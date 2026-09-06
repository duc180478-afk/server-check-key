<?php
// api_check.php

require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

// Chống brute-force: tối đa 30 request / 60 giây / IP
 $ip = getClientIp();
if (!checkRateLimit('api_' . $ip, 30, 60)) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Quá nhiều yêu cầu. Vui lòng thử lại sau.']);
    exit;
}

 $key = isset($_GET['key']) ? trim($_GET['key']) : '';
 $hwid = isset($_GET['hwid']) ? trim($_GET['hwid']) : ''; // nhận nhưng không bắt buộc

if (empty($key)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Thiếu Key!'
    ]);
    exit;
}

 $result = validateKey($key, $hwid);
echo json_encode($result);