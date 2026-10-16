<?php
// claim.php — xử lý lần quay về từ Link4m/Layma và chỉ cấp key khi đủ lượt.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/keygen_functions.php';

@date_default_timezone_set('Asia/Ho_Chi_Minh');
session_start();

$ip = getClientIp();
$pageMode = 'error';
$blockedMsg = '';
$keyText = null;
$keyInfo = null;
$gameLabel = 'Unknown';
$durationLabel = '';
$expiresLabel = '';
$remainingLabel = '';
$ticket = getCurrentClaimTicket($ip);
$gameKey = '';
$durationKey = '';
$durationCfg = null;
$laymaRemaining = 0;
$laymaTotal = 0;
$stepNo = 2;
$stepTotal = 2;

$issueKey = function (array $activeTicket) use (&$pageMode, &$blockedMsg, &$keyText, &$keyInfo, &$gameLabel, &$durationLabel, &$gameKey, &$durationKey, &$durationCfg, $ip, $KEYGEN_APPS, $KEYGEN_DURATIONS): void {
    // Chỉ áp dụng các giới hạn lúc thật sự sắp cấp key, không tính mỗi lần quay về từ link.
    if (!checkRateLimit('claim_' . $ip, 10, 60)) {
        $blockedMsg = 'Quá nhiều yêu cầu. Vui lòng đợi vài phút rồi thử lại!';
        return;
    }
    cleanupOldKeyPages();
    if (countRecentKeysByIp($ip) >= KEYGEN_MAX_PER_HOUR) {
        setClaimTicketStatus($activeTicket, 'invalid');
        $blockedMsg = 'Bạn đã hết lượt lấy key trong giờ này. Vui lòng thử lại sau!';
        return;
    }

    $gameKey = (string)($activeTicket['game'] ?? '');
    $durationKey = (string)($activeTicket['duration'] ?? '');
    if (!isset($KEYGEN_APPS[$gameKey], $KEYGEN_DURATIONS[$durationKey])) {
        setClaimTicketStatus($activeTicket, 'invalid');
        $blockedMsg = 'Vé không hợp lệ. Vui lòng quay lại trang GetKey!';
        return;
    }
    $appCfg = $KEYGEN_APPS[$gameKey];
    $durationCfg = $KEYGEN_DURATIONS[$durationKey];
    $gameLabel = $appCfg['label'];
    $durationLabel = $durationCfg['label'];

    if (!beginClaimTicketIssuance($activeTicket)) {
        $blockedMsg = 'Vé đã được sử dụng hoặc đang xử lý. Vui lòng đợi hoặc tạo yêu cầu mới!';
        return;
    }

    try {
        // createKey nhận thời hạn theo ngày; 12h được biểu diễn chính xác bằng 0.5 ngày.
        $durationDays = ((int)$durationCfg['seconds']) / 86400;
        $keyText = createKey($appCfg['prefix'], $durationDays, 1, 'Free key - ' . $gameKey . ' - ' . $durationLabel, $gameKey);
        logClaim($keyText, $ip);
        $keyInfo = getKeyByKey($keyText);
        setClaimTicketStatus($activeTicket, 'completed'); // Vé chỉ dùng một lần, sau khi cấp key thành công.
        $pageMode = 'success';
    } catch (Throwable $e) {
        setClaimTicketStatus($activeTicket, 'invalid');
        $blockedMsg = 'Lỗi tạo key. Vui lòng thử lại sau!';
        $pageMode = 'error';
    }
};

