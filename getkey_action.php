<?php
// getkey_action.php - Kiểm tra lượt + cấp vé (gọi bằng AJAX từ getkey)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/keygen_functions.php';

header('Content-Type: application/json');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Phương thức không hợp lệ!']);
    exit;
}

 $ip = getClientIp();

// Chống flood request
if (!checkRateLimit('keygen_' . $ip, 10, 60)) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Quá nhiều yêu cầu, thử lại sau ít phút!']);
    exit;
}

// Dọn log cũ + kiểm tra giới hạn theo IP
cleanupOldKeyPages();
 $used = countRecentKeysByIp($ip);
if ($used >= KEYGEN_MAX_PER_HOUR) {
    echo json_encode(['status' => 'error', 'message' => 'Bạn đã tạo quá nhiều key! Vui lòng thử lại sau 1 giờ.']);
    exit;
}

// Whitelist app — CHỈ nhận app có trong danh sách server
 $game = $_POST['game'] ?? '';
if (!isset($KEYGEN_APPS[$game])) {
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng chọn app!']);
    exit;
}
 $appCfg = $KEYGEN_APPS[$game];

// Kiểm tra shortlink của app này đã cấu hình chưa
if (!appShortlinkReady($appCfg)) {
    echo json_encode(['status' => 'error', 'message' => 'App này chưa cấu hình link lấy key. Vui lòng báo admin!']);
    exit;
}

// CẤP VÉ MỘT LẦN — lưu cả app user đã chọn
 $_SESSION['claim_ticket'] = [
    'created' => time(),
    'ip'      => $ip,
    'game'    => $game,
];

echo json_encode([
    'status' => 'success',
    'unlockUrl' => $appCfg['shortlink'],
    'remaining' => KEYGEN_MAX_PER_HOUR - $used
]);