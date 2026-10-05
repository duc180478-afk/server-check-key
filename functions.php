<?php
// functions.php

require_once 'db.php';

// ===== DANH SÁCH APP HỆ THỐNG (dùng chung: web / bot / admin) =====
// Thêm app mới ở đây — mọi nơi tự cập nhật theo
define('SYSTEM_APPS', [
    'fakelag' => ['label' => 'FakeLag',  'default_prefix' => 'FLFF'],
    'locmod' => [
        'label' => 'Loc Mod Proxy',
        'default_prefix' => 'LOCMOD',
    ],
    'proxy'   => ['label' => 'AOV ESP', 'default_prefix' => 'PRX'],
]);

// Lấy tên app hiển thị từ giá trị cột app
function getAppLabel($appKey) {
    $appKey = strtolower(trim((string)$appKey));
    if (isset(SYSTEM_APPS[$appKey])) {
        return SYSTEM_APPS[$appKey]['label'];
    }
    return 'Other';
}

function generateRandomString($length = 10) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $random = '';
    for ($i = 0; $i < $length; $i++) {
        $random .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $random;
}

function createKey($prefix, $expiresDays = null, $hwidLimit = 1, $note = '', $app = 'other') {
    @date_default_timezone_set('Asia/Ho_Chi_Minh');
    $pdo = Database::getConnection();
    $random = generateRandomString(12);
    $key = $prefix . '-' . $random;
    $createdAt = date('Y-m-d H:i:s');
    $expiresAt = null;
    if ($expiresDays !== null && $expiresDays > 0) {
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresDays * 86400);
    }
    $stmt = $pdo->prepare("INSERT INTO keys (key, prefix, created_at, expires_at, hwid_limit, hwids, status, note, app)
                           VALUES (?, ?, ?, ?, ?, '[]', 'active', ?, ?)");
    $stmt->execute([$key, $prefix, $createdAt, $expiresAt, (int)$hwidLimit, $note, $app]);
    return $key;
}

function createKeyBatch($prefix, $count, $expiresDays = null, $hwidLimit = 1, $note = '', $app = 'other') {
    $keys = [];
    for ($i = 0; $i < $count; $i++) {
        $keys[] = createKey($prefix, $expiresDays, $hwidLimit, $note, $app);
    }
    return $keys;
}

