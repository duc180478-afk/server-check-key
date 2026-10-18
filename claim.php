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
        $returnedToken = trim((string)($_GET['k'] ?? ''));
        $expectedToken = (string)($ticket['step_token'] ?? '');

        $prepareNextLaymaStep = function (array &$t) use (&$blockedMsg): bool {
            $newToken = bin2hex(random_bytes(16));
            try {
                $shortLayma = createDynamicLaymaUrl(buildClaimCallbackUrl($newToken));
            } catch (Throwable $e) {
                $blockedMsg = 'Không thể kết nối API Layma.net lúc này. Vui lòng tải lại trang để thử lại!';
                return false;
            }
            $t['stage'] = 'ready_layma';
            $t['link4m_started_at'] = null;
            $t['layma_started_at'] = null;
            $t['step_token'] = $newToken;
            $t['current_short_url'] = $shortLayma;
            if (!saveClaimTicket($t)) {
                $blockedMsg = 'Vé không còn hợp lệ. Vui lòng tạo yêu cầu mới!';
                return false;
            }
            return true;
        };

        if ($stage === 'ready_link4m') {
            $blockedMsg = 'Bạn chưa bắt đầu vượt Link4m. Hãy quay lại GetKey và bấm nút Vượt Link4m.';
        } elseif ($stage === 'await_link4m_return') {
            $startedAt = (int)($ticket['link4m_started_at'] ?? 0);
            if ($startedAt < 1 || (time() - $startedAt) < KEYGEN_MIN_CLAIM_SECONDS) {
                setClaimTicketStatus($ticket, 'invalid');
                $blockedMsg = 'Link4m chưa đủ thời gian tối thiểu 45 giây. Vui lòng tạo yêu cầu mới và vượt link đầy đủ!';
            } elseif ($expectedToken === '' || $returnedToken === '' || !hash_equals($expectedToken, $returnedToken)) {
                setClaimTicketStatus($ticket, 'invalid');
                $blockedMsg = 'Phát hiện truy cập trực tiếp không qua Link4m! Vui lòng tạo yêu cầu mới và vượt link hợp lệ.';
            } elseif ($laymaRemaining > 0) {
                if ($prepareNextLaymaStep($ticket)) {
                    $pageMode = 'layma';
                }
            } else {
                // 12h: không còn lượt Layma. Lưu bước cuối thành công trước khi cấp key.
                $ticket['stage'] = 'ready_to_issue';
                $ticket['step_token'] = '';
                if (!saveClaimTicket($ticket)) {
                    $blockedMsg = 'Không thể xác nhận vé sau Link4m. Vui lòng thử lại từ GetKey!';
                } else {
                    $issueKey($ticket);
                }
            }
        } elseif ($stage === 'ready_layma') {
            // Hiển thị bước Layma. Refresh trang không tự trừ lượt vì chưa bấm nút bắt đầu link.
            if (empty($ticket['current_short_url'])) {
                if ($prepareNextLaymaStep($ticket)) {
                    $pageMode = 'layma';
                }
            } else {
                $pageMode = 'layma';
            }
        } elseif ($stage === 'await_layma_return') {
            $laymaStartedAt = (int)($ticket['layma_started_at'] ?? 0);
            if ($laymaStartedAt < 1) {
                $ticket['stage'] = 'ready_layma';
                $ticket['layma_started_at'] = null;
                saveClaimTicket($ticket);
                $pageMode = 'layma';
            } elseif ((time() - $laymaStartedAt) < KEYGEN_MIN_LAYMA_SECONDS) {
                setClaimTicketStatus($ticket, 'invalid');
                $blockedMsg = 'Layma.net chưa đủ thời gian tối thiểu 45 giây. Vui lòng tạo yêu cầu mới và hoàn tất lượt link!';
            } elseif ($expectedToken === '' || $returnedToken === '' || !hash_equals($expectedToken, $returnedToken)) {
                setClaimTicketStatus($ticket, 'invalid');
                $blockedMsg = 'Xác thực bước Layma.net thất bại (không tìm thấy token vượt link). Vui lòng tạo yêu cầu mới!';
            } else {
                $laymaRemaining = max(0, $laymaRemaining - 1);
                $ticket['layma_started_at'] = null;
                $ticket['layma_remaining'] = $laymaRemaining;
                if ($laymaRemaining > 0) {
                    if ($prepareNextLaymaStep($ticket)) {
                        $pageMode = 'layma';
                    }
                } else {
                    // Đây là lượt Layma cuối (claim thứ 2 với gói 1 ngày, claim thứ 3 với gói 2 ngày).
                    $ticket['stage'] = 'ready_to_issue';
                    $ticket['step_token'] = '';
                    if (!saveClaimTicket($ticket)) {
                        $blockedMsg = 'Không thể xác nhận lượt Layma cuối. Vui lòng tạo yêu cầu mới từ GetKey!';
                    } else {
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
$activeLaymaUrl = !empty($ticket['current_short_url']) ? $ticket['current_short_url'] : KEYGEN_LAYMA_URL;
$laymaUrlJson = json_encode($activeLaymaUrl, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
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

/* custom cursor */
#cur{position:fixed; width:46px; height:46px; border:2px solid var(--accent); border-radius:50%; pointer-events:none; z-index:999; transform:translate(-50%,-50%); opacity:0; transition:opacity .15s ease, background .2s ease;}
#curdot{position:fixed; width:18px; height:18px; background:var(--accent); border-radius:50%; pointer-events:none; z-index:999; transform:translate(-50%,-50%); opacity:0; transition:opacity .15s ease;}
#cur.hover{opacity:1; background:color-mix(in srgb, var(--accent) 18%, transparent);}
#curdot.hover{opacity:1;}
@media(pointer:coarse){ #cur,#curdot{display:none;} }
button, a, .copy-btn{ cursor:none; }
.ripple{position:fixed; border-radius:50%; border:1.3px solid var(--line); pointer-events:none; z-index:998; transform:translate(-50%,-50%); animation:rip .6s ease-out forwards;}
@keyframes rip{ to{ width:90px; height:90px; opacity:0; } }
@keyframes blink{ 50%{ opacity:0; } }
button:focus-visible, a:focus-visible{ outline:2px solid var(--accent); outline-offset:2px; }

/* top bar */
.bar{display:flex; align-items:center; justify-content:space-between; padding:22px 6vw; border-bottom:1.3px solid var(--line); position:relative; gap:16px; flex-wrap:wrap;}
.wordmark{font-family:"IBM Plex Mono",monospace; font-size:13px; letter-spacing:.04em; text-decoration:none; color:var(--ink);}
.wordmark b{color:var(--accent);}
.nav-link{font-family:"IBM Plex Mono",monospace; font-size:11.5px; color:var(--ink); text-decoration:none; border:1.3px solid var(--line); background:var(--card); padding:8px 13px; transition:border-color .18s,color .18s,transform .12s,box-shadow .18s;}
.nav-link:hover{border-color:var(--accent); color:var(--accent); box-shadow:3px 3px 0 var(--accent); transform:translate(-1px,-1px);}
.nav-link:active{transform:translate(1px,1px); box-shadow:none;}

/* hero */
.hero{padding:6vh 6vw 4vh; max-width:680px; margin:0 auto;}
.eyebrow{font-family:"IBM Plex Mono",monospace; font-size:12px; color:var(--ink-soft); margin:0 0 12px; letter-spacing:.04em; text-transform:uppercase;}
h1{font-size:clamp(40px,7vw,68px); font-weight:500; margin:0; line-height:1.02; letter-spacing:-.01em;}
.caret{display:inline-block; width:.07em; height:.88em; background:var(--accent); margin-left:4px; vertical-align:-.06em; animation:blink 1s steps(1) infinite;}
.sub{max-width:52ch; font-size:16.5px; color:var(--ink-soft); margin:14px 0 0; line-height:1.55;}

/* main card */
main{padding:0 6vw 10vh; max-width:680px; margin:0 auto;}
.card{position:relative; z-index:1; background:var(--card); border:1.3px solid var(--line); box-shadow:7px 7px 0 var(--line); margin-top:10px; transform:translate(-2px,-2px); transition:transform .18s ease, box-shadow .18s ease;}
.card:hover{transform:translate(0,0); box-shadow:4px 4px 0 var(--line);}
.card::before{
  content:""; position:absolute; inset:0; z-index:2; pointer-events:none;
  background-image:linear-gradient(110deg,
    transparent 47%,
    color-mix(in srgb, var(--accent) 16%, transparent) 49%,
    rgba(255,255,255,.35) 51%,
    transparent 53%);
  background-size:400% 100%;
  background-repeat:no-repeat;
  background-position:100% 0;
  opacity:0;
}
.card:hover::before{ opacity:1; animation:cardGlint .9s ease-out both; }
@keyframes cardGlint{
  from{ background-position:100% 0; opacity:1; }
  to{ background-position:0% 0; opacity:0; }
}
.card-head{display:flex; justify-content:space-between; align-items:center; gap:12px; padding:20px 24px; border-bottom:1.3px solid var(--line); flex-wrap:wrap;}
.card-head h2{font-size:21px; font-weight:500; margin:0;}
.badge{font-family:"IBM Plex Mono",monospace; font-size:11px; border:1px solid var(--amber); color:var(--amber); padding:4px 9px; display:inline-block; transform:rotate(-3deg);}
.badge.good{border-color:var(--good); color:var(--good);}

.row-item{padding:15px 24px; border-bottom:1px solid var(--grid); display:grid; grid-template-columns:140px 1fr; gap:14px; align-items:center;}
.row-item .lbl{font-family:"IBM Plex Mono",monospace; font-size:12px; color:var(--ink-soft);}
.row-item .val{font-family:"IBM Plex Mono",monospace; font-size:13.5px; color:var(--ink); word-break:break-word;}

/* step progress pills */
.steps-box{padding:18px 24px; border-bottom:1px solid var(--grid); display:flex; flex-direction:column; gap:10px;}
.step-line{display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 13px; border:1.2px solid var(--grid); background:var(--paper); font-family:"IBM Plex Mono",monospace; font-size:12px;}
.step-line.done{border-color:var(--good); color:var(--good);}
.step-line.active{border-color:var(--accent); background:color-mix(in srgb, var(--accent) 7%, var(--paper)); color:var(--ink); font-weight:500;}
.step-tag{font-size:10px; padding:2px 7px; border:1px solid currentColor; text-transform:uppercase; letter-spacing:.04em;}

/* key box */
.key-wrap{padding:22px 24px; border-bottom:1px solid var(--grid);}
.key-label{font-family:"IBM Plex Mono",monospace; font-size:11px; color:var(--ink-soft); text-transform:uppercase; letter-spacing:.06em; margin-bottom:8px;}
.key-box{display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 16px; border:1.4px dashed var(--accent); background:var(--paper); font-family:"IBM Plex Mono",monospace; font-size:clamp(14px,3.5vw,18px); font-weight:600; color:var(--accent); word-break:break-all;}

/* notice / error box */
.notice{padding:18px 24px; border-bottom:1px solid var(--grid); font-family:"IBM Plex Mono",monospace; font-size:12.5px; line-height:1.65; color:var(--ink);}
.notice.warn{color:var(--amber); background:color-mix(in srgb, var(--amber) 8%, var(--card));}

/* footer buttons */
.foot{padding:20px 24px; display:flex; flex-direction:column; gap:10px;}
.btn{width:100%; display:inline-flex; align-items:center; justify-content:center; gap:9px; border:1.3px solid var(--line); background:var(--accent); color:var(--accent-ink); padding:14px 20px; font-family:"IBM Plex Mono",monospace; font-size:13px; font-weight:500; text-decoration:none; transition:transform .12s, letter-spacing .2s, filter .2s, box-shadow .18s;}
.btn:hover{letter-spacing:.04em; filter:brightness(1.06);}
.btn:active{transform:translate(2px,2px);}
.btn.ghost{background:var(--paper); color:var(--ink);}
.btn.ghost:hover{border-color:var(--accent); color:var(--accent);}
.btn[disabled]{opacity:.55; pointer-events:none;}

.status-note{font-family:"IBM Plex Mono",monospace; font-size:11px; color:var(--ink-soft); text-align:center; margin-top:4px; line-height:1.5;}

@media(max-width:560px){
  .bar{padding:16px 5vw;}
  .hero{padding:4vh 5vw 3vh;}
  main{padding:0 5vw 8vh;}
  .card-head,.row-item,.steps-box,.key-wrap,.notice,.foot{padding-left:18px; padding-right:18px;}
  .row-item{grid-template-columns:1fr; gap:4px;}
}
</style>
</head>
<body>
<div id="cur"></div>
<div id="curdot"></div>

<header class="bar">
  <a class="wordmark" href="getkey.php"><b>KEY</b>.SYS — CLAIM PORTAL</a>
  <a class="nav-link" href="getkey.php">← Trang GetKey</a>
</header>

<section class="hero">
  <p class="eyebrow">
    <?php if ($pageMode === 'success'): ?>
      XÁC THỰC HOÀN TẤT · ĐÃ CẤP KEY
    <?php elseif ($pageMode === 'layma'): ?>
      TIẾN TRÌNH VƯỢT LINK · BƯỚC <?= (int)$stepNo ?> / <?= (int)$stepTotal ?>
    <?php else: ?>
      TRẠNG THÁI YÊU CẦU · CHƯA HOÀN TẤT
    <?php endif; ?>
  </p>
  <h1><span id="typedHead"><?= htmlspecialchars($headlineText) ?></span><span class="caret"></span></h1>
  <p class="sub">
    <?php if ($pageMode === 'success'): ?>
      Key miễn phí của bạn đã được khởi tạo thành công. Sao chép và dán vào ứng dụng để kích hoạt.
    <?php elseif ($pageMode === 'layma'): ?>
      Bạn đã vượt xong bước trước đó. Hoàn tất lượt vượt link Layma.net bên dưới để nhận key.
    <?php else: ?>
      Phiên nhận key hiện tại không thể hoàn tất. Xem chi tiết bên dưới và tạo yêu cầu mới trên trang GetKey.
    <?php endif; ?>
  </p>
</section>

<main>
  <?php if ($pageMode === 'success'): ?>
    <div class="card">
      <div class="card-head">
        <h2>Thông tin Key</h2>
        <span class="badge good">ACTIVE · HỢP LỆ</span>
      </div>

      <div class="key-wrap">
        <div class="key-label">Mã Key của bạn</div>
        <div class="key-box">
          <span id="keyOut"><?= htmlspecialchars((string)$keyText) ?></span>
        </div>
      </div>

      <div class="row-item">
        <span class="lbl">Ứng dụng</span>
        <span class="val"><?= htmlspecialchars($gameLabel) ?></span>
      </div>
      <div class="row-item">
        <span class="lbl">Gói thời hạn</span>
        <span class="val"><?= htmlspecialchars($durationLabel) ?> (<?= htmlspecialchars($remainingLabel) ?>)</span>
      </div>
      <div class="row-item">
        <span class="lbl">Hết hạn vào</span>
        <span class="val"><?= htmlspecialchars($expiresLabel) ?></span>
      </div>
      <div class="row-item">
        <span class="lbl">Giới hạn thiết bị</span>
        <span class="val">1 thiết bị (gắn HWID lần đăng nhập đầu)</span>
      </div>

      <div class="foot">
        <button type="button" class="btn copy-btn" id="copyBtn">SAO CHÉP KEY</button>
        <a class="btn ghost" href="getkey.php">← QUAY LẠI TRANG GETKEY</a>
      </div>
    </div>

  <?php elseif ($pageMode === 'layma'): ?>
    <div class="card">
      <div class="card-head">
        <h2>Bước <?= (int)$stepNo ?> / <?= (int)$stepTotal ?> — Vượt link Layma.net</h2>
        <span class="badge">CÒN <?= (int)$laymaRemaining ?> LƯỢT</span>
      </div>

      <div class="steps-box">
        <div class="step-line done">
          <span>01 · Link4m.net</span>
          <span class="step-tag">Đã hoàn tất ✓</span>
        </div>
        <?php for ($i = 1; $i <= $laymaTotal; $i++):
            $currentLaymaIndex = $laymaTotal - $laymaRemaining + 1;
            $isDone = $i < $currentLaymaIndex;
            $isActive = $i === $currentLaymaIndex;
        ?>
        <div class="step-line <?= $isDone ? 'done' : ($isActive ? 'active' : '') ?>">
          <span>0<?= $i + 1 ?> · Layma.net (Lượt <?= $i ?>/<?= $laymaTotal ?>)</span>
          <span class="step-tag"><?= $isDone ? 'Đã hoàn tất ✓' : ($isActive ? 'Đang thực hiện' : 'Chờ') ?></span>
        </div>
        <?php endfor; ?>
      </div>

      <div class="row-item">
        <span class="lbl">Ứng dụng</span>
        <span class="val"><?= htmlspecialchars($gameLabel) ?></span>
      </div>
      <div class="row-item">
        <span class="lbl">Gói đã chọn</span>
        <span class="val"><?= htmlspecialchars($durationLabel) ?></span>
      </div>

      <div class="notice" id="laymaStatus">
        Bấm nút bên dưới để bắt đầu lượt vượt link Layma.net (tối thiểu <?= (int)KEYGEN_MIN_LAYMA_SECONDS ?> giây). Sau khi giải xong link rút gọn, bạn sẽ tự động được chuyển về trang này để nhận key.
      </div>

      <div class="foot">
        <button type="button" class="btn" id="startLaymaBtn">BẮT ĐẦU VƯỢT LINK LAYMA (BƯỚC <?= (int)$stepNo ?>/<?= (int)$stepTotal ?>) →</button>
        <a class="btn ghost" href="getkey.php">HỦY &amp; TẠO YÊU CẦU MỚI</a>
        <div class="status-note">Lưu ý: Không đóng trình duyệt hoặc xóa cookie trong lúc vượt link.</div>
      </div>
    </div>

  <?php else: ?>
    <div class="card">
      <div class="card-head">
        <h2>Không thể cấp Key</h2>
        <span class="badge">CHƯA HOÀN TẤT</span>
      </div>

      <div class="notice warn">
        ⚠ <?= htmlspecialchars($blockedMsg) ?>
      </div>

      <?php if ($gameLabel !== 'Unknown' && $gameLabel !== ''): ?>
      <div class="row-item">
        <span class="lbl">Ứng dụng</span>
        <span class="val"><?= htmlspecialchars($gameLabel) ?></span>
      </div>
      <?php endif; ?>
      <?php if ($durationLabel !== ''): ?>
      <div class="row-item">
        <span class="lbl">Gói thời hạn</span>
        <span class="val"><?= htmlspecialchars($durationLabel) ?></span>
      </div>
      <?php endif; ?>

      <div class="foot">
        <a class="btn" href="getkey.php">← QUAY LẠI TRANG GETKEY &amp; THỬ LẠI</a>
      </div>
    </div>
  <?php endif; ?>
</main>

<script>
(function(){
  // Con trỏ chuột custom + hiệu ứng ripple
  const cur = document.getElementById('cur');
  const dot = document.getElementById('curdot');
  if (cur && dot && window.matchMedia('(pointer:fine)').matches) {
    window.addEventListener('mousemove', (e) => {
      cur.style.left = e.clientX + 'px';
      cur.style.top = e.clientY + 'px';
      dot.style.left = e.clientX + 'px';
      dot.style.top = e.clientY + 'px';
    });
    document.querySelectorAll('a, button').forEach((el) => {
      el.addEventListener('mouseenter', () => { cur.classList.add('hover'); dot.classList.add('hover'); });
      el.addEventListener('mouseleave', () => { cur.classList.remove('hover'); dot.classList.remove('hover'); });
    });
  }
  window.addEventListener('click', (e) => {
    const r = document.createElement('div');
    r.className = 'ripple';
    r.style.left = e.clientX + 'px';
    r.style.top = e.clientY + 'px';
    document.body.appendChild(r);
    setTimeout(() => r.remove(), 600);
  });

  // Nút copy key
  const copyBtn = document.getElementById('copyBtn');
  const keyOut = document.getElementById('keyOut');
  if (copyBtn && keyOut) {
    copyBtn.addEventListener('click', async () => {
      const text = keyOut.textContent.trim();
      try {
        await navigator.clipboard.writeText(text);
        copyBtn.textContent = 'ĐÃ SAO CHÉP KEY ✓';
      } catch (_) {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
        copyBtn.textContent = 'ĐÃ SAO CHÉP KEY ✓';
      }
      setTimeout(() => { copyBtn.textContent = 'SAO CHÉP KEY'; }, 2200);
    });
  }

  // Nút bắt đầu lượt Layma: gọi getkey_action.php (action=begin_layma) xong chuyển sang URL Layma động
  const startLaymaBtn = document.getElementById('startLaymaBtn');
  const laymaStatus = document.getElementById('laymaStatus');
  const laymaUrl = <?= $laymaUrlJson ?>;
  if (startLaymaBtn) {
    startLaymaBtn.addEventListener('click', async () => {
      startLaymaBtn.disabled = true;
      startLaymaBtn.textContent = 'ĐANG KHỞI TẠO LƯỢT LAYMA...';
      try {
        const res = await fetch('getkey_action.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
          body: 'action=begin_layma',
          credentials: 'same-origin'
        });
        const data = await res.json();
        if (data && data.status === 'success') {
          window.location.href = laymaUrl;
          return;
        }
        if (laymaStatus) {
          laymaStatus.classList.add('warn');
          laymaStatus.textContent = (data && data.message) ? data.message : 'Không thể bắt đầu bước Layma. Vui lòng thử lại!';
        }
      } catch (err) {
        if (laymaStatus) {
          laymaStatus.classList.add('warn');
          laymaStatus.textContent = 'Lỗi kết nối máy chủ. Vui lòng thử lại!';
        }
      }
      startLaymaBtn.disabled = false;
      startLaymaBtn.textContent = 'BẮT ĐẦU VƯỢT LINK LAYMA →';
    });
  }
})();
</script>
</body>
</html>
