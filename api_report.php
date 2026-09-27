<?php
// api_report.php — heartbeat + silent ban from client troll/detect modules
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

// Khuyến nghị: chuyển APP_SECRET vào config.php thay vì hardcode ở đây,
// để dễ rotate và tránh lộ nếu file này từng bị commit nhầm lên git public.
$APP_SECRET = 'FAKEPING_BY_MDUCDZ';

$ip = getClientIp();
if (!checkRateLimit('rpt_' . $ip, 40, 60)) {
    http_response_code(429);
    echo json_encode(['ok' => 0]);
    exit;
}

$key    = trim($_POST['key']    ?? $_GET['key']    ?? '');
$hwid   = trim($_POST['hwid']   ?? $_GET['hwid']   ?? '');
$sid    = trim($_POST['sid']    ?? $_GET['sid']    ?? '');
$event  = trim($_POST['event']  ?? $_GET['event']  ?? 'heartbeat');
$detail = substr(trim($_POST['detail'] ?? $_GET['detail'] ?? ''), 0, 500);
$ts     = trim($_POST['ts']     ?? $_GET['ts']     ?? '');
$nonce  = trim($_POST['nonce']  ?? $_GET['nonce']  ?? '');
$sig    = trim($_POST['sig']    ?? $_GET['sig']    ?? '');

$allowedEvents = ['heartbeat', 'frida', 'root', 'xposed', 'emulator', 'integrity', 'honeypot', 'overlay', 'packet_drop'];
if (!in_array($event, $allowedEvents, true)) $event = 'heartbeat';

if ($key === '' || $hwid === '') {
    echo json_encode(['ok' => 0]);
    exit;
}

// FIX: chữ ký BẮT BUỘC cho mọi request — bản gốc "if ($sig !== '') {...}" cho phép
// bỏ trống sig để bypass toàn bộ verify (bao gồm cả các event ban như frida/root).
if ($sig === '' || $ts === '' || $nonce === '') {
    echo json_encode(['ok' => 0]);
    exit;
}
if (abs(time() - (int)$ts) > 180) {
    echo json_encode(['ok' => 0]);
    exit;
}
if (!consumeNonce($nonce)) {
    echo json_encode(['ok' => 0]);
    exit;
}
$canon = $key . '|' . $hwid . '|' . $event . '|' . $ts . '|' . $nonce;
if (!hash_equals(hash_hmac('sha256', $canon, $APP_SECRET), strtolower($sig))) {
    echo json_encode(['ok' => 0]);
    exit;
}

$kd = getKeyByKey($key);
if (!$kd) {
    echo json_encode(['ok' => 0]);
    exit;
}

logKeyEvent($key, $hwid, $ip, $event, $detail);

$banEvents = ['frida', 'root', 'xposed', 'integrity', 'honeypot'];
if (in_array($event, $banEvents, true) && $kd['status'] === 'active') {
    revokeKey($kd, $event);
    echo json_encode(['ok' => 1, 'banned' => 1]);
    exit;
}

$alive = verifySession($key, $hwid, $sid);
echo json_encode(['ok' => $alive ? 1 : 0, 'banned' => $alive ? 0 : 1]);
