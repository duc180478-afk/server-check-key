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
<meta name="application-version" content="KEYSYS-UI-PREVIEW-MATCH-2026-10-15">
<!-- KEYSYS_UI_BUILD: preview-match-2026-10-15 -->
<title><?= $pageMode === 'success' ? 'Key của bạn — KEY.SYS' : ($pageMode === 'layma' ? 'Tiếp tục vượt link — KEY.SYS' : 'Yêu cầu chưa hoàn tất — KEY.SYS') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,wght@0,400;0,500;0,600;1,400&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{--paper:#F6F4EE;--card:#FCFBF7;--ink:#191B17;--soft:#4C4A42;--grid:#DEDAD0;--accent:#2B4FD8;--accent-ink:#F6F4EE;--amber:#C4780A;--line:#191B17;--good:#23734D;--bad:#A83B30;color-scheme:light}
@media(prefers-color-scheme:dark){:root:not([data-theme="light"]){--paper:#15160F;--card:#1C1D15;--ink:#EDEAE0;--soft:#A6A295;--grid:#2B2C22;--accent:#7D95F5;--accent-ink:#15160F;--amber:#E0A03E;--line:#EDEAE0;--good:#79C79A;--bad:#EF8D82;color-scheme:dark}}
*{box-sizing:border-box}body{margin:0;min-height:100vh;background:var(--paper);color:var(--ink);font-family:"Newsreader",serif;background-image:linear-gradient(var(--grid) 1px,transparent 1px),linear-gradient(90deg,var(--grid) 1px,transparent 1px);background-size:28px 28px;cursor:auto}@media(pointer:coarse){body{cursor:auto}#cur,#curdot{display:none}}a,button{font:inherit}#cur{position:fixed;left:50%;top:50%;width:36px;height:36px;border:2.5px solid var(--line);border-radius:50%;pointer-events:none;z-index:999;transform:translate(-50%,-50%);transition:width .22s,height .22s,background .22s,border-color .22s;will-change:transform}#curdot{position:fixed;width:7px;height:7px;background:var(--accent);border-radius:50%;pointer-events:none;z-index:999;transform:translate(-50%,-50%);transition:transform .15s}#cur.hover{width:58px;height:58px;background:color-mix(in srgb,var(--accent) 22%,transparent);border-color:var(--accent)}#curdot.hover{transform:translate(-50%,-50%) scale(0)}.ripple{position:fixed;border:1.3px solid var(--line);border-radius:50%;pointer-events:none;z-index:998;transform:translate(-50%,-50%);animation:rip .6s ease-out forwards}@keyframes rip{to{width:90px;height:90px;opacity:0}}.caret{display:inline-block;width:.07em;background:var(--accent);margin-left:3px;animation:blink 1s steps(1) infinite}@keyframes blink{50%{opacity:0}}button:focus-visible,a:focus-visible{outline:2px solid var(--accent);outline-offset:3px}
.bar{display:flex;justify-content:space-between;align-items:center;padding:18px 6vw;border-bottom:1.3px solid var(--line)}.wordmark{font:13px "IBM Plex Mono",monospace;letter-spacing:.04em}.wordmark b{color:var(--accent)}.node{font:10px/1.6 "IBM Plex Mono",monospace;color:var(--soft);text-align:right}.node i{display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--good);margin-right:6px}.hero{padding:8vh 6vw 5vh;border-bottom:1.3px solid var(--line)}.eyebrow{font:11px "IBM Plex Mono",monospace;color:var(--soft);letter-spacing:.07em;margin:0 0 13px}.hero h1{font-size:clamp(42px,7vw,76px);line-height:1.02;letter-spacing:-.02em;font-weight:500;margin:0}.hero-sub{max-width:50ch;color:var(--soft);font-size:17px;line-height:1.55;margin:17px 0 0}
main{max-width:760px;margin:0 auto;padding:0 6vw 8vh}.statusline{display:flex;justify-content:space-between;align-items:center;gap:12px;margin:24px 0 2px;font:10px "IBM Plex Mono",monospace;color:var(--soft);letter-spacing:.035em}.statusleft{display:flex;align-items:center;gap:9px}.statusmark{width:25px;height:25px;border:1.3px solid var(--good);border-radius:50%;display:grid;place-items:center;color:var(--good);font-size:14px;flex:0 0 auto}.statusmark.warn{border-color:var(--amber);color:var(--amber)}.statuspill{border:1px solid var(--good);color:var(--good);padding:5px 8px;white-space:nowrap}.statuspill.warn{border-color:var(--amber);color:var(--amber)}.card{margin-top:18px;background:var(--card);border:1.3px solid var(--line);box-shadow:7px 7px 0 var(--line);animation:cardIn .45s ease both}@keyframes cardIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}.card-head{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:18px 23px;border-bottom:1.3px solid var(--line)}.card-head h2{font-size:21px;font-weight:500;margin:0}.ref{font:10px "IBM Plex Mono",monospace;color:var(--soft);text-align:right}.key-area{padding:23px;border-bottom:1px solid var(--grid)}.key-label{font:10px "IBM Plex Mono",monospace;letter-spacing:.08em;color:var(--soft);margin-bottom:10px}.keybox{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:15px 16px;background:var(--paper);border:1.3px dashed var(--accent);min-height:66px}.keytext{font:clamp(14px,2.3vw,19px) "IBM Plex Mono",monospace;letter-spacing:.025em;overflow-wrap:anywhere;user-select:all}.copy-btn{flex:0 0 auto;display:flex;align-items:center;gap:7px;padding:9px 11px;border:1.3px solid var(--line);background:var(--card);color:var(--ink);font:10px "IBM Plex Mono",monospace;cursor:auto;transition:background .2s,color .2s,transform .15s}.copy-btn:hover{background:var(--accent);color:var(--accent-ink)}.copy-btn:active{transform:translate(2px,2px)}.copy-btn svg{width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:1.7}.meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1px;background:var(--line);border-bottom:1.3px solid var(--line)}.meta-item{background:var(--card);padding:16px 21px;min-width:0}.meta-label{display:block;font:10px "IBM Plex Mono",monospace;color:var(--soft);margin-bottom:8px;letter-spacing:.035em}.meta-value{font-size:18px;overflow-wrap:anywhere}.meta-value.mono{font:12px/1.5 "IBM Plex Mono",monospace}.meta-value.app{color:var(--accent);font-weight:600}.state{display:inline-flex;align-items:center;gap:7px}.state:before{content:"";width:7px;height:7px;border-radius:50%;background:var(--good);display:inline-block}.card-foot{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:19px 23px}.footnote{font:10px/1.7 "IBM Plex Mono",monospace;color:var(--soft);max-width:34ch}.primary{position:relative;isolation:isolate;overflow:hidden;display:inline-flex;align-items:center;justify-content:center;gap:9px;background:var(--accent);color:var(--accent-ink);border:1.3px solid var(--line);padding:13px 17px;font:11px "IBM Plex Mono",monospace;text-decoration:none;cursor:auto;transition:transform .15s,box-shadow .2s}.primary:before{content:"";position:absolute;inset:-20% auto -20% -45%;width:32%;background:rgba(255,255,255,.25);transform:skewX(-22deg);animation:sweep 2.7s ease-in-out infinite;z-index:-1}@keyframes sweep{0%,32%{left:-45%}70%,100%{left:125%}}.primary:hover{box-shadow:4px 4px 0 var(--line);transform:translate(-2px,-2px)}.primary:active{transform:translate(2px,2px)}.primary:disabled{opacity:.55;cursor:wait}.primary svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:1.8}.progress{padding:22px 23px 8px}.progress-top{display:flex;justify-content:space-between;gap:12px;font:10px "IBM Plex Mono",monospace;color:var(--soft);margin-bottom:10px}.progress-track{display:flex;gap:5px}.progress-track span{height:4px;flex:1;background:var(--grid)}.progress-track span.done{background:var(--accent)}.instructions{padding:17px 23px 22px}.instructions h3{font-size:25px;font-weight:500;margin:0 0 10px}.instructions p{font-size:15px;line-height:1.6;color:var(--soft);margin:6px 0}.instructions .small{font:10px/1.7 "IBM Plex Mono",monospace;color:var(--amber);margin-top:15px}.errorbox{padding:24px;text-align:center}.error-symbol{width:48px;height:48px;border:1.3px solid var(--amber);color:var(--amber);border-radius:50%;display:grid;place-items:center;font-size:23px;margin:0 auto 15px}.errorbox h2{font-size:25px;font-weight:500;margin:0 0 12px}.errorbox p{font-size:16px;line-height:1.6;color:var(--soft);margin:0 auto 19px;max-width:48ch}.bottom{display:flex;justify-content:space-between;align-items:center;gap:15px;margin-top:27px}.back{color:var(--ink);font:11px "IBM Plex Mono",monospace;text-decoration:none;border-bottom:1px solid var(--ink);padding-bottom:4px}.back:hover{color:var(--accent);border-color:var(--accent)}.security{font:9px/1.7 "IBM Plex Mono",monospace;color:var(--soft);text-align:right}.footer{margin:31px 0 0;text-align:center;font:10px/1.7 "IBM Plex Mono",monospace;color:var(--soft)}.footer strong{color:var(--ink);font-weight:500}.toast{position:fixed;left:50%;bottom:24px;transform:translate(-50%,18px);opacity:0;pointer-events:none;background:var(--ink);color:var(--paper);padding:12px 16px;font:11px "IBM Plex Mono",monospace;z-index:1000;box-shadow:4px 4px 0 var(--accent);transition:opacity .2s,transform .2s;max-width:calc(100vw - 30px);text-align:center}.toast.show{opacity:1;transform:translate(-50%,0)}
@media(max-width:560px){.bar{padding:15px 20px}.node{font-size:8px}.hero{padding:6vh 20px 4vh}.hero h1{font-size:46px}.hero-sub{font-size:16px}main{padding:0 17px 38px}.statusline{font-size:8px}.card-head,.key-area,.card-foot{padding-left:16px;padding-right:16px}.card-head h2{font-size:19px}.ref{font-size:8px}.keybox{align-items:stretch;flex-direction:column}.copy-btn{justify-content:center}.meta-item{padding:14px}.meta-label{font-size:9px}.meta-value{font-size:16px}.meta-value.mono{font-size:11px}.card-foot{align-items:stretch;flex-direction:column}.primary{width:100%}.bottom{align-items:flex-start;flex-direction:column}.security{text-align:left}.progress,.instructions{padding-left:16px;padding-right:16px}}