if (!is_array($ticket)) {
    $blockedMsg = 'Không tìm thấy phiên lấy key. Hãy bắt đầu từ trang GetKey.';
} elseif (($ticket['ip'] ?? '') !== $ip) {
    setClaimTicketStatus($ticket, 'invalid');
    $blockedMsg = 'Phiên lấy key không hợp lệ với kết nối hiện tại. Vui lòng tạo yêu cầu mới!';
} elseif (time() - (int)($ticket['created'] ?? 0) > KEYGEN_TICKET_TTL_SECONDS) {
    setClaimTicketStatus($ticket, 'expired');
    $blockedMsg = 'Phiên lấy key đã hết hạn. Vui lòng tạo yêu cầu mới!';
} else {
    $gameKey = (string)($ticket['game'] ?? '');
    $durationKey = (string)($ticket['duration'] ?? '');
    if (!isset($KEYGEN_APPS[$gameKey], $KEYGEN_DURATIONS[$durationKey])) {
        setClaimTicketStatus($ticket, 'invalid');
        $blockedMsg = 'Vé không hợp lệ. Vui lòng tạo yêu cầu mới!';
    } else {
        $gameLabel = $KEYGEN_APPS[$gameKey]['label'];
        $durationCfg = $KEYGEN_DURATIONS[$durationKey];
        $durationLabel = $durationCfg['label'];
        $laymaTotal = (int)$durationCfg['layma_passes'];
        $laymaRemaining = max(0, (int)($ticket['layma_remaining'] ?? 0));
        $stepTotal = 1 + $laymaTotal;

        $stage = (string)($ticket['stage'] ?? '');
        if ($stage === 'ready_link4m') {
            $blockedMsg = 'Bạn chưa bắt đầu vượt Link4m. Hãy quay lại GetKey và bấm nút Vượt Link4m.';
        } elseif ($stage === 'await_link4m_return') {
            $startedAt = (int)($ticket['link4m_started_at'] ?? 0);
            if ($startedAt < 1 || (time() - $startedAt) < KEYGEN_MIN_CLAIM_SECONDS) {
                setClaimTicketStatus($ticket, 'invalid');
                $blockedMsg = 'Link4m chưa đủ thời gian tối thiểu 45 giây. Vui lòng tạo yêu cầu mới và vượt link đầy đủ!';
            } elseif ($laymaRemaining > 0) {
                $ticket['stage'] = 'ready_layma';
                $ticket['link4m_started_at'] = null;
                if (!saveClaimTicket($ticket)) {
                    $blockedMsg = 'Vé không còn hợp lệ. Vui lòng tạo yêu cầu mới!';
                } else {
                    $pageMode = 'layma';
                }
            } else {
                // 12h: không còn lượt Layma. Lưu bước cuối thành công trước khi cấp key.
                $ticket['stage'] = 'ready_to_issue';
                if (!saveClaimTicket($ticket)) {
                    $blockedMsg = 'Không thể xác nhận vé sau Link4m. Vui lòng thử lại từ GetKey!';
                } else {
                    // Phản hồi claim này chính là UI ra key; không bắt người dùng claim thêm lần nữa.
                    $issueKey($ticket);
                }
            }
        } elseif ($stage === 'ready_layma') {
            // Hiển thị bước Layma. Refresh trang không tự trừ lượt vì chưa bấm nút bắt đầu link.
            $pageMode = 'layma';
        } elseif ($stage === 'await_layma_return') {
            $laymaStartedAt = (int)($ticket['layma_started_at'] ?? 0);
            if ($laymaStartedAt < 1) {
                $ticket['stage'] = 'ready_layma';
                $ticket['layma_started_at'] = null;
                saveClaimTicket($ticket);
                $pageMode = 'layma';
            } elseif ((time() - $laymaStartedAt) < KEYGEN_MIN_LAYMA_SECONDS) {
                // Không đếm một lượt nếu người dùng quay về quá nhanh sau khi bấm mở Layma.
                setClaimTicketStatus($ticket, 'invalid');
                $blockedMsg = 'Layma.net chưa đủ thời gian tối thiểu 45 giây. Vui lòng tạo yêu cầu mới và hoàn tất lượt link!';
            } else {
                $laymaRemaining = max(0, $laymaRemaining - 1);
                $ticket['layma_started_at'] = null;
                $ticket['layma_remaining'] = $laymaRemaining;
                if ($laymaRemaining > 0) {
                    $ticket['stage'] = 'ready_layma';
                    saveClaimTicket($ticket);
                    $pageMode = 'layma';
                } else {
                    // Đây là lượt Layma cuối (claim thứ 2 với gói 1 ngày, claim thứ 3 với gói 2 ngày).
                    // Lưu trạng thái hoàn tất trước khi cấp key; refresh không trừ thêm lượt Layma.
                    $ticket['stage'] = 'ready_to_issue';
                    if (!saveClaimTicket($ticket)) {
                        $blockedMsg = 'Không thể xác nhận lượt Layma cuối. Vui lòng tạo yêu cầu mới từ GetKey!';
                    } else {
                        // Cấp key ngay trong request claim cuối và render pageMode=success bên dưới.
                        $issueKey($ticket);
                    }
                }
            }
        } elseif ($stage === 'ready_to_issue') {
            $issueKey($ticket);
        } elseif ($stage === 'issuing') {
            $blockedMsg = 'Vé đang được xử lý. Vui lòng đợi trong giây lát, không bấm Generate thêm lần nữa!';
        } else {
            setClaimTicketStatus($ticket, 'invalid');
            $blockedMsg = 'Trạng thái vé không hợp lệ. Vui lòng bắt đầu lại từ GetKey!';
        }

        if ($pageMode === 'layma') {
            $laymaTotal = (int)$durationCfg['layma_passes'];
            $stepTotal = 1 + $laymaTotal;
            $stepNo = 2 + ($laymaTotal - $laymaRemaining);
        }
    }
}

