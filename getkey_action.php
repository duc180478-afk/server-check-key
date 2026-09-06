<?php
// getkey_action.php - API tạo key free (gọi bằng AJAX từ getkey.php)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/keygen_functions.php';

header('Content-Type: application/json');

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

// Dọn trang key cũ + giới hạn số key mỗi IP mỗi giờ
cleanupOldKeyPages();
if (countRecentKeysByIp($ip) >= KEYGEN_MAX_PER_HOUR) {
    echo json_encode(['status' => 'error', 'message' => 'Bạn đã tạo quá nhiều key! Vui lòng thử lại sau 1 giờ.']);
    exit;
}

// ===== QUÁ TRÌNH BÍ MẬT BÊN SERVER =====
// 1. Tạo key thật (cố định: prefix FLFF, 1 ngày, 1 device)
 $keyText = createKey(KEYGEN_GAME_PREFIX, KEYGEN_DAYS, 1, 'Free key - web getkey');

// 2. Tạo trang chứa key với token bí mật
 $token = createKeyPage($keyText, $ip);
 $keyPageUrl = getSiteBaseUrl() . '/viewkey.php?token=' . $token;

// 3. Gửi URL trang chứa key vào link4m.co để tạo link rút gọn
 $result = createLink4mShortlink($keyPageUrl);

if (!$result['success']) {
    // ✅ QUAN TRỌNG: rollback — xóa trang key + xóa key để KHÔNG tốn quota của user
    deleteKeyPage($token);
    deleteKeyByKey($keyText);

    $msg = 'Không tạo được link, vui lòng thử lại sau!';
    if (KEYGEN_DEBUG) {
        $msg .= ' [DEBUG] Lỗi: ' . $result['error'] . ' | Raw: ' . mb_substr($result['raw'], 0, 300);
    }
    echo json_encode(['status' => 'error', 'message' => $msg]);
    exit;
}

// 4. Chỉ trả về link rút gọn
echo json_encode(['status' => 'success', 'shortlink' => $result['shortlink']]);