/* Responsive cursor + tactile feedback */
#curpointer{position:fixed;left:0;top:0;width:28px;height:34px;z-index:1002;pointer-events:none;transform-origin:3px 2px;transition:opacity .15s ease,transform .16s ease,filter .16s ease;will-change:left,top;display:none;opacity:0}
#cur,#curdot{opacity:0}
html.has-cursor-position #cur,html.has-cursor-position #curdot,html.has-cursor-position #curpointer{opacity:1}
#curpointer svg{display:block;width:100%;height:100%;overflow:visible}
#curpointer .pointer-shape{fill:var(--accent);stroke:var(--line);stroke-width:1.65;stroke-linejoin:round}
#curpointer .pointer-glint{fill:none;stroke:var(--accent-ink);stroke-width:1.25;stroke-linecap:round;stroke-linejoin:round;opacity:.95}
#curpointer .pointer-notch{fill:none;stroke:var(--line);stroke-width:1.15;stroke-linecap:round;opacity:.8}
#cur{z-index:998}
#curdot{z-index:999}
#curdot.hover{transform:translate(-50%,-50%) scale(.82)}
html.cursor-pointer-hover #curpointer{transform:scale(1.08) rotate(-4deg);filter:drop-shadow(2px 2px 0 color-mix(in srgb,var(--accent) 28%,transparent))}
@media(pointer:fine) and (hover:hover){
  html.has-cursor-position body,html.has-cursor-position button,html.has-cursor-position a,html.has-cursor-position .dd-btn,html.has-cursor-position .dd-list button,html.has-cursor-position .burger,html.has-cursor-position .gen,html.has-cursor-position .primary,html.has-cursor-position .modal-close,html.has-cursor-position .unlock,html.has-cursor-position .copy-btn{cursor:none!important}
  #cur,#curdot,#curpointer{display:block}
}
@media(pointer:coarse),(hover:none){
  body,button,a,.dd-btn,.dd-list button,.burger,.gen,.primary,.modal-close,.unlock,.copy-btn{cursor:auto!important}
  #cur,#curdot,#curpointer{display:none!important}
  .ripple{border-color:var(--accent);box-shadow:0 0 0 3px color-mix(in srgb,var(--accent) 13%,transparent)}
  .gen:before,.primary:before,.unlock:before{animation:none!important;opacity:0}
  .card::after,.keybox::after{animation:none!important;opacity:0}
}
@keyframes panelScan{
  0%{transform:translateX(0) skewX(-18deg);opacity:0}
  16%{opacity:.52}
  54%{opacity:.45}
  76%,100%{transform:translateX(650%) skewX(-18deg);opacity:0}
}
@keyframes tapSweep{
  0%{transform:translateX(0) skewX(-22deg);opacity:0}
  18%{opacity:.62}
  100%{transform:translateX(520%) skewX(-22deg);opacity:0}
}
@keyframes tapRipple{
  from{width:0;height:0;opacity:.6}
  to{width:116px;height:116px;opacity:0}
}
/* Mobile still gets responsive touch feedback without a permanent cursor RAF loop */
.touch-pressed{filter:saturate(1.12)}
button.touch-pressed,a.touch-pressed,.gen.touch-pressed,.primary.touch-pressed,.unlock.touch-pressed,.copy-btn.touch-pressed,.dd-btn.touch-pressed,.burger.touch-pressed{
  transform:translate(2px,2px)!important;box-shadow:1px 1px 0 var(--line)!important;
}
.card.touch-pressed,.keybox.touch-pressed,.instructions.touch-pressed,.meta-item.touch-pressed{
  transform:translate(2px,2px)!important;box-shadow:2px 2px 0 var(--line)!important;
}
.touch-flash::after{animation:tapSweep .8s ease-out both!important}
.gen.touch-flash::before,.primary.touch-flash::before,.unlock.touch-flash::before,.copy-btn.touch-flash::before,.dd-btn.touch-flash::before{
  opacity:1!important;animation:tapSweep .7s ease-out both!important
}
.copy-btn{position:relative;isolation:isolate;overflow:hidden}
.copy-btn::before{content:"";position:absolute;inset:-20% auto -20% -40%;width:30%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.3),transparent);transform:skewX(-22deg);opacity:0;pointer-events:none;z-index:1}
.copy-btn>*{position:relative;z-index:2}
.copy-btn:hover::before{opacity:1;animation:tapSweep .9s ease-out both}
.copy-btn:hover{box-shadow:4px 4px 0 var(--line);transform:translate(-2px,-2px)}
.copy-btn:active{transform:translate(2px,2px);box-shadow:none}
.ripple{animation:tapRipple .68s ease-out forwards}
@media(pointer:coarse),(hover:none){
  .gen.touch-flash:before,.primary.touch-flash:before,.unlock.touch-flash:before{display:block}
}

