<?php
// AJAX handler cho GetKey: tạo vé, bắt đầu Link4m và bắt đầu từng lượt Layma.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/keygen_functions.php';

header('Content-Type: application/json; charset=utf-8');
session_start();

function keygenJson(array $payload, int $statusCode = 200): void {
 http_response_code($statusCode);
 echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
 exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
 keygenJson(['status' => 'error', 'message' => 'Phương thức không hợp lệ!'], 405);
}

$ip = getClientIp();
$action = (string)($_POST['action'] ?? 'create');

if ($action === 'begin_link4m' || $action === 'begin_layma') {
 if (!checkRateLimit('keygen_step_' . $ip, 20, 60)) {
 keygenJson(['status' => 'error', 'message' => 'Quá nhiều thao tác. Vui lòng thử lại sau!'], 429);
 }

 $ticket = getCurrentClaimTicket($ip);
 if (!is_array($ticket) || ($ticket['ip'] ?? '') !== $ip) {
 keygenJson(['status' => 'error', 'message' => 'Không tìm thấy vé hợp lệ cho lựa chọn này. Hãy bấm Tạo key trên GetKey trước!'], 400);
 }

 if (time() - (int)($ticket['created'] ?? 0) > KEYGEN_TICKET_TTL_SECONDS) {
 setClaimTicketStatus($ticket, 'expired');
 keygenJson(['status' => 'error', 'message' => 'Yêu cầu đã hết hạn. Hãy tạo yêu cầu mới!'], 400);
 }

 if ($action === 'begin_link4m') {
 if (($ticket['stage'] ?? '') !== 'ready_link4m') {
 keygenJson(['status' => 'error', 'message' => 'Link4m đã được bắt đầu hoặc vé không đúng bước.'], 409);
 }
 $ticket['stage'] = 'await_link4m_return';
 $ticket['link4m_started_at'] = time();
 if (!saveClaimTicket($ticket)) {
 keygenJson(['status' => 'error', 'message' => 'Vé đã hết hiệu lực. Vui lòng tạo yêu cầu mới!'], 409);
 }
 keygenJson(['status' => 'success']);
 }

 if (($ticket['stage'] ?? '') !== 'ready_layma' || (int)($ticket['layma_remaining'] ?? 0) < 1) {
 keygenJson(['status' => 'error', 'message' => 'Chưa đến bước Layma.net hoặc lượt Layma đã hết.'], 409);
 }
 $ticket['stage'] = 'await_layma_return';
 $ticket['layma_started_at'] = time();
 if (!saveClaimTicket($ticket)) {
 keygenJson(['status' => 'error', 'message' => 'Vé đã hết hiệu lực. Vui lòng tạo yêu cầu mới!'], 409);
 }
 keygenJson(['status' => 'success']);
}

if ($action !== 'create') {
 keygenJson(['status' => 'error', 'message' => 'Thao tác không hợp lệ!'], 400);
}

if (!checkRateLimit('keygen_' . $ip, 10, 60)) {
 keygenJson(['status' => 'error', 'message' => 'Quá nhiều yêu cầu, thử lại sau ít phút!'], 429);
}

cleanupOldKeyPages();
cleanupClaimTickets();
$used = countRecentKeysByIp($ip);
if ($used >= KEYGEN_MAX_PER_HOUR) {
 keygenJson(['status' => 'error', 'message' => 'Bạn đã tạo quá nhiều key! Vui lòng thử lại sau 1 giờ.']);
}

$game = (string)($_POST['game'] ?? '');
if (!isset($KEYGEN_APPS[$game])) {
 keygenJson(['status' => 'error', 'message' => 'Vui lòng chọn ứng dụng hợp lệ!']);
}
$appCfg = $KEYGEN_APPS[$game];

$durationKey = (string)($_POST['duration'] ?? '1d');
if (!isset($KEYGEN_DURATIONS[$durationKey])) {
 keygenJson(['status' => 'error', 'message' => 'Thời hạn đã chọn không hợp lệ!']);
}
$durationCfg = $KEYGEN_DURATIONS[$durationKey];

if (!appShortlinkReady($appCfg)) {
 keygenJson(['status' => 'error', 'message' => 'Ứng dụng này chưa cấu hình Link4m. Vui lòng báo admin!']);
}

try {
 $ticket = createClaimTicket($ip, $game, $durationKey, $durationCfg, $appCfg['shortlink'] ?? '');
} catch (Throwable $e) {
 keygenJson(['status' => 'error', 'message' => $e->getMessage() ?: 'Không thể tạo vé lấy key lúc này. Vui lòng thử lại!'], 500);
}

keygenJson([
 'status' => 'success',
 'ticketId' => $ticket['ticket_id'],
 'unlockUrl' => $ticket['current_short_url'],
 'duration' => $durationCfg['label'],
 'laymaPasses' => (int)$durationCfg['layma_passes'],
 'remaining' => KEYGEN_MAX_PER_HOUR - $used,
 'appLabel' => $appCfg['label'],
]);
