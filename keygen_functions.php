<?php
// keygen_functions.php
// Các hàm phục vụ trang lấy key miễn phí (getkey + claim)
// + block cuối: cấu hình app/Telegram qua database (quản trị tại admin/config.php)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// ================== CẤU HÌNH ==================

$KEYGEN_APPS = [
    'fakelag' => [
        'label'     => 'FakeLag-Free Fire',
        'prefix'    => 'FLFF',
        'shortlink' => 'https://link4m.net/go/Q9w2R',
    ],
    'proxy' => [
        'label'     => 'Proxy FF',
        'prefix'    => 'PRX',
        'shortlink' => 'https://link4m.net/go/Q9w2R',
    ],
];

$KEYGEN_DURATIONS = [
    '12h' => [
        'label'        => '12h',
        'seconds'      => 43200,
        'layma_passes' => 0,
    ],
    '1d' => [
        'label'        => '1 ngày',
        'seconds'      => 86400,
        'layma_passes' => 1,
    ],
    '2d' => [
        'label'        => '2 ngày',
        'seconds'      => 172800,
        'layma_passes' => 2,
    ],
];

define('KEYGEN_LAYMA_URL', 'https://layma.net/jwxpdcTUo');
define('KEYGEN_MAX_PER_HOUR', 3);
define('KEYGEN_LOG_TTL_HOURS', 24);
define('KEYGEN_MIN_CLAIM_SECONDS', 45);
define('KEYGEN_MIN_LAYMA_SECONDS', 45);
define('KEYGEN_TICKET_TTL_SECONDS', 1800);

// API Token của Link4m và Layma.net
define('LINK4M_API_TOKEN', getenv('LINK4M_API_TOKEN') ?: '6a68cadf75156e7ce6748248');
define('LAYMA_API_TOKEN', getenv('LAYMA_API_TOKEN') ?: '55bdd5ce78d3d25c8cbc3dd26b598742');

// ==========================================================

// Lấy Base URL hiện tại của web (để tạo link callback về claim.php)
function getServerBaseUrl(): string {
    $proto = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']))
        ? explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]
        : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
    $host = $_SERVER['HTTP_HOST'] ?? 'server-check-key-vwoq.onrender.com';
    return rtrim($proto . '://' . $host, '/');
}

// Tạo URL đích đến claim.php kèm token dùng 1 lần
function buildClaimCallbackUrl(string $stepToken): string {
    return getServerBaseUrl() . '/claim.php?k=' . rawurlencode($stepToken);
}

// Gọi API Link4m tạo link rút gọn động
function createDynamicLink4mUrl(string $destinationUrl, string $fallbackUrl = ''): string {
    $apiToken = LINK4M_API_TOKEN;
    $longUrl = urlencode($destinationUrl);
    $apiUrl = "https://link4m.co/api-shorten/v2?api={$apiToken}&url={$longUrl}";

    $raw = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 6,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => ['timeout' => 6, 'ignore_errors' => true],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false]
        ]);
        $raw = @file_get_contents($apiUrl, false, $ctx);
    }

    if (is_string($raw) && $raw !== '') {
        $result = @json_decode(trim($raw), true);
        if (is_array($result) && ($result['status'] ?? '') === 'success' && !empty($result['shortenedUrl'])) {
            return (string)$result['shortenedUrl'];
        }
    }
    throw new RuntimeException('Không thể khởi tạo link vượt Link4m lúc này. Vui lòng thử lại!');
}