/* Claim panels: low-key scan line and tactile depth to match GetKey. */
.claim-page .card{position:relative;isolation:isolate;overflow:hidden;transition:transform .18s,box-shadow .18s}
.claim-page .card::after,.claim-page .keybox::after{content:"";position:absolute;top:-35%;bottom:-35%;left:-32%;width:24%;background:linear-gradient(90deg,transparent,color-mix(in srgb,var(--accent) 11%,transparent),rgba(255,255,255,.38),color-mix(in srgb,var(--accent) 9%,transparent),transparent);transform:translateX(0) skewX(-18deg);opacity:0;pointer-events:none;z-index:1}
.claim-page .card::after{animation:panelScan 5.8s ease-in-out infinite;opacity:.65}
.claim-page .keybox{position:relative;isolation:isolate;overflow:hidden;transition:transform .16s,box-shadow .16s}
.claim-page .keybox::after{animation:panelScan 4.6s ease-in-out infinite;opacity:.75}
.claim-page .card>*{position:relative;z-index:2}
.claim-page .keybox>*{position:relative;z-index:2}
.claim-page .card:hover{transform:translate(1px,1px);box-shadow:5px 5px 0 var(--line)}
.claim-page .card:active{transform:translate(2px,2px);box-shadow:2px 2px 0 var(--line)}
.claim-page .keybox:hover{transform:translate(2px,2px);box-shadow:2px 2px 0 var(--grid)}
.claim-page .keybox:active{transform:translate(3px,3px);box-shadow:0 0 0 transparent}
.claim-page .instructions .primary{box-shadow:0 0 0 transparent}
.claim-page .instructions .primary:hover{transform:translate(-2px,-2px);box-shadow:4px 4px 0 var(--line)}
.claim-page .instructions .primary:active{transform:translate(2px,2px);box-shadow:none}
@media(pointer:coarse),(hover:none){
  .claim-page .card::after,.claim-page .keybox::after{animation:none!important;opacity:0!important}
  .claim-page .card.touch-flash::after{animation:tapSweep .82s ease-out both!important;opacity:1!important}
  .claim-page .keybox.touch-flash::after{animation:tapSweep .72s ease-out both!important;opacity:1!important}
  .claim-page .card.touch-pressed,.claim-page .keybox.touch-pressed{box-shadow:2px 2px 0 var(--line)!important;transform:translate(2px,2px)!important}
}