if ($pageMode === 'error' && $blockedMsg === '') {
    $blockedMsg = 'Không thể hoàn tất yêu cầu. Vui lòng quay lại trang GetKey.';
}
if ($pageMode === 'success' && $keyInfo) {
    $expiresLabel = $keyInfo['expires_at'] ?: 'Vĩnh viễn';
    $remainingLabel = getTimeRemaining($keyInfo['expires_at']);
}
$headlineText = $pageMode === 'success' ? 'Key của bạn' : ($pageMode === 'layma' ? 'Tiếp tục' : 'Chưa hoàn tất');
$laymaUrlJson = json_encode(KEYGEN_LAYMA_URL, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#F6F4EE">
<title><?= $pageMode === 'success' ? 'Key của bạn — KEY.SYS' : ($pageMode === 'layma' ? 'Tiếp tục vượt link — KEY.SYS' : 'Yêu cầu chưa hoàn tất — KEY.SYS') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,wght@0,400;0,500;0,600;1,400&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{
  --paper:#F6F4EE; --card:#FCFBF7; --ink:#191B17; --ink-soft:#4C4A42;
  --grid:#DEDAD0; --accent:#2B4FD8; --accent-ink:#F6F4EE; --amber:#C4780A;
  --line:#191B17; --good:#23734D; color-scheme:light;
  padding-top:env(safe-area-inset-top,0px); padding-bottom:env(safe-area-inset-bottom,0px);
}
@media(prefers-color-scheme:dark){
  :root:not([data-theme="light"]){
    --paper:#15160F; --card:#1C1D15; --ink:#EDEAE0; --ink-soft:#A6A295;
    --grid:#2B2C22; --accent:#7D95F5; --accent-ink:#15160F; --amber:#E0A03E; --line:#EDEAE0; --good:#79C79A;
    color-scheme:dark;
  }
}
*{box-sizing:border-box;}
html{scroll-padding-top:env(safe-area-inset-top,0px);}
body{
  margin:0; min-height:100vh; background:var(--paper); color:var(--ink); font-family:"Newsreader",serif;
  background-image:linear-gradient(var(--grid) 1px,transparent 1px),linear-gradient(90deg,var(--grid) 1px,transparent 1px);
  background-size:28px 28px; cursor:auto;
}
a,button{font-family:inherit;}
.mono{font-family:"IBM Plex Mono",monospace;}

/* custom cursor — native OS pointer shows everywhere by default; the custom dot (sized like
   the default Windows cursor) plus the blue ring only fade in together over buttons/links */
#cur{position:fixed; width:46px; height:46px; border:2px solid var(--accent); border-radius:50%; pointer-events:none; z-index:999; transform:translate(-50%,-50%); opacity:0; transition:opacity .15s ease, background .2s ease;}
#curdot{position:fixed; width:18px; height:18px; background:var(--accent); border-radius:50%; pointer-events:none; z-index:999; transform:translate(-50%,-50%); opacity:0; transition:opacity .15s ease;}
#cur.hover{opacity:1; background:color-mix(in srgb, var(--accent) 18%, transparent);}
#curdot.hover{opacity:1;}
@media(pointer:coarse){ #cur,#curdot{display:none;} }
button, a, .copy-btn{ cursor:none; }
.ripple{position:fixed; border:1.3px solid var(--line); border-radius:50%; pointer-events:none; z-index:998; transform:translate(-50%,-50%); animation:rip .6s ease-out forwards;}
@keyframes rip{ to{ width:90px; height:90px; opacity:0; } }
.caret{display:inline-block; width:.07em; background:var(--accent); margin-left:3px; animation:blink 1s steps(1) infinite;}
@keyframes blink{ 50%{ opacity:0; } }
button:focus-visible, a:focus-visible{ outline:2px solid var(--accent); outline-offset:3px; }

/* top bar */
.bar{display:flex; justify-content:space-between; align-items:center; padding:22px 6vw; border-bottom:1.3px solid var(--line);}
.wordmark{font-family:"IBM Plex Mono",monospace; font-size:13px; letter-spacing:.04em;}
.wordmark b{color:var(--accent);}
.node{font-family:"IBM Plex Mono",monospace; font-size:10px; line-height:1.6; color:var(--ink-soft); text-align:right;}
.node i{display:inline-block; width:7px; height:7px; border-radius:50%; background:var(--good); margin-right:6px;}

/* hero */
.hero{padding:9vh 6vw 5vh; border-bottom:1.3px solid var(--line);}
.eyebrow{font-family:"IBM Plex Mono",monospace; font-size:12px; color:var(--ink-soft); letter-spacing:.07em; margin:0 0 14px;}
.hero h1{font-size:clamp(44px,8vw,86px); font-weight:500; line-height:1.0; letter-spacing:-.02em; margin:0;}
.hero-sub{max-width:52ch; color:var(--ink-soft); font-size:17px; line-height:1.55; margin:18px 0 0;}

main{max-width:720px; margin:0 auto; padding:0 6vw 8vh;}
.statusline{display:flex; justify-content:space-between; align-items:center; gap:12px; margin:26px 0 2px; font-family:"IBM Plex Mono",monospace; font-size:11px; color:var(--ink-soft); letter-spacing:.03em;}
.statusleft{display:flex; align-items:center; gap:9px;}
.statusmark{width:25px; height:25px; border:1.3px solid var(--good); border-radius:50%; display:grid; place-items:center; color:var(--good); font-size:14px; flex:0 0 auto;}
.statusmark.warn{border-color:var(--amber); color:var(--amber);}
.statuspill{border:1px solid var(--good); color:var(--good); padding:5px 8px; white-space:nowrap;}
.statuspill.warn{border-color:var(--amber); color:var(--amber);}

/* card — with mouse-tracking spotlight glow */
.card{position:relative; margin-top:18px; background:var(--card); border:1.3px solid var(--line); box-shadow:7px 7px 0 var(--line); transition:transform .18s ease, box-shadow .18s ease;}
.card:hover{transform:translate(1px,1px); box-shadow:5px 5px 0 var(--line);}
.card::before{content:""; position:absolute; inset:0; z-index:0; pointer-events:none; background:radial-gradient(260px circle at var(--mx,50%) var(--my,50%), color-mix(in srgb, var(--accent) 16%, transparent), transparent 70%); opacity:0; transition:opacity .25s ease;}
.card:hover::before{opacity:1;}
.card-head{position:relative; z-index:1; display:flex; justify-content:space-between; align-items:center; gap:14px; padding:20px 24px; border-bottom:1.3px solid var(--line);}
.card-head h2{font-size:22px; font-weight:500; margin:0;}
.ref{font-family:"IBM Plex Mono",monospace; font-size:11px; color:var(--ink-soft); text-align:right;}

/* key delivery panel */
.key-area{position:relative; z-index:1; padding:23px; border-bottom:1px solid var(--grid);}
.key-label{font-family:"IBM Plex Mono",monospace; font-size:11px; letter-spacing:.07em; color:var(--ink-soft); margin-bottom:10px;}
.keybox{position:relative; display:flex; align-items:center; justify-content:space-between; gap:12px; padding:15px 16px; background:var(--paper); border:1.3px dashed var(--accent); min-height:66px; overflow:hidden;}
.keybox::before{content:""; position:absolute; inset:0; z-index:0; pointer-events:none; background:radial-gradient(180px circle at var(--mx,50%) var(--my,50%), color-mix(in srgb, var(--accent) 18%, transparent), transparent 70%); opacity:0; transition:opacity .25s ease;}
.keybox:hover::before{opacity:1;}
.keybox>*{position:relative; z-index:1;}
.keytext{font-family:"IBM Plex Mono",monospace; font-size:clamp(14px,2.3vw,19px); letter-spacing:.025em; overflow-wrap:anywhere; user-select:all;}
.copy-btn{flex:0 0 auto; display:flex; align-items:center; gap:7px; padding:9px 11px; border:1.3px solid var(--line); background:var(--card); color:var(--ink); font-family:"IBM Plex Mono",monospace; font-size:11px; transition:background .2s, color .2s, transform .15s;}
.copy-btn:hover{background:var(--accent); color:var(--accent-ink);}
.copy-btn:active{transform:translate(2px,2px);}
.copy-btn svg{width:14px; height:14px; stroke:currentColor; fill:none; stroke-width:1.7;}

.meta{position:relative; z-index:1; display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:1px; background:var(--line); border-bottom:1.3px solid var(--line);}
.meta-item{background:var(--card); padding:16px 21px; min-width:0;}
.meta-label{display:block; font-family:"IBM Plex Mono",monospace; font-size:10px; color:var(--ink-soft); margin-bottom:8px; letter-spacing:.035em;}
.meta-value{font-size:18px; overflow-wrap:anywhere;}
.meta-value.mono{font-family:"IBM Plex Mono",monospace; font-size:12px; line-height:1.5;}
.meta-value.app{color:var(--accent); font-weight:600;}
.state{display:inline-flex; align-items:center; gap:7px;}
.state::before{content:""; width:7px; height:7px; border-radius:50%; background:var(--good); display:inline-block;}

.card-foot{position:relative; z-index:1; display:flex; justify-content:space-between; align-items:center; gap:16px; padding:19px 23px;}
.footnote{font-family:"IBM Plex Mono",monospace; font-size:10px; line-height:1.7; color:var(--ink-soft); max-width:34ch;}

.primary{background:var(--accent); color:var(--accent-ink); border:1.3px solid var(--line); padding:13px 20px; font-family:"IBM Plex Mono",monospace; font-size:12px; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; gap:9px; position:relative; transition:transform .12s ease, letter-spacing .2s ease;}
.primary:hover{letter-spacing:.04em;}
.primary::before,.primary::after{content:''; position:absolute; width:9px; height:9px; border:1.6px solid var(--line); opacity:0; transition:opacity .2s ease, transform .2s ease;}
.primary::before{top:-5px; left:-5px; border-right:none; border-bottom:none; transform:translate(5px,5px);}
.primary::after{bottom:-5px; right:-5px; border-left:none; border-top:none; transform:translate(-5px,-5px);}
.primary:hover::before,.primary:hover::after{opacity:1; transform:translate(0,0);}
.primary:active{transform:translate(2px,2px);}
.primary:disabled{opacity:.6; cursor:wait;}
.primary svg{width:15px; height:15px; stroke:currentColor; fill:none; stroke-width:1.8;}

/* in-progress (Layma) panel */
.progress{position:relative; z-index:1; padding:22px 23px 8px;}
.progress-top{display:flex; justify-content:space-between; gap:12px; font-family:"IBM Plex Mono",monospace; font-size:10px; color:var(--ink-soft); margin-bottom:10px;}
.progress-track{display:flex; gap:5px;}
.progress-track span{height:4px; flex:1; background:var(--grid);}
.progress-track span.done{background:var(--accent);}
.instructions{position:relative; z-index:1; padding:17px 23px 22px;}
.instructions h3{font-size:25px; font-weight:500; margin:0 0 10px;}
.instructions p{font-size:15px; line-height:1.6; color:var(--ink-soft); margin:6px 0;}
.instructions .small{font-family:"IBM Plex Mono",monospace; font-size:10px; line-height:1.7; color:var(--amber); margin-top:15px;}
.instructions .primary{margin-top:6px;}

/* error panel */
.errorbox{position:relative; z-index:1; padding:30px 24px; text-align:center;}
.error-symbol{width:48px; height:48px; border:1.3px solid var(--amber); color:var(--amber); border-radius:50%; display:grid; place-items:center; font-size:23px; margin:0 auto 15px;}
.errorbox h2{font-size:25px; font-weight:500; margin:0 0 12px;}
.errorbox p{font-size:16px; line-height:1.6; color:var(--ink-soft); margin:0 auto 19px; max-width:48ch;}

.bottom{display:flex; justify-content:space-between; align-items:center; gap:15px; margin-top:27px;}
.back{color:var(--ink); font-family:"IBM Plex Mono",monospace; font-size:11px; text-decoration:none; border-bottom:1px solid var(--ink); padding-bottom:4px; transition:color .2s, border-color .2s;}
.back:hover{color:var(--accent); border-color:var(--accent);}
.security{font-family:"IBM Plex Mono",monospace; font-size:9px; line-height:1.7; color:var(--ink-soft); text-align:right;}
.footer{margin:31px 0 0; text-align:center; font-family:"IBM Plex Mono",monospace; font-size:10px; line-height:1.7; color:var(--ink-soft);}
.footer strong{color:var(--ink); font-weight:500;}

.toast{position:fixed; left:50%; bottom:24px; transform:translate(-50%,18px); opacity:0; pointer-events:none; background:var(--ink); color:var(--paper); padding:12px 16px; font-family:"IBM Plex Mono",monospace; font-size:12px; z-index:1000; box-shadow:4px 4px 0 var(--accent); transition:opacity .2s, transform .2s; max-width:calc(100vw - 32px); text-align:center;}
.toast.show{opacity:1; transform:translate(-50%,0);}

@media(max-width:560px){
  .bar{padding:16px 20px;} .node{font-size:8px;} .hero{padding:6vh 20px 4vh;} .hero h1{font-size:46px;} .hero-sub{font-size:16px;}
  main{padding:0 17px 38px;} .statusline{font-size:9px;} .card-head,.key-area,.card-foot{padding-left:16px; padding-right:16px;}
  .card-head h2{font-size:19px;} .keybox{align-items:stretch; flex-direction:column;} .copy-btn{justify-content:center;}
  .meta-item{padding:14px;} .meta-label{font-size:9px;} .meta-value{font-size:16px;} .meta-value.mono{font-size:11px;}
  .card-foot{align-items:stretch; flex-direction:column;} .primary{width:100%;}
  .bottom{align-items:flex-start; flex-direction:column;} .security{text-align:left;}
  .progress,.instructions{padding-left:16px; padding-right:16px;}
}
</style>
</head>
<body>
<div id="cur" aria-hidden="true"></div><div id="curdot" aria-hidden="true"></div>
<header class="bar">
  <div class="wordmark"><b>KEY</b>.SYS — CLAIM PORTAL</div>
  <div class="node"><i></i>NODE VN-01<br>LICENSE DELIVERY</div>
</header>

<section class="hero">
  <p class="eyebrow">CLAIM / LICENSE DELIVERY</p>
  <h1 id="headline"><span class="caret">&nbsp;</span></h1>
  <?php if ($pageMode === 'success'): ?>
    <p class="hero-sub">Yêu cầu đã được xử lý. Sao chép license bên dưới và lưu lại để sử dụng trên một thiết bị.</p>
  <?php elseif ($pageMode === 'layma'): ?>
    <p class="hero-sub">Link4m đã được ghi nhận. Hoàn thành các lượt Layma.net còn lại để hệ thống cấp key theo đúng thời hạn đã chọn.</p>
  <?php else: ?>
    <p class="hero-sub">Hệ thống chưa thể hoàn tất yêu cầu hiện tại. Đọc thông báo bên dưới, sau đó quay lại GetKey để bắt đầu phiên mới.</p>
  <?php endif; ?>
</section>

<main>
<?php if ($pageMode === 'success' && $keyText): ?>
  <div class="statusline"><div class="statusleft"><span class="statusmark">✓</span><span>CLAIM COMPLETED / LICENSE READY</span></div><span class="statuspill">SUCCESS</span></div>
  <section class="card" aria-label="Thông tin license">
    <div class="card-head"><h2>Thông tin license</h2><span class="ref">KEY.SYS / DELIVERY</span></div>
    <div class="key-area">
      <div class="key-label">YOUR LICENSE KEY</div>
      <div class="keybox">
        <span class="keytext" id="keyText"><?= htmlspecialchars($keyText, ENT_QUOTES, 'UTF-8') ?></span>
        <button class="copy-btn" id="copyBtn" type="button"><svg viewBox="0 0 24 24"><rect x="8" y="8" width="12" height="12" rx="1"></rect><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"></path></svg><span id="copyLabel">COPY KEY</span></button>
      </div>
    </div>
    <div class="meta">
      <div class="meta-item"><span class="meta-label">ỨNG DỤNG / APP</span><span class="meta-value app"><?= htmlspecialchars($gameLabel, ENT_QUOTES, 'UTF-8') ?></span></div>
      <div class="meta-item"><span class="meta-label">THỜI HẠN</span><span class="meta-value mono"><?= htmlspecialchars($durationLabel, ENT_QUOTES, 'UTF-8') ?></span></div>
      <div class="meta-item"><span class="meta-label">NGÀY HẾT HẠN</span><span class="meta-value mono"><?= htmlspecialchars($expiresLabel, ENT_QUOTES, 'UTF-8') ?></span></div>
      <div class="meta-item"><span class="meta-label">THỜI GIAN CÒN LẠI</span><span class="meta-value"><?= htmlspecialchars($remainingLabel, ENT_QUOTES, 'UTF-8') ?></span></div>
      <div class="meta-item" style="grid-column:1/-1"><span class="meta-label">TRẠNG THÁI / STATUS</span><span class="meta-value state"><?= ($keyInfo && ($keyInfo['status'] ?? '') === 'active') ? 'Hoạt động' : 'Đang kiểm tra' ?></span></div>
    </div>
    <div class="card-foot">
      <span class="footnote">KEY ĐƯỢC GẮN VỚI 1 THIẾT BỊ.<br>Không chia sẻ license với người khác.</span>
      <button class="primary" id="copyPrimary" type="button"><svg viewBox="0 0 24 24"><rect x="8" y="8" width="12" height="12" rx="1"></rect><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"></path></svg>Sao chép key</button>
    </div>
  </section>
<?php elseif ($pageMode === 'layma'): ?>
  <div class="statusline"><div class="statusleft"><span class="statusmark">↗</span><span>LINK VERIFICATION / STEP <?= (int)$stepNo ?> OF <?= (int)$stepTotal ?></span></div><span class="statuspill">IN PROGRESS</span></div>
  <section class="card" aria-label="Bước tiếp theo">
    <div class="card-head"><h2>Tiếp tục xác minh</h2><span class="ref">LAYMA.NET / <?= (int)$laymaRemaining ?> LEFT</span></div>
    <div class="progress">
      <div class="progress-top"><span>TIẾN TRÌNH VƯỢT LINK</span><span><?= (int)$stepNo ?>/<?= (int)$stepTotal ?></span></div>
      <div class="progress-track"><?php for ($i = 1; $i <= $stepTotal; $i++): ?><span class="<?= $i < $stepNo ? 'done' : '' ?>"></span><?php endfor; ?></div>
    </div>
    <div class="instructions">
      <h3>Đã hoàn tất Link4m</h3>
      <p>Thời hạn đã chọn: <strong><?= htmlspecialchars($durationLabel, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
      <p>Bạn còn <strong><?= (int)$laymaRemaining ?> lượt Layma.net</strong>. Nhấn nút bên dưới để tiếp tục. Mỗi lượt Layma.net cần tối thiểu 45 giây trước khi quay lại. Khi trở về trang claim, hệ thống sẽ kiểm tra bước tiếp theo.</p>
      <button class="primary" id="laymaBtn" type="button">Vượt Layma.net · Còn <?= (int)$laymaRemaining ?> lượt</button>
      <p class="small">Không cần tự nhập key. Key sẽ được cấp tự động sau khi hoàn tất đủ số lượt yêu cầu.</p>
    </div>
  </section>
<?php else: ?>
  <div class="statusline"><div class="statusleft"><span class="statusmark warn">!</span><span>CLAIM NOT COMPLETED / ACTION REQUIRED</span></div><span class="statuspill warn">NOTICE</span></div>
  <section class="card">
    <div class="card-head"><h2>Không thể cấp key</h2><span class="ref">CLAIM / NOTICE</span></div>
    <div class="errorbox">
      <div class="error-symbol">!</div>
      <h2>Phiên chưa hoàn tất</h2>
      <p><?= htmlspecialchars($blockedMsg, ENT_QUOTES, 'UTF-8') ?></p>
      <a class="primary" href="getkey">← Quay lại GetKey</a>
    </div>
  </section>
<?php endif; ?>
  <div class="bottom"><a href="getkey" class="back">← LẤY KEY KHÁC</a><div class="security">SESSION TICKET · 1 DEVICE<br>KEY.SYS / VN-01</div></div>
  <p class="footer"><strong>KEY.SYS</strong> — Hệ thống cấp license tự động.<br>Không chia sẻ key hoặc vé xác minh với người khác.</p>
</main>
<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script>
// custom cursor — dot snaps, ring trails; both hidden until hovering a button/link
const cur=document.getElementById('cur'), dot=document.getElementById('curdot');
let mx=innerWidth/2, my=innerHeight/2, rx=mx, ry=my;
window.addEventListener('mousemove',e=>{
  mx=e.clientX; my=e.clientY;
  dot.style.left=mx+'px'; dot.style.top=my+'px';
});
(function loop(){
  rx+=(mx-rx)*0.22; ry+=(my-ry)*0.22;
  cur.style.left=rx+'px'; cur.style.top=ry+'px';
  requestAnimationFrame(loop);
})();
function bindHover(el){
  el.addEventListener('mouseenter',()=>{ cur.classList.add('hover'); dot.classList.add('hover'); });
  el.addEventListener('mouseleave',()=>{ cur.classList.remove('hover'); dot.classList.remove('hover'); });
}
document.querySelectorAll('button,a').forEach(bindHover);

// click ripple
document.addEventListener('click',e=>{
  const r=document.createElement('div'); r.className='ripple';
  r.style.left=e.clientX+'px'; r.style.top=e.clientY+'px';
  document.body.appendChild(r); setTimeout(()=>r.remove(),600);
});

// mouse-tracking spotlight glow on card + keybox
document.querySelectorAll('.card, .keybox').forEach(panel=>{
  panel.addEventListener('mousemove', e=>{
    const r=panel.getBoundingClientRect();
    panel.style.setProperty('--mx', (e.clientX-r.left)+'px');
    panel.style.setProperty('--my', (e.clientY-r.top)+'px');
  });
});

// headline typewriter — types once (page reflects server state, no loop needed here)
const headline=document.getElementById('headline');
const titleText=<?= json_encode($headlineText, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
let hi=0;
(function typeTitle(){
  headline.innerHTML=titleText.slice(0,hi)+'<span class="caret">&nbsp;</span>';
  if(hi<titleText.length){ hi++; setTimeout(typeTitle,45); }
})();

// toast
function toast(msg){
  const el=document.getElementById('toast');
  el.textContent=msg; el.classList.add('show');
  clearTimeout(window.toastTimer);
  window.toastTimer=setTimeout(()=>el.classList.remove('show'),2600);
}

// copy key
async function copyKey(){
  const key=document.getElementById('keyText')?.textContent.trim();
  if(!key) return;
  const done=()=>{
    const l=document.getElementById('copyLabel');
    if(l){ l.textContent='COPIED!'; setTimeout(()=>l.textContent='COPY KEY',2300); }
    toast('Đã sao chép key vào clipboard');
  };
  try{
    if(navigator.clipboard && navigator.clipboard.writeText){ await navigator.clipboard.writeText(key); done(); return; }
  }catch(e){}
  const ta=document.createElement('textarea'); ta.value=key; ta.style.position='fixed'; ta.style.opacity='0';
  document.body.appendChild(ta); ta.select();
  let ok=false; try{ ok=document.execCommand('copy'); }catch(e){}
  ta.remove();
  if(ok) done(); else toast('Không thể tự sao chép. Hãy chọn key và sao chép thủ công.');
}
document.getElementById('copyBtn')?.addEventListener('click', copyKey);
document.getElementById('copyPrimary')?.addEventListener('click', copyKey);

// Layma continue button
const laymaBtn=document.getElementById('laymaBtn');
const laymaUrl=<?= $laymaUrlJson ?>;
laymaBtn?.addEventListener('click', async()=>{
  laymaBtn.disabled=true; laymaBtn.textContent='Đang mở Layma.net…';
  try{
    const fd=new FormData(); fd.set('action','begin_layma');
    const res=await fetch('getkey_action.php',{method:'POST',body:fd,credentials:'same-origin'});
    const data=await res.json();
    if(!res.ok || data.status!=='success'){
      toast(data.message || 'Không thể bắt đầu lượt Layma.net.');
      laymaBtn.disabled=false; laymaBtn.textContent='Thử lại Layma.net';
      return;
    }
    window.location.assign(laymaUrl);
  }catch(e){
    toast('Lỗi kết nối. Vui lòng thử lại.');
    laymaBtn.disabled=false; laymaBtn.textContent='Thử lại Layma.net';
  }
});
</script>
</body>
</html>
