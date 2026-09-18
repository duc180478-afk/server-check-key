<?php
// api_check.php

require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

// ★ APP_SECRET — PHẢI GIỐNG CHÍNH XÁC chuỗi đã obfuscate trong
//   Secrets.kt → APP_SECRET_OBF (salt 0x77)
 $APP_SECRET = 'FAKEPING_BY_MDUCDZ';

// Chống brute-force: tối đa 30 request / 60 giây / IP
 $ip = getClientIp();
if (!checkRateLimit('api_' . $ip, 30, 60)) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Quá nhiều yêu cầu. Vui lòng thử lại sau.']);
    exit;
}

 $key  = isset($_GET['key'])  ? trim($_GET['key'])  : '';
 $hwid = isset($_GET['hwid']) ? trim($_GET['hwid']) : ''; // nhận nhưng không bắt buộc

if (empty($key)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Thiếu Key!',
        'p' => null,
        's' => null
    ]);
    exit;
}

// Gọi validateKey — KHÔNG đổi logic DB
 $result = validateKey($key, $hwid);

// ★ PHÂN LOẠI APP theo cột app của key (không phụ thuộc prefix)
 $kd = getKeyByKey($key);
 $result['app'] = $kd ? getAppLabel($kd['app'] ?? '') : 'Unknown';

// ================================================================
// ★ HMAC PAYLOAD (Lớp 3)
// ================================================================
 $ok = 0;
if (is_array($result)) {
    if (isset($result['status']) && strtolower($result['status']) === 'success') {
        $ok = 1;
    }
    if (isset($result['valid']) && $result['valid'] === true) {
        $ok = 1;
    }
    if (isset($result['ok']) && (int)$result['ok'] === 1) {
        $ok = 1;
    }
} elseif (is_bool($result)) {
    $ok = $result ? 1 : 0;
}

 $payloadArr = [
    'ok'  => $ok,
    'exp' => time() + 300,
    't'   => substr($key, 0, 4),
];
 $payload = json_encode($payloadArr);
 $sig     = hash_hmac('sha256', $payload, $APP_SECRET);

 $result['p'] = $payload;
 $result['s'] = $sig;

echo json_encode($result);