@media(prefers-reduced-motion:reduce){
  #cur,#curdot,#curpointer{transition:none!important}
  .card::after,.keybox::after,.copy-btn::before{animation:none!important}
}

@media(prefers-reduced-motion:reduce){*,*:before,*:after{animation-duration:.01ms!important;animation-iteration-count:1!important;scroll-behavior:auto!important;transition-duration:.01ms!important}}

/* Preview-match robustness: preserve native cursor if custom cursor JS is unavailable. */
.claim-page .keybox,.claim-page .card{isolation:isolate}
.claim-page .instructions .primary,.claim-page .copy-btn{touch-action:manipulation}
.claim-page .primary,.claim-page .copy-btn{position:relative;isolation:isolate;overflow:hidden}
.claim-page .primary:focus-visible,.claim-page .copy-btn:focus-visible{outline:2px solid var(--accent);outline-offset:3px}
@media(prefers-reduced-motion:reduce){.claim-page .primary:before,.claim-page .copy-btn:before{animation:none!important}}

</style>
</head>
<body class="claim-page">
<div id="cur" aria-hidden="true"></div>
<div id="curdot" aria-hidden="true"></div>
<div id="curpointer" aria-hidden="true">
  <svg viewBox="0 0 28 34" xmlns="http://www.w3.org/2000/svg" focusable="false">
    <path class="pointer-shape" d="M3 2.4 3 25.7 9.6 19.2 15 31.2 19.7 29.1 14.3 17.4 24.2 17.4Z"/>
    <path class="pointer-glint" d="M6.2 7.1 6.2 18.3 9.3 15.3"/>
    <path class="pointer-notch" d="M14.3 17.4 19.7 29.1"/>
  </svg>
