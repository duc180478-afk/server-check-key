<?php
// keygen_functions.php
// Các hàm phục vụ trang lấy key miễn phí (getkey + claim)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// ================== CẤU HÌNH — SỬA Ở ĐÂY ==================

// ⭐ Link rút gọn tĩnh (trỏ tới https://server-check-key.onrender.com/claim)
define('KEYGEN_CLAIM_SHORTLINK', 'https://link4m.net/go/2EdzW3');

// Game cố định (khoá cứng)
define('KEYGEN_GAME_LABEL',  'FakeLag-Free Fire');
define('KEYGEN_GAME_PREFIX', 'FLFF');

// Khoá cứng 1 ngày (không cho người dùng chọn)
define('KEYGEN_DAYS', 1);

// Giới hạn: số key tối đa 1 IP được lấy trong 1 giờ (chống spam)
define('KEYGEN_MAX_PER_HOUR', 3);

// Thời gian lưu log claim (giờ) — chỉ dùng để đếm lượt theo IP
define('KEYGEN_LOG_TTL_HOURS', 24);

// ⏱️ Thời gian tối thiểu từ lúc bấm Generate đến khi đến claim (giây)
// Đến nhanh hơn mức này = nghi là bypass tool -> chặn
define('KEYGEN_MIN_CLAIM_SECONDS', 45);

// ⏱️ Vé có hiệu lực bao lâu sau khi bấm Generate (giây) = 10 phút
define('KEYGEN_TICKET_TTL_SECONDS', 600);

// ==========================================================

// Bảng key_pages dùng làm LOG ghi nhận IP lấy key (để giới hạn lượt)
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

// Ghi log 1 lần lấy key (để đếm lượt theo IP)
function logClaim($keyText, $ip) {
    initKeyPagesTable();
    $pdo = Database::getConnection();
    $token = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("INSERT INTO key_pages (token, key_text, ip, created_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$token, $keyText, $ip, date('Y-m-d H:i:s')]);
}

// Đếm số key 1 IP đã lấy trong $minutes phút gần nhất
function countRecentKeysByIp($ip, $minutes = 60) {
    initKeyPagesTable();
    $pdo = Database::getConnection();
    $since = date('Y-m-d H:i:s', time() - $minutes * 60);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM key_pages WHERE ip = ? AND created_at > ?");
    $stmt->execute([$ip, $since]);
    return (int)$stmt->fetchColumn();
}

// Xóa log quá hạn
function cleanupOldKeyPages() {
    initKeyPagesTable();
    $pdo = Database::getConnection();
    $pdo->prepare("DELETE FROM key_pages WHERE created_at < ?")
        ->execute([date('Y-m-d H:i:s', time() - KEYGEN_LOG_TTL_HOURS * 3600)]);
}

// Kiểm tra link claim đã cấu hình chưa
function claimShortlinkReady() {
    $u = KEYGEN_CLAIM_SHORTLINK;
    return is_string($u) && filter_var($u, FILTER_VALIDATE_URL) && strpos($u, 'PASTE_') !== 0;
}