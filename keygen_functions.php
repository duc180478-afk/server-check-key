<?php
// keygen_functions.php
// Các hàm phục vụ trang lấy key miễn phí (getkey + claim)
// + block cuối: cấu hình app/Telegram qua database (quản trị tại admin/config.php)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// ================== CẤU HÌNH ==================

// Mỗi app có prefix và Link4m riêng. URL Link4m hiện tại được giữ nguyên.
// ⚠ app_key + prefix là IDENTITY (client hardcode / nằm trong key text) — đã bị
//   khoá ở admin/config.php, chỉ label + phần marketing sửa qua web được.
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

// Thời hạn cho phép. 12h = 43.200 giây; 1 ngày = 86.400 giây; 2 ngày = 172.800 giây.
// Số lượt Layma được tính thêm sau khi đã hoàn tất Link4m.
 $KEYGEN_DURATIONS = [
    '12h' => [
        'label' => '12h',
        'seconds' => 43200,
        'layma_passes' => 0,
    ],
    '1d' => [
        'label' => '1 ngày',
        'seconds' => 86400,
        'layma_passes' => 1,
    ],
    '2d' => [
        'label' => '2 ngày',
        'seconds' => 172800,
        'layma_passes' => 2,
    ],
];

// Layma.net đã được cấu hình để chuyển về trang claim.
define('KEYGEN_LAYMA_URL', 'https://layma.net/jwxpdcTUo');

// Giới hạn: số key tối đa 1 IP được lấy trong 1 giờ.
define('KEYGEN_MAX_PER_HOUR', 3);

// Thời gian lưu log claim (giờ).
define('KEYGEN_LOG_TTL_HOURS', 24);

// Ngưỡng thời gian tối thiểu trước khi chấp nhận quay về từ từng link.
define('KEYGEN_MIN_CLAIM_SECONDS', 45);
define('KEYGEN_MIN_LAYMA_SECONDS', 45);

// Vé được phép dùng trong tối đa 30 phút để đủ thời gian qua cả hai hệ thống link.
define('KEYGEN_TICKET_TTL_SECONDS', 1800);

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

// ================== VÉ CLAIM BỀN VỮNG ==================
// Vé được lưu trong database, gắn với PHP session hiện tại và IP.
// Việc này tránh mất tiến trình nếu request quay về claim.php đi qua instance khác.
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
        layma_started_at BIGINT NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_keygen_tickets_owner ON keygen_tickets (session_hash, ip, created_at)");
}

function claimTicketSessionHash(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    return hash('sha256', session_id());
}