</div>
<header class="bar"><div class="wordmark"><b>KEY</b>.SYS — CLAIM PORTAL</div><div class="node"><i></i>NODE VN-01<br>LICENSE DELIVERY</div></header>
<section class="hero">
  <p class="eyebrow">CLAIM / LICENSE DELIVERY</p>
  <h1 id="headline"></h1>
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
    <div class="key-area"><div class="key-label">YOUR LICENSE KEY</div><div class="keybox"><span class="keytext" id="keyText"><?= htmlspecialchars($keyText, ENT_QUOTES, 'UTF-8') ?></span><button class="copy-btn" id="copyBtn" type="button"><svg viewBox="0 0 24 24"><rect x="8" y="8" width="12" height="12" rx="1"></rect><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"></path></svg><span id="copyLabel">COPY KEY</span></button></div></div>
    <div class="meta">
      <div class="meta-item"><span class="meta-label">ỨNG DỤNG / APP</span><span class="meta-value app"><?= htmlspecialchars($gameLabel, ENT_QUOTES, 'UTF-8') ?></span></div>
      <div class="meta-item"><span class="meta-label">THỜI HẠN</span><span class="meta-value mono"><?= htmlspecialchars($durationLabel, ENT_QUOTES, 'UTF-8') ?></span></div>
      <div class="meta-item"><span class="meta-label">NGÀY HẾT HẠN</span><span class="meta-value mono"><?= htmlspecialchars($expiresLabel, ENT_QUOTES, 'UTF-8') ?></span></div>
      <div class="meta-item"><span class="meta-label">THỜI GIAN CÒN LẠI</span><span class="meta-value"><?= htmlspecialchars($remainingLabel, ENT_QUOTES, 'UTF-8') ?></span></div>
      <div class="meta-item" style="grid-column:1/-1"><span class="meta-label">TRẠNG THÁI / STATUS</span><span class="meta-value state"><?= ($keyInfo && ($keyInfo['status'] ?? '') === 'active') ? 'Hoạt động' : 'Đang kiểm tra' ?></span></div>
    </div>
    <div class="card-foot"><span class="footnote">KEY ĐƯỢC GẮN VỚI 1 THIẾT BỊ.<br>Không chia sẻ license với người khác.</span><button class="primary" id="copyPrimary" type="button"><svg viewBox="0 0 24 24"><rect x="8" y="8" width="12" height="12" rx="1"></rect><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"></path></svg>Sao chép key</button></div>
  </section>
