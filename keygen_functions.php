<?php
// keygen_functions.php
// Các hàm phục vụ trang lấy key miễn phí (getkey + claim)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// ================== CẤU HÌNH ==================

// Mỗi app có prefix và Link4m riêng. URL Link4m hiện tại được giữ nguyên.
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