// Gọi API Layma.net tạo link rút gọn động (Có Fallback Quicklink Redirect chống chặn IP Render)
function createDynamicLaymaUrl(string $destinationUrl, string $fallbackUrl = ''): string {
    $apiToken = LAYMA_API_TOKEN;
    $originUrl = rawurlencode($destinationUrl);
    $duPhong = rawurlencode($fallbackUrl !== '' ? $fallbackUrl : getServerBaseUrl() . '/getkey.php');

    // 1. Thử lấy link rút gọn qua JSON (giả lập User-Agent trình duyệt)
    $apiUrlJson = "https://api.layma.net/api/admin/shortlink/quicklink?tokenUser={$apiToken}&format=json&url={$originUrl}&link_du_phong={$duPhong}";

    $raw = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($apiUrlJson);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 4,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => ['Accept: application/json, text/plain, */*'],
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => [
                'timeout'       => 4,
                'ignore_errors' => true,
                'header'        => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/124.0.0.0\r\nAccept: application/json\r\n"
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ]);
        $raw = @file_get_contents($apiUrlJson, false, $ctx);
    }

    if (is_string($raw) && $raw !== '') {
        $result = @json_decode(trim($raw), true);
        if (is_array($result) && !empty($result['success']) && !empty($result['html'])) {
            return trim((string)$result['html']);
        }
        if (preg_match('#^https?://layma\.net/[a-zA-Z0-9_-]+#i', trim($raw), $m)) {
            return $m[0];
        }
    }

    // 2. FALLBACK BẤT TỬ: Nếu IP của server Render bị Cloudflare của Layma chặn,
    // trả về trực tiếp Quicklink (format=redirect) để trình duyệt của người dùng tự mở và chuyển hướng!
    return "https://api.layma.net/api/admin/shortlink/quicklink?tokenUser={$apiToken}&format=redirect&url={$originUrl}&link_du_phong={$duPhong}";
}

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