function createClaimTicket(string $ip, string $game, string $durationKey, array $durationCfg): array {
    initClaimTicketsTable();
    $pdo = Database::getConnection();
    $sessionHash = claimTicketSessionHash();
    $now = time();
    $ticketId = bin2hex(random_bytes(24));

    $pdo->beginTransaction();
    try {
        // Một flow đang chạy cho cùng session/IP. Generate mới thay thế vé cũ.
        $stmt = $pdo->prepare("UPDATE keygen_tickets SET stage = 'superseded', updated_at = ?
            WHERE session_hash = ? AND ip = ?
            AND stage NOT IN ('completed', 'superseded', 'expired', 'invalid')");
        $stmt->execute([$now, $sessionHash, $ip]);

        $stmt = $pdo->prepare("INSERT INTO keygen_tickets
            (ticket_id, session_hash, ip, game, duration, duration_seconds, layma_total,
             layma_remaining, stage, created_at, updated_at, link4m_started_at, layma_started_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ready_link4m', ?, ?, NULL, NULL)");
        $stmt->execute([
            $ticketId, $sessionHash, $ip, $game, $durationKey,
            (int)$durationCfg['seconds'], (int)$durationCfg['layma_passes'],
            (int)$durationCfg['layma_passes'], $now, $now
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    return [
        'ticket_id' => $ticketId,
        'session_hash' => $sessionHash,
        'ip' => $ip,
        'game' => $game,
        'duration' => $durationKey,
        'duration_seconds' => (int)$durationCfg['seconds'],
        'layma_total' => (int)$durationCfg['layma_passes'],
        'layma_remaining' => (int)$durationCfg['layma_passes'],
        'stage' => 'ready_link4m',
        'created' => $now,
        'updated_at' => $now,
        'link4m_started_at' => null,
        'layma_started_at' => null,
    ];
}

function getCurrentClaimTicket(string $ip): ?array {
    initClaimTicketsTable();
    $pdo = Database::getConnection();
    $sessionHash = claimTicketSessionHash();
    $stmt = $pdo->prepare("SELECT ticket_id, session_hash, ip, game, duration,
            duration_seconds, layma_total, layma_remaining, stage,
            created_at AS created, updated_at, link4m_started_at, layma_started_at
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
        link4m_started_at = ?, layma_started_at = ?, updated_at = ?
        WHERE ticket_id = ? AND session_hash = ? AND ip = ?
          AND stage NOT IN ('completed', 'superseded', 'expired', 'invalid')");
    $stmt->execute([
        (string)$ticket['stage'], max(0, (int)($ticket['layma_remaining'] ?? 0)),
        $ticket['link4m_started_at'] ?? null, $ticket['layma_started_at'] ?? null,
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
    // Chỉ một request duy nhất được phép chuyển vé ready_to_issue sang issuing.
    $stmt = $pdo->prepare("UPDATE keygen_tickets SET stage = 'issuing', updated_at = ?
        WHERE ticket_id = ? AND session_hash = ? AND ip = ? AND stage = 'ready_to_issue'");
    $stmt->execute([time(), (string)$ticket['ticket_id'], $sessionHash, (string)$ticket['ip']]);
    return $stmt->rowCount() === 1;
}

// ================== CẤU HÌNH QUA DATABASE ==================
// Quản trị tại admin/config.php (dùng auth admin sẵn có của folder admin).
// app_key + prefix bị khoá ở 2 lớp (UI disabled + saveKeygenApp bỏ qua) vì là identity
// mà client hardcode / nằm trong key text. Ngày "Cập nhật" trên thẻ = thời điểm Lưu.

function initKeygenConfigTables(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo = Database::getConnection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS keygen_apps_config (
        app_key        TEXT PRIMARY KEY,
        label          TEXT NOT NULL DEFAULT '',
        prefix         TEXT NOT NULL DEFAULT '',
        shortlink      TEXT NOT NULL DEFAULT '',
        download_url   TEXT NOT NULL DEFAULT '',
        icon           TEXT NOT NULL DEFAULT '',
        version        TEXT NOT NULL DEFAULT '',
        description    TEXT NOT NULL DEFAULT '',
        size           TEXT NOT NULL DEFAULT '',
        platform       TEXT NOT NULL DEFAULT '',
        sort_order     INTEGER NOT NULL DEFAULT 0,
        enabled        INTEGER NOT NULL DEFAULT 1,
        updated_at     INTEGER NOT NULL DEFAULT 0
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS keygen_settings (
        skey       TEXT PRIMARY KEY,
        svalue     TEXT NOT NULL DEFAULT '',
        updated_at INTEGER NOT NULL DEFAULT 0
    )");
}

// Toàn bộ app đang bật, lấy từ DB (trả [] nếu chưa từng cấu hình)
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

// Ghi đè $KEYGEN_APPS bằng cấu hình DB (nếu có) — mọi file require keygen_functions.php
// (getkey.php, getkey_action.php, claim...) tự dùng cấu hình mới, không sửa file nào.
// DB lỗi/chưa có gì → giữ nguyên mảng mặc định ở đầu file.
(function () use (&$KEYGEN_APPS) {
    try {
        $dbApps = loadKeygenAppsFromDb();
        if (!empty($dbApps)) $KEYGEN_APPS = $dbApps;
    } catch (Throwable $e) {
        // DB chưa sẵn sàng → dùng cấu hình mặc định trong file này
    }
})();

// ----- Settings chung (link Telegram...) -----
function getKeygenSetting(string $key, string $default = ''): string {
    initKeygenConfigTables();
    $stmt = Database::getConnection()->prepare("SELECT svalue FROM keygen_settings WHERE skey = ?");
    $stmt->execute([$key]);
    $v = $stmt->fetchColumn();
    return ($v === false || $v === null) ? $default : (string)$v;
}

function setKeygenSetting(string $key, string $value): void {
    initKeygenConfigTables();
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

// ----- Quản trị app -----
// Lần đầu mở trang quản trị: seed sẵn các app hiện có trong code để dễ sửa
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
        'shortlink'   => trim((string)($in['shortlink'] ?? '')),
        'download_url'=> trim((string)($in['download_url'] ?? '')),
        'icon'        => trim((string)($in['icon'] ?? '')),
        'version'     => trim((string)($in['version'] ?? '')),
        'description' => trim((string)($in['description'] ?? '')),
        'size'        => trim((string)($in['size'] ?? '')),
        'platform'    => trim((string)($in['platform'] ?? '')),
        'sort_order'  => (int)($in['sort_order'] ?? 0),
        'enabled'     => empty($in['enabled']) ? 0 : 1,
    ];
    $now = time(); // "Cập nhật" trên thẻ download tự nhảy theo thời điểm này

    $check = $pdo->prepare("SELECT 1 FROM keygen_apps_config WHERE app_key = ?");
    $check->execute([$appKey]);

    if ($check->fetchColumn()) {
        // App ĐÃ TỒN TẠI: app_key + prefix là identity (client hardcode / nằm trong
        // key text đã sinh) — KHÔNG ghi đè dù POST gửi gì. Chỉ label + marketing.
        $pdo->prepare("UPDATE keygen_apps_config SET label=?, shortlink=?, download_url=?, icon=?, version=?, description=?, size=?, platform=?, sort_order=?, enabled=?, updated_at=? WHERE app_key=?")
            ->execute([$label, $v['shortlink'], $v['download_url'], $v['icon'], $v['version'], $v['description'], $v['size'], $v['platform'], $v['sort_order'], $v['enabled'], $now, $appKey]);
        return true;
    }

    // App MỚI: cần prefix (chỉ áp dụng cho key sinh sau này)
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

// Phiên bản cấu hình — trang getkey poll getkey_config.php để tự refresh khi admin vừa lưu
function getKeygenConfigVersion(): int {
    initKeygenConfigTables();
    $pdo = Database::getConnection();
    $a = (int)$pdo->query("SELECT COALESCE(MAX(updated_at),0) FROM keygen_apps_config")->fetchColumn();
    $s = (int)$pdo->query("SELECT COALESCE(MAX(updated_at),0) FROM keygen_settings")->fetchColumn();
    return max($a, $s);
}
