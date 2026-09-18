<?php
// keygen_functions.php
// Các hàm phục vụ trang lấy key miễn phí (getkey + claim)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// ================== CẤU HÌNH — SỬA Ở ĐÂY ==================

// ⭐ DANH SÁCH APP — mỗi app có prefix gợi ý, số ngày, LINK RÚT GỌN RIÊNG
// key phải trùng với key của SYSTEM_APPS trong functions.php
 $KEYGEN_APPS = [
    'fakelag' => [
        'label'     => 'FakeLag-Free Fire',
        'prefix'    => 'FLFF',
        'days'      => 1,
        'shortlink' => 'https://link4m.net/go/2EdzW3',
    ],
    'proxy' => [
        'label'     => 'Proxy FF',
        'prefix'    => 'PRX',
        'days'      => 1,
        'shortlink' => 'https://link4m.net/J6jvwz',
    ],
];

// Giới hạn: số key tối đa 1 IP được lấy trong 1 giờ (chống spam)
define('KEYGEN_MAX_PER_HOUR', 3);

// Thời gian lưu log claim (giờ)
define('KEYGEN_LOG_TTL_HOURS', 24);

// ⏱️ Thời gian tối thiểu Generate → claim (giây) — chống bypass
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

// Kiểm tra shortlink của 1 app đã cấu hình chưa
function appShortlinkReady($appCfg) {
    $u = $appCfg['shortlink'] ?? '';
    return is_string($u) && filter_var($u, FILTER_VALIDATE_URL) && strpos($u, 'PASTE_') !== 0;
}