<?php elseif ($pageMode === 'layma'): ?>
  <div class="statusline"><div class="statusleft"><span class="statusmark">↗</span><span>LINK VERIFICATION / STEP <?= (int)$stepNo ?> OF <?= (int)$stepTotal ?></span></div><span class="statuspill">IN PROGRESS</span></div>
  <section class="card" aria-label="Bước tiếp theo">
    <div class="card-head"><h2>Tiếp tục xác minh</h2><span class="ref">LAYMA.NET / <?= (int)$laymaRemaining ?> LEFT</span></div>
    <div class="progress"><div class="progress-top"><span>TIẾN TRÌNH VƯỢT LINK</span><span><?= (int)$stepNo ?>/<?= (int)$stepTotal ?></span></div><div class="progress-track"><?php for ($i = 1; $i <= $stepTotal; $i++): ?><span class="<?= $i < $stepNo ? 'done' : '' ?>"></span><?php endfor; ?></div></div>
    <div class="instructions"><h3>Đã hoàn tất Link4m</h3><p>Thời hạn đã chọn: <strong><?= htmlspecialchars($durationLabel, ENT_QUOTES, 'UTF-8') ?></strong>.</p><p>Bạn còn <strong><?= (int)$laymaRemaining ?> lượt Layma.net</strong>. Nhấn nút bên dưới để tiếp tục. Mỗi lượt Layma.net cần tối thiểu 45 giây trước khi quay lại. Khi trở về trang claim, hệ thống sẽ kiểm tra bước tiếp theo.</p><button class="primary" id="laymaBtn" type="button">Vượt Layma.net · Còn <?= (int)$laymaRemaining ?> lượt ↗</button><p class="small">Không cần tự nhập key. Key sẽ được cấp tự động sau khi hoàn tất đủ số lượt yêu cầu.</p></div>
  </section>
