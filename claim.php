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
 $durationDays = ((int)$durationCfg['seconds']) / 86400;
 $keyText = createKey($appCfg['prefix'], $durationDays, 1, 'Free key - ' . $gameKey . ' - ' . $durationLabel, $gameKey);
 logClaim($keyText, $ip);
 $keyInfo = getKeyByKey($keyText);
 setClaimTicketStatus($activeTicket, 'completed');
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
 $ticket['stage'] = 'ready_to_issue';
 $ticket['step_token'] = '';
 if (!saveClaimTicket($ticket)) {
 $blockedMsg = 'Không thể xác nhận vé sau Link4m. Vui lòng thử lại từ GetKey!';
 } else {
 $issueKey($ticket);
 }
 }
 } elseif ($stage === 'ready_layma') {
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
#cur{position:fixed; width:46px; height:46px; border:2px solid var(--accent); border-radius:50%; pointer-events:none; z-index:999; transform:translate(-50%,-50%); opacity:0; transition:opacity .15s ease, background .2s ease;}
#curdot{position:fixed; width:18px; height:18px; background:var(--accent); border-radius:50%; pointer-events:none; z-index:999; transform:translate(-50%,-50%); opacity:0; transition:opacity .15s ease;}
#cur.hover{opacity:1; background:color-mix(in srgb, var(--accent) 18%, transparent);}
#curdot.hover{opacity:1;}
@media(pointer:coarse){ #cur,#curdot{display:none;} }
button, a, .copy-btn{ cursor:none; }
.card{position:relative; z-index:1; background:var(--card); border:1.3px solid var(--line); box-shadow:7px 7px 0 var(--line); margin:4vh auto; max-width:540px; padding:28px;}
.btn{display:block; width:100%; text-align:center; background:var(--accent); color:var(--accent-ink); border:1.3px solid var(--line); padding:14px; font-family:"IBM Plex Mono",monospace; font-size:14px; text-decoration:none; margin-top:20px; transition:filter .2s;}
.btn:hover{filter:brightness(1.1);}
.errbox{background:color-mix(in srgb, var(--amber) 12%, var(--card)); border:1.3px solid var(--amber); color:var(--amber); padding:16px; font-family:"IBM Plex Mono",monospace; font-size:12.5px; margin-bottom:20px; line-height:1.6;}
.okbox{background:color-mix(in srgb, var(--good) 12%, var(--card)); border:1.3px solid var(--good); color:var(--good); padding:16px; font-family:"IBM Plex Mono",monospace; font-size:12.5px; margin-bottom:20px; word-break:break-all;}
</style>
</head>
<body>
<div style="padding: 20px 6vw;">
 <div class="card">
 <h2 style="margin-top:0; font-weight:500; font-size:24px;"><?= htmlspecialchars($headlineText) ?></h2>
 <?php if ($pageMode === 'error'): ?>
 <div class="errbox">⚠️ <?= htmlspecialchars($blockedMsg) ?></div>
 <a class="btn" href="getkey.php">← Quay lại trang GetKey</a>
 <?php elseif ($pageMode === 'layma'): ?>
 <p class="mono" style="font-size:13px; color:var(--ink-soft);">Bước <?= $stepNo ?> / <?= $stepTotal ?>: Vui lòng hoàn tất lượt vượt link tiếp theo.</p>
 <div class="errbox">Bạn cần hoàn tất link Layma.net để nhận key. Hệ thống sẽ tính giờ tối thiểu 45 giây.</div>
 <a id="laymaBtn" class="btn" href="<?= htmlspecialchars($activeLaymaUrl) ?>" target="_blank">Mở link Layma.net (Bước <?= $stepNo ?>)</a>
 <p class="mono" style="font-size:11px; color:var(--ink-soft); margin-top:14px; text-align:center;">Sau khi vượt link xong, hãy bấm nút bên dưới để xác nhận:</p>
 <button id="checkLaymaBtn" class="btn" style="background:var(--paper); color:var(--ink); margin-top:8px;">Tôi đã hoàn tất — Nhận tiếp</button>
 <script>
 const laymaUrl = <?= $laymaUrlJson ?>;
 document.getElementById('laymaBtn').addEventListener('click', () => {
 fetch('getkey_action.php', {
 method: 'POST',
 headers: {'Content-Type': 'application/x-www-form-urlencoded'},
 body: 'action=begin_layma'
 }).catch(() => {});
 });
 document.getElementById('checkLaymaBtn').addEventListener('click', () => {
 window.location.href = 'claim.php';
 });
 </script>
 <?php elseif ($pageMode === 'success'): ?>
 <div class="okbox">🔑 <b><?= htmlspecialchars($keyText) ?></b></div>
 <p class="mono" style="font-size:12px; margin:6px 0;">Hạn sử dụng: <b><?= htmlspecialchars($expiresLabel) ?></b> (<?= htmlspecialchars($remainingLabel) ?>)</p>
 <button class="btn" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($keyText) ?>'); alert('Đã copy key!');">Copy Key</button>
 <a class="btn" href="getkey.php" style="background:var(--paper); color:var(--ink); margin-top:10px;">Tạo thêm key mới</a>
 <?php endif; ?>
 </div>
</div>
</body>
</html>