// ================== VÉ CLAIM BỀN VỮNG (CÓ STEP TOKEN CHỐNG BYPASS) ==================
function initClaimTicketsTable(): void {
    $pdo = Database::getConnection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS keygen_tickets (
        ticket_id TEXT PRIMARY KEY,
        session_hash TEXT NOT NULL,
        ip TEXT NOT NULL,
        game TEXT NOT NULL,
        duration TEXT NOT NULL,
        duration_seconds BIGINT NOT NULL,
        layma_total INTEGER NOT NULL,
        layma_remaining INTEGER NOT NULL,
        stage TEXT NOT NULL,
        created_at BIGINT NOT NULL,
        updated_at BIGINT NOT NULL,
        link4m_started_at BIGINT NULL,
        layma_started_at BIGINT NULL,
        step_token TEXT DEFAULT '',
        current_short_url TEXT DEFAULT ''
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_keygen_tickets_owner ON keygen_tickets (session_hash, ip, created_at)");
    try { $pdo->exec("ALTER TABLE keygen_tickets ADD COLUMN step_token TEXT DEFAULT ''"); } catch (Throwable $e) {}
    try { $pdo->exec("ALTER TABLE keygen_tickets ADD COLUMN current_short_url TEXT DEFAULT ''"); } catch (Throwable $e) {}
}

function claimTicketSessionHash(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    return hash('sha256', session_id());
}

function createClaimTicket(string $ip, string $game, string $durationKey, array $durationCfg, string $fallbackShortlink = ''): array {
    initClaimTicketsTable();
    $pdo = Database::getConnection();
    $sessionHash = claimTicketSessionHash();
    $now = time();
    $ticketId = bin2hex(random_bytes(24));
    $stepToken = bin2hex(random_bytes(16));

    $callbackUrl = buildClaimCallbackUrl($stepToken);
    $shortUrl = createDynamicLink4mUrl($callbackUrl, $fallbackShortlink);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE keygen_tickets SET stage = 'superseded', updated_at = ?
            WHERE session_hash = ? AND ip = ?
            AND stage NOT IN ('completed', 'superseded', 'expired', 'invalid')");
        $stmt->execute([$now, $sessionHash, $ip]);

        $stmt = $pdo->prepare("INSERT INTO keygen_tickets
            (ticket_id, session_hash, ip, game, duration, duration_seconds, layma_total,
             layma_remaining, stage, created_at, updated_at, link4m_started_at, layma_started_at, step_token, current_short_url)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ready_link4m', ?, ?, NULL, NULL, ?, ?)");
        $stmt->execute([
            $ticketId, $sessionHash, $ip, $game, $durationKey,
            (int)$durationCfg['seconds'], (int)$durationCfg['layma_passes'],
            (int)$durationCfg['layma_passes'], $now, $now, $stepToken, $shortUrl
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    return [
        'ticket_id'         => $ticketId,
        'session_hash'      => $sessionHash,
        'ip'                => $ip,
        'game'              => $game,
        'duration'          => $durationKey,
        'duration_seconds'  => (int)$durationCfg['seconds'],
        'layma_total'       => (int)$durationCfg['layma_passes'],
        'layma_remaining'   => (int)$durationCfg['layma_passes'],
        'stage'             => 'ready_link4m',
        'created'           => $now,
        'updated_at'        => $now,
        'link4m_started_at' => null,
        'layma_started_at'  => null,
        'step_token'        => $stepToken,
        'current_short_url' => $shortUrl,
    ];
}

function getCurrentClaimTicket(string $ip): ?array {
    initClaimTicketsTable();
    $pdo = Database::getConnection();
    $sessionHash = claimTicketSessionHash();
    $stmt = $pdo->prepare("SELECT ticket_id, session_hash, ip, game, duration,
            duration_seconds, layma_total, layma_remaining, stage,
            created_at AS created, updated_at, link4m_started_at, layma_started_at,
            step_token, current_short_url
        FROM keygen_tickets
        WHERE session_hash = ? AND ip = ?
          AND stage NOT IN ('completed', 'superseded', 'expired', 'invalid')
        ORDER BY created_at DESC, ticket_id DESC LIMIT 1");
    $stmt->execute([$sessionHash, $ip]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ticket) return null;
    foreach (['duration_seconds', 'layma_total', 'layma_remaining', 'created', 'updated_at'] as $key) {
        $ticket[$key] = (int)($ticket[$key] ?? 0);
    }
    foreach (['link4m_started_at', 'layma_started_at'] as $key) {
        $ticket[$key] = ($ticket[$key] === null || $ticket[$key] === '') ? null : (int)$ticket[$key];
    }
    $ticket['step_token'] = (string)($ticket['step_token'] ?? '');
    $ticket['current_short_url'] = (string)($ticket['current_short_url'] ?? '');
    return $ticket;
}

function saveClaimTicket(array $ticket): bool {
    initClaimTicketsTable();
    $sessionHash = claimTicketSessionHash();
    if (empty($ticket['ticket_id']) || !hash_equals((string)($ticket['session_hash'] ?? ''), $sessionHash)) {
        return false;
    }
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("UPDATE keygen_tickets SET stage = ?, layma_remaining = ?,
            link4m_started_at = ?, layma_started_at = ?, step_token = ?, current_short_url = ?, updated_at = ?
        WHERE ticket_id = ? AND session_hash = ? AND ip = ?
          AND stage NOT IN ('completed', 'superseded', 'expired', 'invalid')");
    $stmt->execute([
        (string)$ticket['stage'], max(0, (int)($ticket['layma_remaining'] ?? 0)),
        $ticket['link4m_started_at'] ?? null, $ticket['layma_started_at'] ?? null,
        (string)($ticket['step_token'] ?? ''), (string)($ticket['current_short_url'] ?? ''),
        time(), (string)$ticket['ticket_id'], $sessionHash, (string)$ticket['ip']
    ]);
    return $stmt->rowCount() > 0;
}

function setClaimTicketStatus(array $ticket, string $status): void {
    $allowed = ['completed', 'expired', 'invalid', 'superseded'];
    if (!in_array($status, $allowed, true) || empty($ticket['ticket_id'])) return;
    initClaimTicketsTable();
    $sessionHash = claimTicketSessionHash();
    if (!hash_equals((string)($ticket['session_hash'] ?? ''), $sessionHash)) return;
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("UPDATE keygen_tickets SET stage = ?, updated_at = ?
        WHERE ticket_id = ? AND session_hash = ? AND ip = ?
        AND stage NOT IN ('completed', 'superseded', 'expired', 'invalid')");
    $stmt->execute([$status, time(), (string)$ticket['ticket_id'], $sessionHash, (string)$ticket['ip']]);
}

function supersedeCurrentClaimTicket(string $ip): void {
    initClaimTicketsTable();
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("UPDATE keygen_tickets SET stage = 'superseded', updated_at = ?
        WHERE session_hash = ? AND ip = ?
        AND stage NOT IN ('completed', 'superseded', 'expired', 'invalid')");
    $stmt->execute([time(), claimTicketSessionHash(), $ip]);
}

function cleanupClaimTickets(int $ttlSeconds = 86400): void {
    initClaimTicketsTable();
    if (random_int(1, 20) !== 1) return;
    $pdo = Database::getConnection();
    $pdo->prepare("DELETE FROM keygen_tickets WHERE created_at < ?")
        ->execute([time() - max(3600, $ttlSeconds)]);
}

function beginClaimTicketIssuance(array $ticket): bool {
    initClaimTicketsTable();
    $sessionHash = claimTicketSessionHash();
    if (empty($ticket['ticket_id']) || !hash_equals((string)($ticket['session_hash'] ?? ''), $sessionHash)) {
        return false;
    }
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("UPDATE keygen_tickets SET stage = 'issuing', updated_at = ?
        WHERE ticket_id = ? AND session_hash = ? AND ip = ? AND stage = 'ready_to_issue'");
    $stmt->execute([time(), (string)$ticket['ticket_id'], $sessionHash, (string)$ticket['ip']]);
    return $stmt->rowCount() === 1;
}

// ================== CẤU HÌNH QUA DATABASE ==================
function initKeygenConfigTables(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo = Database::getConnection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS keygen_apps_config (
        app_key TEXT PRIMARY KEY,
        label TEXT NOT NULL DEFAULT '',
        prefix TEXT NOT NULL DEFAULT '',
        shortlink TEXT NOT NULL DEFAULT '',
        download_url TEXT NOT NULL DEFAULT '',
        icon TEXT NOT NULL DEFAULT '',
        version TEXT NOT NULL DEFAULT '',
        description TEXT NOT NULL DEFAULT '',
        size TEXT NOT NULL DEFAULT '',
        platform TEXT NOT NULL DEFAULT '',
        sort_order INTEGER NOT NULL DEFAULT 0,
        enabled INTEGER NOT NULL DEFAULT 1,
        updated_at INTEGER NOT NULL DEFAULT 0
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS keygen_settings (
        skey TEXT PRIMARY KEY,
        svalue TEXT NOT NULL DEFAULT '',
        updated_at INTEGER NOT NULL DEFAULT 0
    )");
}

function loadKeygenAppsFromDb(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    initKeygenConfigTables();
    $pdo = Database::getConnection();
    $rows = $pdo->query("SELECT * FROM keygen_apps_config WHERE enabled = 1 ORDER BY sort_order ASC, app_key ASC")->fetchAll(PDO::FETCH_ASSOC);
    $apps = [];
    foreach ($rows as $r) {
        $apps[$r['app_key']] = [
            'label'       => (string)$r['label'],
            'prefix'      => (string)$r['prefix'],
            'shortlink'   => (string)$r['shortlink'],
            'downloadUrl' => (string)$r['download_url'],
            'icon'        => (string)$r['icon'],
            'version'     => (string)$r['version'],
            'desc'        => (string)$r['description'],
            'size'        => (string)$r['size'],
            'platform'    => (string)$r['platform'],
            'updated_at'  => (int)$r['updated_at'],
            'updated'     => ((int)$r['updated_at'] > 0) ? date('d/m', (int)$r['updated_at']) : '',
        ];
    }
    $cache = $apps;
    return $apps;
}

(function () use (&$KEYGEN_APPS) {
    try {
        $dbApps = loadKeygenAppsFromDb();
        if (!empty($dbApps)) $KEYGEN_APPS = $dbApps;
    } catch (Throwable $e) {}
})();

// Lọc URL an toàn (chỉ cho phép http://, https:// hoặc đường dẫn nội bộ /..., chặn javascript: XSS)
function sanitizeHttpUrl(string $url, bool $allowRelative = false): string {
    $url = trim($url);
    if ($url === '') return '';
    if ($allowRelative && str_starts_with($url, '/') && !str_starts_with($url, '//')) {
        return $url;
    }
    if (preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL)) {
        return $url;
    }
    return '';
}

function getKeygenSetting(string $key, string $default = ''): string {
    initKeygenConfigTables();
    $stmt = Database::getConnection()->prepare("SELECT svalue FROM keygen_settings WHERE skey = ?");
    $stmt->execute([$key]);
    $v = $stmt->fetchColumn();
    return ($v === false || $v === null) ? $default : (string)$v;
}

function setKeygenSetting(string $key, string $value): void {
    initKeygenConfigTables();
    if (in_array($key, ['tg_admin_url', 'tg_group_url'], true)) {
        $value = sanitizeHttpUrl($value, false);
        if ($value === '') return;
    }
    $pdo = Database::getConnection();
    $check = $pdo->prepare("SELECT 1 FROM keygen_settings WHERE skey = ?");
    $check->execute([$key]);
    if ($check->fetchColumn()) {
        $pdo->prepare("UPDATE keygen_settings SET svalue = ?, updated_at = ? WHERE skey = ?")->execute([$value, time(), $key]);
    } else {
        $pdo->prepare("INSERT INTO keygen_settings (skey, svalue, updated_at) VALUES (?, ?, ?)")->execute([$key, $value, time()]);
    }
}

function deleteKeygenSetting(string $key): void {
    initKeygenConfigTables();
    Database::getConnection()->prepare("DELETE FROM keygen_settings WHERE skey = ?")->execute([$key]);
}

function seedKeygenAppDefaults(): void {
    global $KEYGEN_APPS;
    $pdo = Database::getConnection();
    $check = $pdo->prepare("SELECT 1 FROM keygen_apps_config WHERE app_key = ?");
    $ins = $pdo->prepare("INSERT INTO keygen_apps_config (app_key, label, prefix, shortlink, sort_order, enabled, updated_at) VALUES (?, ?, ?, ?, ?, 1, 0)");
    $order = 0;
    foreach ($KEYGEN_APPS as $key => $cfg) {
        $check->execute([$key]);
        if (!$check->fetchColumn()) {
            $ins->execute([$key, $cfg['label'], $cfg['prefix'], $cfg['shortlink'], $order++]);
        }
    }
}

function listKeygenAppsAll(): array {
    initKeygenConfigTables();
    $pdo = Database::getConnection();
    if ((int)$pdo->query("SELECT COUNT(*) FROM keygen_apps_config")->fetchColumn() === 0) {
        seedKeygenAppDefaults();
    }
    return $pdo->query("SELECT * FROM keygen_apps_config ORDER BY sort_order ASC, app_key ASC")->fetchAll(PDO::FETCH_ASSOC);
}

function saveKeygenApp(array $in): bool {
    initKeygenConfigTables();
    $pdo = Database::getConnection();
    $appKey = strtolower(trim((string)($in['app_key'] ?? '')));
    $label = trim((string)($in['label'] ?? ''));
    if ($appKey === '' || !preg_match('/^[a-z0-9_-]{1,40}$/', $appKey)) return false;
    if ($label === '') return false;

    $v = [
        'shortlink'    => sanitizeHttpUrl((string)($in['shortlink'] ?? ''), false),
        'download_url' => sanitizeHttpUrl((string)($in['download_url'] ?? ''), false),
        'icon'         => sanitizeHttpUrl((string)($in['icon'] ?? ''), true),
        'version'      => trim((string)($in['version'] ?? '')),
        'description'  => trim((string)($in['description'] ?? '')),
        'size'         => trim((string)($in['size'] ?? '')),
        'platform'     => trim((string)($in['platform'] ?? '')),
        'sort_order'   => (int)($in['sort_order'] ?? 0),
        'enabled'      => empty($in['enabled']) ? 0 : 1,
    ];
    $now = time();

    $check = $pdo->prepare("SELECT 1 FROM keygen_apps_config WHERE app_key = ?");
    $check->execute([$appKey]);

    if ($check->fetchColumn()) {
        $pdo->prepare("UPDATE keygen_apps_config SET label=?, shortlink=?, download_url=?, icon=?, version=?, description=?, size=?, platform=?, sort_order=?, enabled=?, updated_at=? WHERE app_key=?")
            ->execute([$label, $v['shortlink'], $v['download_url'], $v['icon'], $v['version'], $v['description'], $v['size'], $v['platform'], $v['sort_order'], $v['enabled'], $now, $appKey]);
        return true;
    }

    $prefix = trim((string)($in['prefix'] ?? ''));
    if ($prefix === '') return false;
    $pdo->prepare("INSERT INTO keygen_apps_config (app_key, label, prefix, shortlink, download_url, icon, version, description, size, platform, sort_order, enabled, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$appKey, $label, $prefix, $v['shortlink'], $v['download_url'], $v['icon'], $v['version'], $v['description'], $v['size'], $v['platform'], $v['sort_order'], $v['enabled'], $now]);
    return true;
}

function deleteKeygenApp(string $appKey): void {
    initKeygenConfigTables();
    Database::getConnection()->prepare("DELETE FROM keygen_apps_config WHERE app_key = ?")->execute([trim($appKey)]);
}

function getKeygenConfigVersion(): int {
    initKeygenConfigTables();
    $pdo = Database::getConnection();
    $a = (int)$pdo->query("SELECT COALESCE(MAX(updated_at),0) FROM keygen_apps_config")->fetchColumn();
    $s = (int)$pdo->query("SELECT COALESCE(MAX(updated_at),0) FROM keygen_settings")->fetchColumn();
    return max($a, $s);
}