function getKeyByKey($key) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("SELECT * FROM keys WHERE key = ?");
    $stmt->execute([$key]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getKeyById($id) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("SELECT * FROM keys WHERE id = ?");
    $stmt->execute([(int)$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateKey($id, $fields) {
    $pdo = Database::getConnection();
    // Whitelist các cột được phép update (chống SQL injection qua tên cột)
    $allowed = ['key', 'prefix', 'expires_at', 'hwid_limit', 'hwids', 'status', 'times_used', 'last_used_at', 'note', 'app',
                'bound_hwid', 'session_token', 'last_ip', 'last_seen_at', 'ban_reason', 'anomaly_count'];
    $sets = [];
    $params = [];
    foreach ($fields as $col => $val) {
        if (!in_array($col, $allowed, true)) continue;
        $sets[] = "$col = ?";
        $params[] = $val;
    }
    if (empty($sets)) return false;
    $params[] = (int)$id;
    $sql = "UPDATE keys SET " . implode(', ', $sets) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return true;
}

function deleteKey($id) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("DELETE FROM keys WHERE id = ?");
    $stmt->execute([(int)$id]);
}

function listKeys($limit = 100) {
    $pdo = Database::getConnection();
    $limit = max(1, min((int)$limit, 500));
    $stmt = $pdo->prepare("SELECT * FROM keys ORDER BY created_at DESC LIMIT :lim");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Lấy danh sách key lọc theo cột app ('fakelag', 'proxy', null = tất cả)
function listKeysByApp($limit = 20, $app = null) {
    $pdo = Database::getConnection();
    $limit = max(1, min((int)$limit, 500));
    if ($app !== null && $app !== '') {
        $stmt = $pdo->prepare("SELECT * FROM keys WHERE app = :app ORDER BY created_at DESC LIMIT :lim");
        $stmt->bindValue(':app', $app, PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM keys ORDER BY created_at DESC LIMIT :lim");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function logKeyEvent($key, $hwid, $ip, $event, $detail = '') {
    try {
        $pdo = Database::getConnection();
        $pdo->prepare("INSERT INTO key_events (key_text, hwid, ip, event, detail, created_at) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$key, $hwid, $ip, $event, $detail, date('Y-m-d H:i:s')]);
    } catch (Exception $e) { /* ignore */ }
}

function revokeKey($keyData, $reason) {
    updateKey($keyData['id'], [
        'status' => 'revoked',
        'ban_reason' => $reason,
        'session_token' => null,
    ]);
    logKeyEvent($keyData['key'] ?? '', $keyData['bound_hwid'] ?? '', getClientIp(), 'revoke', $reason);
}

function consumeNonce($nonce, $ttlSeconds = 120) {
    if (!$nonce || strlen($nonce) < 8 || strlen($nonce) > 128) return false;
    $pdo = Database::getConnection();
    $now = date('Y-m-d H:i:s');
    try {
        $pdo->prepare("INSERT INTO used_nonces (nonce, created_at) VALUES (?, ?)")->execute([$nonce, $now]);
    } catch (PDOException $e) {
        return false; // replay
    }
    if (random_int(1, 30) === 1) {
        $pdo->prepare("DELETE FROM used_nonces WHERE created_at < ?")
            ->execute([date('Y-m-d H:i:s', time() - $ttlSeconds * 10)]);
    }
    return true;
}

/**
 * Phase 0: device-binding + single-session + anomaly.
 * Returns [resultArray, sessionToken|null]
 */
function validateKey($key, $hwid = null, $opts = []) {
    $ip = getClientIp();
    $keyData = getKeyByKey($key);
    if (!$keyData) {
        logKeyEvent($key, $hwid, $ip, 'unknown_key', '');
        return ['status' => 'error', 'message' => 'Key không tồn tại!', 'valid' => false];
    }

    if ($keyData['expires_at'] !== null && strtotime($keyData['expires_at']) < time()) {
        updateKey($keyData['id'], ['status' => 'expired']);
        return ['status' => 'error', 'message' => 'Key đã hết hạn!', 'valid' => false];
    }

    if ($keyData['status'] !== 'active') {
        $msg = 'Key đã bị vô hiệu hóa!';
        if (!empty($keyData['ban_reason'])) $msg = 'Key bị khóa: ' . $keyData['ban_reason'];
        return ['status' => 'error', 'message' => $msg, 'valid' => false];
    }

    $hwid = is_string($hwid) ? trim($hwid) : '';
    $limit = max(1, (int)($keyData['hwid_limit'] ?? 1));

    // Device binding: lần đầu gắn HWID, lần sau phải khớp
    $bound = $keyData['bound_hwid'] ?? '';
    if ($hwid !== '') {
        if ($bound === null || $bound === '') {
            updateKey($keyData['id'], ['bound_hwid' => $hwid]);
            $bound = $hwid;
            logKeyEvent($key, $hwid, $ip, 'bind', 'first device');
        } elseif ($bound !== $hwid) {
            $cnt = (int)($keyData['anomaly_count'] ?? 0) + 1;
            updateKey($keyData['id'], ['anomaly_count' => $cnt]);
            logKeyEvent($key, $hwid, $ip, 'hwid_mismatch', "bound=$bound new=$hwid");
            if ($limit <= 1 || $cnt >= 3) {
                revokeKey($keyData, 'multi-device');
                return ['status' => 'error', 'message' => 'Key đã gắn thiết bị khác!', 'valid' => false, 'kicked' => true];
            }
            return ['status' => 'error', 'message' => 'HWID không khớp!', 'valid' => false];
        }
    }

    // Anomaly: chỉ flag khi CÙNG LÚC nhiều HWID khác nhau (không phải cùng device đổi IP)
    // User VPN/4G rotate IP là bình thường; khác device mới đáng ngờ.
    try {
        $pdo = Database::getConnection();
        $since = date('Y-m-d H:i:s', time() - 300);
        $st = $pdo->prepare(
            "SELECT COUNT(DISTINCT hwid) FROM key_events
             WHERE key_text = ? AND created_at > ?
               AND event IN ('ok','heartbeat','bind')
               AND hwid IS NOT NULL AND hwid != ''"
        );
        $st->execute([$key, $since]);
        $hwidCount = (int)$st->fetchColumn();
        if ($hwidCount >= 3) {
            revokeKey($keyData, 'multi-device-window');
            return ['status' => 'error', 'message' => 'Phát hiện bất thường (nhiều thiết bị).', 'valid' => false];
        }
    } catch (Exception $e) { /* ignore */ }

    $sessionToken = bin2hex(random_bytes(16));
    $prevSession = $keyData['session_token'] ?? '';
    $kicked = ($prevSession !== '' && $prevSession !== ($opts['old_session'] ?? ''));

    updateKey($keyData['id'], [
        'times_used' => ((int)$keyData['times_used']) + 1,
        'last_used_at' => date('Y-m-d H:i:s'),
        'last_ip' => $ip,
        'last_seen_at' => date('Y-m-d H:i:s'),
        'session_token' => $sessionToken,
    ]);
    logKeyEvent($key, $hwid, $ip, 'ok', $kicked ? 'kick_old' : 'login');

    return [
        'status' => 'success',
        'message' => $kicked ? 'Key hợp lệ (phiên cũ đã bị kick).' : 'Key hợp lệ!',
        'valid' => true,
        'session' => $sessionToken,
        'kicked_old' => $kicked,
        'bound_hwid' => $bound ?: $hwid,
    ];
}

function verifySession($key, $hwid, $session) {
    $keyData = getKeyByKey($key);
    if (!$keyData || $keyData['status'] !== 'active') return false;
    if ($keyData['expires_at'] !== null && strtotime($keyData['expires_at']) < time()) return false;
    if (!empty($keyData['bound_hwid']) && $hwid && $keyData['bound_hwid'] !== $hwid) return false;
    if (!empty($keyData['session_token']) && $session && !hash_equals($keyData['session_token'], $session)) return false;
    updateKey($keyData['id'], ['last_seen_at' => date('Y-m-d H:i:s'), 'last_ip' => getClientIp()]);
    return true;
}

// Xóa các key hết hạn, giữ lại các key có prefix vip (không phân biệt hoa thường)
function cleanupExpiredKeys() {
    $pdo = Database::getConnection();
    $now = date('Y-m-d H:i:s');

    $pdo->prepare("UPDATE keys SET status = 'expired'
                   WHERE expires_at IS NOT NULL AND expires_at < ? AND status != 'expired'")
        ->execute([$now]);

    $stmt = $pdo->prepare("DELETE FROM keys
                           WHERE expires_at IS NOT NULL AND expires_at < ?
                           AND LOWER(COALESCE(prefix, '')) != 'vip'");
    $stmt->execute([$now]);

    return $stmt->rowCount();
}

// Tính thời gian còn lại
function getTimeRemaining($expiresAt) {
    if (empty($expiresAt)) {
        return 'Vĩnh viễn';
    }
    $expireTime = strtotime($expiresAt);
    $now = time();
    if ($expireTime <= $now) {
        return 'Đã hết hạn';
    }
    $diff = $expireTime - $now;
    $days = floor($diff / 86400);
    $hours = floor(($diff % 86400) / 3600);
    $minutes = floor(($diff % 3600) / 60);
    if ($days > 0) {
        return "Còn $days ngày $hours giờ";
    } elseif ($hours > 0) {
        return "Còn $hours giờ $minutes phút";
    } else {
        return "Còn $minutes phút";
    }
}

function setBotState($chatId, $state, $data = null) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("INSERT INTO bot_states (chat_id, state, data) VALUES (?, ?, ?)
                           ON CONFLICT (chat_id) DO UPDATE SET state = EXCLUDED.state, data = EXCLUDED.data");
    $stmt->execute([$chatId, $state, $data]);
}

function getBotState($chatId) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("SELECT state, data FROM bot_states WHERE chat_id = ?");
    $stmt->execute([$chatId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}

function clearBotState($chatId) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("DELETE FROM bot_states WHERE chat_id = ?");
    $stmt->execute([$chatId]);
}

function sendTelegramMessage($chatId, $text, $keyboard = null) {
    if (!defined('TELEGRAM_BOT_TOKEN') || TELEGRAM_BOT_TOKEN === 'YOUR_BOT_TOKEN') {
        return false;
    }
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    if ($keyboard !== null) {
        $data['reply_markup'] = json_encode($keyboard);
    }
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    $context  = stream_context_create($options);
    @file_get_contents($url, false, $context);
}

// ===== CSRF TOKEN =====
function csrfToken() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

function verifyCsrfToken($token) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

// ===== RATE LIMIT (lưu DB) =====
function getClientIp() {
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $h) {
        if (!empty($_SERVER[$h])) {
            $ips = explode(',', $_SERVER[$h]);
            return trim($ips[0]);
        }
    }
    return 'unknown';
}

function checkRateLimit($identifier, $maxRequests = 30, $windowSeconds = 60) {
    $pdo = Database::getConnection();
    $now = time();
    $windowStart = date('Y-m-d H:i:s', $now - $windowSeconds);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE identifier = ? AND created_at > ?");
    $stmt->execute([$identifier, $windowStart]);
    if ((int)$stmt->fetchColumn() >= $maxRequests) {
        return false;
    }
    $pdo->prepare("INSERT INTO rate_limits (identifier, created_at) VALUES (?, ?)")
        ->execute([$identifier, date('Y-m-d H:i:s', $now)]);
    // Thỉnh thoảng dọn bản ghi cũ
    if (random_int(1, 20) === 1) {
        $pdo->prepare("DELETE FROM rate_limits WHERE created_at < ?")
            ->execute([date('Y-m-d H:i:s', $now - 3600)]);
    }
    return true;
}