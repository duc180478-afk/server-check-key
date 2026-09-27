<?php
// api_report.php — heartbeat + silent ban. HMAC BẮT BUỘC, không optional.

require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

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

// HMAC BẮT BUỘC — bỏ sig = fail ngay.
// Lý do fail cụ thể chỉ log server-side (error_log), KHÔNG trả ra response —
// tránh cho attacker 1 oracle để dò dần format canon/ts/nonce đúng.
if ($sig === '' || $ts === '' || $nonce === '') {
    error_log("api_report reject key=$key hwid=$hwid reason=missing_sig");
    echo json_encode(['ok' => 0]);
    exit;
}
if (abs(time() - (int)$ts) > 180) {
    error_log("api_report reject key=$key hwid=$hwid reason=expired");
    echo json_encode(['ok' => 0]);
    exit;
}
if (!consumeNonce($nonce)) {
    error_log("api_report reject key=$key hwid=$hwid reason=replay");
    echo json_encode(['ok' => 0]);
    exit;
}
$canon = $key . '|' . $hwid . '|' . $event . '|' . $ts . '|' . $nonce;
if (!hash_equals(hash_hmac('sha256', $canon, $APP_SECRET), strtolower($sig))) {
    error_log("api_report reject key=$key hwid=$hwid reason=bad_sig");
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
