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

// DEBUG: true = hiện chi tiết lỗi link4m trên web (xong nhớ đặt về false!)
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

function deleteKeyByKey($keyText) {
    $keyData = getKeyByKey($keyText);
    if ($keyData) {
        deleteKey($keyData['id']);
    }
}

// ===== HTTP GET: ưu tiên cURL, fallback file_get_contents =====
function keygenHttpGet($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => ['Accept: application/json, text/plain, */*'],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($resp === false) {
            error_log('[keygen] cURL error: ' . $err);
            return false;
        }
        error_log('[keygen] HTTP ' . $code);
        return $resp;
    }
    $context = stream_context_create(['http' => [
        'timeout'       => 20,
        'ignore_errors' => true,
        'header'        => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\nAccept: application/json, text/plain, */*\r\n",
    ]]);
    return @file_get_contents($url, false, $context);
}

// Parse response của link4m: hỗ trợ JSON nhiều format + text thuần
function parseLink4mResponse($resp) {
    if (!is_string($resp) || trim($resp) === '') return null;
    $data = json_decode($resp, true);
    if (is_array($data)) {
        $short = $data['shortenedUrl']
              ?? $data['shorturl']
              ?? $data['short']
              ?? $data['shortlink']
              ?? $data['shortened']
              ?? $data['url']
              ?? ($data['data']['shortlink'] ?? null)
              ?? ($data['data']['url'] ?? null);
        if (is_string($short) && filter_var($short, FILTER_VALIDATE_URL)) {
            return $short;
        }
        return null;
    }
    // API trả text thuần (chỉ 1 dòng link)
    $trim = trim($resp);
    if (filter_var($trim, FILTER_VALIDATE_URL)) return $trim;
    return null;
}

/**
 * Gọi API link4m.co rút gọn link.
 * ✅ Thử 2 kiểu: URL NGUYÊN BẢN (giống khi bạn dán tay vào trình duyệt)
 *    rồi mới đến kiểu urlencoded.
 */
function createLink4mShortlink($url) {
    $apiBase = 'https://link4m.co/st?api=' . KEYGEN_LINK4M_API . '&url=';

    $candidates = [
        $apiBase . $url,             // 1. nguyên bản (raw) — giống dán tay
        $apiBase . urlencode($url),  // 2. encode
    ];

    $lastErr = 'Không tạo được link từ link4m';
    $lastRaw = '';

    foreach ($candidates as $i => $apiUrl) {
        $label = ($i === 0) ? 'raw' : 'encoded';
        error_log('[keygen] link4m thu kieu ' . $label . ': ' . $apiUrl);

        $resp = keygenHttpGet($apiUrl);
        if ($resp === false) {
            $lastErr = 'Không kết nối được đến link4m.co';
            continue;
        }
        error_log('[keygen] link4m (' . $label . ') response: ' . substr($resp, 0, 400));
        $lastRaw = $resp;

        $short = parseLink4mResponse($resp);
        if ($short !== null) {
            return ['success' => true, 'shortlink' => $short, 'error' => '', 'raw' => $resp];
        }
        $lastErr = 'link4m phản hồi nhưng không có link hợp lệ';
    }

    return ['success' => false, 'shortlink' => '', 'error' => $lastErr, 'raw' => $lastRaw];
}

function getSiteBaseUrl() {
    $scheme = 'http';
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') $scheme = 'https';
    elseif (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') $scheme = 'https';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}