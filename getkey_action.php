<?php
// getkey_action.php - Kiểm tra lượt + cấp vé lấy key (gọi bằng AJAX từ getkey)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/keygen_functions.php';

header('Content-Type: application/json');
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Phương thức không hợp lệ!']);
    exit;
}

if (!claimShortlinkReady()) {
    echo json_encode(['status' => 'error', 'message' => 'Hệ thống chưa cấu hình link lấy key. Vui lòng báo admin!']);
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

// ✅ CẤP VÉ MỘT LẦN (lưu vào session của trình duyệt này)
 $_SESSION['claim_ticket'] = [
    'created' => time(),
    'ip'      => $ip,
];

echo json_encode([
    'status' => 'success',
    'unlockUrl' => KEYGEN_CLAIM_SHORTLINK,
    'remaining' => KEYGEN_MAX_PER_HOUR - $used
]);