<?php
// keygen_functions.php
// Các hàm phục vụ trang lấy key miễn phí (getkey.php)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// ================== CẤU HÌNH — SỬA Ở ĐÂY ==================

// API key của link4m.co
define('KEYGEN_LINK4M_API', '6a68cadf75156e7ce6748248');

// Game cố định (khoá cứng)
define('KEYGEN_GAME_LABEL',  'FakeLag-Free Fire');
define('KEYGEN_GAME_PREFIX', 'FLFF');

// Khoá cứng 1 ngày (không cho người dùng chọn)
define('KEYGEN_DAYS', 1);

// Giới hạn: số key tối đa 1 IP được tạo trong 1 giờ (chống spam)
define('KEYGEN_MAX_PER_HOUR', 3);

// Trang chứa key sống bao lâu (giờ) trước khi tự vô hiệu
define('KEYGEN_PAGE_TTL_HOURS', 24);

// ✅ DEBUG: đặt true để hiện chi tiết lỗi link4m ngay trên web (xong rồi đặt lại false!)
define('KEYGEN_DEBUG', false);

// ==========================================================

function initKeyPagesTable() {
    static $done = false;
    if ($done) return;
    $pdo = Database::getConnection();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'pgsql') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS key_pages (
            id SERIAL PRIMARY KEY,
            token TEXT UNIQUE NOT NULL,
            key_text TEXT NOT NULL,
            ip TEXT,
            created_at TEXT NOT NULL
        )");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS key_pages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            token TEXT UNIQUE NOT NULL,
            key_text TEXT NOT NULL,
            ip TEXT,
            created_at TEXT NOT NULL
        )");
    }
    $done = true;
}

function createKeyPage($keyText, $ip) {
    initKeyPagesTable();
    $pdo = Database::getConnection();
    $token = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("INSERT INTO key_pages (token, key_text, ip, created_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$token, $keyText, $ip, date('Y-m-d H:i:s')]);
    return $token;
}

function deleteKeyPage($token) {
    initKeyPagesTable();
    $pdo = Database::getConnection();
    $pdo->prepare("DELETE FROM key_pages WHERE token = ?")->execute([$token]);
}

function getKeyPageByToken($token) {
    initKeyPagesTable();
    if (!is_string($token) || !preg_match('/^[a-f0-9]{32}$/', $token)) return null;
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("SELECT * FROM key_pages WHERE token = ?");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    if (strtotime($row['created_at']) < time() - KEYGEN_PAGE_TTL_HOURS * 3600) return null;
    return $row;
}

function countRecentKeysByIp($ip, $minutes = 60) {
    initKeyPagesTable();
    $pdo = Database::getConnection();
    $since = date('Y-m-d H:i:s', time() - $minutes * 60);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM key_pages WHERE ip = ? AND created_at > ?");
    $stmt->execute([$ip, $since]);
    return (int)$stmt->fetchColumn();
}

function cleanupOldKeyPages() {
    initKeyPagesTable();
    $pdo = Database::getConnection();
    $pdo->prepare("DELETE FROM key_pages WHERE created_at < ?")
        ->execute([date('Y-m-d H:i:s', time() - KEYGEN_PAGE_TTL_HOURS * 3600)]);
}

// Xóa key theo chuỗi key (dùng để rollback khi tạo link thất bại)
function deleteKeyByKey($keyText) {
    $keyData = getKeyByKey($keyText);
    if ($keyData) {
        deleteKey($keyData['id']);
    }
}

/**
 * Gọi API link4m.co để rút gọn link.
 * ✅ Trả về mảng: ['success' => bool, 'shortlink' => string, 'error' => string, 'raw' => string]
 * Ghi log ra error_log (xem trong Render Dashboard -> Logs)
 */
function createLink4mShortlink($url) {
    $apiUrl = 'https://link4m.co/st?api=' . KEYGEN_LINK4M_API . '&url=' . urlencode($url);

    $context = stream_context_create(['http' => [
        'timeout'       => 20,
        'method'        => 'GET',
        'ignore_errors' => true, // vẫn nhận body dù trả 4xx/5xx
        'header'        => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n" .
                           "Accept: application/json, text/plain, */*\r\n"
    ]]);

    $resp = @file_get_contents($apiUrl, false, $context);

    if ($resp === false) {
        error_log('[keygen] link4m: KHONG ket noi duoc den link4m.co');
        return ['success' => false, 'shortlink' => '', 'error' => 'Không kết nối được đến link4m.co', 'raw' => ''];
    }

    // Lấy mã HTTP trả về (để log)
    $httpCode = 0;
    if (isset($http_response_header) && preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
        $httpCode = (int)$m[1];
    }
    error_log('[keygen] link4m: HTTP ' . $httpCode . ' | Raw: ' . substr($resp, 0, 500));

    // Thử parse JSON với nhiều tên trường khác nhau
    $data = json_decode($resp, true);
    if (is_array($data)) {
        $short = $data['shortenedUrl']
              ?? $data['shorturl']
              ?? $data['short']
              ?? $data['url']
              ?? $data['shortlink']
              ?? ($data['data']['shortlink'] ?? null)
              ?? ($data['data']['url'] ?? null);
        if (is_string($short) && filter_var($short, FILTER_VALIDATE_URL)) {
            return ['success' => true, 'shortlink' => $short, 'error' => '', 'raw' => $resp];
        }
        $errMsg = $data['message'] ?? $data['error'] ?? $data['msg'] ?? 'link4m từ chối yêu cầu';
        return ['success' => false, 'shortlink' => '', 'error' => (string)$errMsg, 'raw' => $resp];
    }

    // Fallback: API trả về link dạng text thuần
    $trim = trim($resp);
    if (filter_var($trim, FILTER_VALIDATE_URL) && strpos($trim, 'http') === 0) {
        return ['success' => true, 'shortlink' => $trim, 'error' => '', 'raw' => $resp];
    }

    return ['success' => false, 'shortlink' => '', 'error' => 'Phản hồi không hiểu từ link4m', 'raw' => $resp];
}

function getSiteBaseUrl() {
    $scheme = 'http';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') $scheme = 'https';
    elseif (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') $scheme = 'https';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}