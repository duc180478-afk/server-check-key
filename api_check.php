<?php
// api_check.php

require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

// ★ APP_SECRET — PHẢI GIỐNG CHÍNH XÁC chuỗi đã obfuscate trong
//   Secrets.kt → APP_SECRET_OBF (salt 0x77)
// ★ ĐỔI thành chuỗi của bạn nếu chưa
 $APP_SECRET = 'CHUỖI_SECRET_CỦA_BẠN';

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
    // Response lỗi giữ format cũ (client mới chỉ quan tâm field p/s,
    // nhưng format cũ vẫn trả về để tương thích ngược với app cũ nếu còn)
    echo json_encode([
        'status' => 'error',
        'message' => 'Thiếu Key!',
        'p' => null,
        's' => null
    ]);
    exit;
}

// ★ Gọi validateKey cũ của bạn — KHÔNG đổi logic DB
 $result = validateKey($key, $hwid);

// ================================================================
// ★ HMAC PAYLOAD (Lớp 3)
// Tạo payload + signature từ kết quả validateKey.
// app mới đọc: p (payload), s (chữ ký HMAC-SHA256)
// ================================================================

// Chuẩn hoá ok từ kết quả cũ: validateKey trả về dạng gì cũng bắt được
 $ok = 0;
if (is_array($result)) {
    // case 1: ['status' => 'success']
    if (isset($result['status']) && strtolower($result['status']) === 'success') {
        $ok = 1;
    }
    // case 2: ['valid' => true]
    if (isset($result['valid']) && $result['valid'] === true) {
        $ok = 1;
    }
    // case 3: ['ok' => 1]
    if (isset($result['ok']) && (int)$result['ok'] === 1) {
        $ok = 1;
    }
} elseif (is_bool($result)) {
    $ok = $result ? 1 : 0;
}

 $payloadArr = [
    'ok'  => $ok,
    'exp' => time() + 300,           // expire 5 phút — chống replay
    't'   => substr($key, 0, 4),     // echo một phần key
];
 $payload = json_encode($payloadArr);
 $sig     = hash_hmac('sha256', $payload, $APP_SECRET);

// Trả về: giữ nguyên $result cũ (tương thích ngược) + thêm p/s (app mới đọc)
 $result['p'] = $payload;
 $result['s'] = $sig;

echo json_encode($result);