<?php else: ?>
  <div class="statusline"><div class="statusleft"><span class="statusmark warn">!</span><span>CLAIM NOT COMPLETED / ACTION REQUIRED</span></div><span class="statuspill warn">NOTICE</span></div>
  <section class="card"><div class="card-head"><h2>Không thể cấp key</h2><span class="ref">CLAIM / NOTICE</span></div><div class="errorbox"><div class="error-symbol">!</div><h2>Phiên chưa hoàn tất</h2><p><?= htmlspecialchars($blockedMsg, ENT_QUOTES, 'UTF-8') ?></p><a class="primary" href="getkey">← Quay lại GetKey</a></div></section>
<?php endif; ?>
  <div class="bottom"><a href="getkey" class="back">← LẤY KEY KHÁC</a><div class="security">SESSION TICKET · 1 DEVICE<br>KEY.SYS / VN-01</div></div>
  <p class="footer"><strong>KEY.SYS</strong> — Hệ thống cấp license tự động.<br>Không chia sẻ key hoặc vé xác minh với người khác.</p>
</main>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script>
// Custom cursor: only animate on devices with a real hover-capable pointer.
const cur=document.getElementById('cur'),dot=document.getElementById('curdot'),pointerEl=document.getElementById('curpointer');
const cursorMedia=window.matchMedia('(pointer:fine) and (hover:hover)');
const reducedMotionMedia=window.matchMedia('(prefers-reduced-motion: reduce)');
const touchMedia=window.matchMedia('(pointer:coarse), (hover:none)');
let mx=innerWidth/2,my=innerHeight/2,rx=mx,ry=my,cursorRaf=0,cursorRunning=false;
function cursorLoop(){if(!cursorRunning)return;rx+=(mx-rx)*.22;ry+=(my-ry)*.22;cur.style.left=rx+'px';cur.style.top=ry+'px';cursorRaf=requestAnimationFrame(cursorLoop)}
function startCursorLoop(){if(!cursorMedia.matches||reducedMotionMedia.matches||document.hidden||cursorRunning||!document.documentElement.classList.contains('has-cursor-position'))return;cursorRunning=true;cursorLoop()}
function stopCursorLoop(){cursorRunning=false;if(cursorRaf)cancelAnimationFrame(cursorRaf);cursorRaf=0}
function syncCursorMode(){if(cursorMedia.matches){document.documentElement.classList.add('has-custom-cursor')}else{document.documentElement.classList.remove('has-custom-cursor','cursor-pointer-hover','has-cursor-position');stopCursorLoop()}}
window.addEventListener('mousemove',e=>{if(!cursorMedia.matches)return;mx=e.clientX;my=e.clientY;document.documentElement.classList.add('has-cursor-position');dot.style.left=mx+'px';dot.style.top=my+'px';pointerEl.style.left=(mx-3)+'px';pointerEl.style.top=(my-2)+'px';if(reducedMotionMedia.matches){rx=mx;ry=my;cur.style.left=mx+'px';cur.style.top=my+'px'}else startCursorLoop()},{passive:true});
document.addEventListener('mouseleave',()=>{document.documentElement.classList.remove('has-cursor-position','cursor-pointer-hover');cur.classList.remove('hover');dot.classList.remove('hover');stopCursorLoop()});
document.addEventListener('visibilitychange',()=>document.hidden?stopCursorLoop():startCursorLoop());
if(cursorMedia.addEventListener)cursorMedia.addEventListener('change',syncCursorMode);else if(cursorMedia.addListener)cursorMedia.addListener(syncCursorMode);
if(reducedMotionMedia.addEventListener)reducedMotionMedia.addEventListener('change',()=>{if(reducedMotionMedia.matches)stopCursorLoop();else startCursorLoop()});
syncCursorMode();
function bindHover(el){if(!el||el.dataset.hoverBound)return;el.dataset.hoverBound='1';el.addEventListener('mouseenter',()=>{if(!cursorMedia.matches)return;cur.classList.add('hover');dot.classList.add('hover');document.documentElement.classList.add('cursor-pointer-hover')});el.addEventListener('mouseleave',()=>{cur.classList.remove('hover');dot.classList.remove('hover');document.documentElement.classList.remove('cursor-pointer-hover')})}
document.querySelectorAll('button,a,.dd-list button').forEach(bindHover);
// Click/tap ripples work on desktop and touchscreens.
document.addEventListener('click',e=>{const r=document.createElement('span');r.className='ripple';r.style.left=e.clientX+'px';r.style.top=e.clientY+'px';r.style.width='0';r.style.height='0';document.body.appendChild(r);setTimeout(()=>r.remove(),720)},{passive:true});
// Mobile gets short scan/press feedback on interaction, not an always-running cursor animation.
function timedClass(el,name,duration){if(!el)return;el.classList.add(name);const key='_'+name+'Timer';if(el[key])clearTimeout(el[key]);el[key]=setTimeout(()=>el.classList.remove(name),duration)}
document.addEventListener('pointerdown',e=>{if(!(touchMedia.matches||e.pointerType==='touch'||e.pointerType==='pen'))return;const target=e.target.closest('button,a,.card,.keybox,.instructions,.meta-item,.dd-btn');if(!target)return;timedClass(target,'touch-flash',900);if(target.matches('button,a,.card,.keybox,.instructions,.meta-item'))timedClass(target,'touch-pressed',230);const card=target.closest('.card');if(card)timedClass(card,'touch-flash',900);const keybox=target.closest('.keybox');if(keybox)timedClass(keybox,'touch-flash',900)},{passive:true});
const title=document.getElementById('headline');const titleText=<?= json_encode($headlineText, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;let i=0,del=false;(function typeTitle(){title.innerHTML=titleText.slice(0,i)+'<span class="caret">&nbsp;</span>';let wait=del?27:38;if(!del&&i===titleText.length){wait=900;del=true}else if(del&&i===0){wait=280;del=false}else{i+=del?-1:1}setTimeout(typeTitle,wait)})();
function toast(msg){const el=document.getElementById('toast');el.textContent=msg;el.classList.add('show');clearTimeout(window.toastTimer);window.toastTimer=setTimeout(()=>el.classList.remove('show'),2600)}
async function copyKey(){const key=document.getElementById('keyText')?.textContent.trim();if(!key)return;const done=()=>{const l=document.getElementById('copyLabel');if(l){l.textContent='COPIED!';setTimeout(()=>l.textContent='COPY KEY',2300)}toast('Đã sao chép key vào clipboard')};try{if(navigator.clipboard&&navigator.clipboard.writeText){await navigator.clipboard.writeText(key);done();return}}catch(e){}const ta=document.createElement('textarea');ta.value=key;ta.style.position='fixed';ta.style.opacity='0';document.body.appendChild(ta);ta.select();let ok=false;try{ok=document.execCommand('copy')}catch(e){}ta.remove();if(ok)done();else toast('Không thể tự sao chép. Hãy chọn key và sao chép thủ công.')}
document.getElementById('copyBtn')?.addEventListener('click',copyKey);document.getElementById('copyPrimary')?.addEventListener('click',copyKey);
const laymaBtn=document.getElementById('laymaBtn');const laymaUrl=<?= $laymaUrlJson ?>;
laymaBtn?.addEventListener('click',async()=>{laymaBtn.disabled=true;laymaBtn.textContent='Đang mở Layma.net…';try{const fd=new FormData();fd.set('action','begin_layma');const res=await fetch('getkey_action.php',{method:'POST',body:fd,credentials:'same-origin'});const data=await res.json();if(!res.ok||data.status!=='success'){toast(data.message||'Không thể bắt đầu lượt Layma.net.');laymaBtn.disabled=false;laymaBtn.textContent='Thử lại Layma.net ↗';return}window.location.assign(laymaUrl)}catch(e){toast('Lỗi kết nối. Vui lòng thử lại.');laymaBtn.disabled=false;laymaBtn.textContent='Thử lại Layma.net ↗'}});
</script>
</body>
</html>
