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

// Tạo 1 trang chứa key với token bí mật (người dùng không biết URL trực tiếp)
function createKeyPage($keyText, $ip) {
    initKeyPagesTable();
    $pdo = Database::getConnection();
    $token = bin2hex(random_bytes(16)); // 32 ký tự ngẫu nhiên, không đoán được
    $stmt = $pdo->prepare("INSERT INTO key_pages (token, key_text, ip, created_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$token, $keyText, $ip, date('Y-m-d H:i:s')]);
    return $token;
}

// Lấy trang chứa key theo token (tự kiểm tra hạn)
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

// Đếm số key 1 IP đã tạo trong $minutes phút gần nhất
function countRecentKeysByIp($ip, $minutes = 60) {
    initKeyPagesTable();
    $pdo = Database::getConnection();
    $since = date('Y-m-d H:i:s', time() - $minutes * 60);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM key_pages WHERE ip = ? AND created_at > ?");
    $stmt->execute([$ip, $since]);
    return (int)$stmt->fetchColumn();
}

// Xóa các trang chứa key quá hạn
function cleanupOldKeyPages() {
    initKeyPagesTable();
    $pdo = Database::getConnection();
    $pdo->prepare("DELETE FROM key_pages WHERE created_at < ?")
        ->execute([date('Y-m-d H:i:s', time() - KEYGEN_PAGE_TTL_HOURS * 3600)]);
}

// Gọi API link4m.co để rút gọn link
function createLink4mShortlink($url) {
    $apiUrl = 'https://link4m.co/st?api=' . KEYGEN_LINK4M_API . '&url=' . urlencode($url);
    $context = stream_context_create(['http' => [
        'timeout' => 15,
        'header'  => "User-Agent: Mozilla/5.0\r\n"
    ]]);
    $resp = @file_get_contents($apiUrl, false, $context);
    if ($resp === false) return null;
    $data = json_decode($resp, true);
    // Hỗ trợ nhiều format response khác nhau
    $short = $data['shortenedUrl'] ?? $data['shorturl'] ?? $data['short'] ?? null;
    if (is_string($short) && filter_var($short, FILTER_VALIDATE_URL)) {
        return $short;
    }
    return null;
}

// Lấy URL gốc của site (hỗ trợ Render/proxy)
function getSiteBaseUrl() {
    $scheme = 'http';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') $scheme = 'https';
    elseif (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') $scheme = 'https';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}