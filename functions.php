<?php
// functions.php

require_once 'db.php';

function generateRandomString($length = 10) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $random = '';
    for ($i = 0; $i < $length; $i++) {
        $random .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $random;
}

function createKey($prefix, $expiresDays = null, $hwidLimit = 1, $note = '') {
    $pdo = Database::getConnection();
    $random = generateRandomString(12);
    $key = $prefix . '-' . $random;
    $createdAt = date('Y-m-d H:i:s');
    $expiresAt = null;
    if ($expiresDays !== null && $expiresDays > 0) {
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresDays * 86400);
    }
    $stmt = $pdo->prepare("INSERT INTO keys (key, prefix, created_at, expires_at, hwid_limit, hwids, status, note)
                           VALUES (?, ?, ?, ?, ?, '[]', 'active', ?)");
    $stmt->execute([$key, $prefix, $createdAt, $expiresAt, (int)$hwidLimit, $note]);
    return $key;
}

function createKeyBatch($prefix, $count, $expiresDays = null, $hwidLimit = 1, $note = '') {
    $keys = [];
    for ($i = 0; $i < $count; $i++) {
        $keys[] = createKey($prefix, $expiresDays, $hwidLimit, $note);
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
    $allowed = ['key', 'prefix', 'expires_at', 'hwid_limit', 'hwids', 'status', 'times_used', 'last_used_at', 'note'];
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
    // Fix SQL injection: bind tham số LIMIT
    $limit = max(1, min((int)$limit, 500));
    $stmt = $pdo->prepare("SELECT * FROM keys ORDER BY created_at DESC LIMIT :lim");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function validateKey($key, $hwid = null) {
    $keyData = getKeyByKey($key);
    if (!$keyData) {
        return ['status' => 'error', 'message' => 'Key không tồn tại!'];
    }

    // Kiểm tra hết hạn
    if ($keyData['expires_at'] !== null && strtotime($keyData['expires_at']) < time()) {
        updateKey($keyData['id'], ['status' => 'expired']);
        return ['status' => 'error', 'message' => 'Key đã hết hạn!'];
    }

    if ($keyData['status'] !== 'active') {
        return ['status' => 'error', 'message' => 'Key đã bị vô hiệu hóa!'];
    }

    // Cập nhật số lần sử dụng (không giới hạn thiết bị)
    updateKey($keyData['id'], [
        'times_used' => $keyData['times_used'] + 1,
        'last_used_at' => date('Y-m-d H:i:s')
    ]);

    return ['status' => 'success', 'message' => 'Key hợp lệ!'];
}

// Hàm xóa các key hết hạn, nhưng giữ lại các key có prefix vip (không phân biệt hoa thường)
function cleanupExpiredKeys() {
    $pdo = Database::getConnection();
    $now = date('Y-m-d H:i:s');

    // Cập nhật trạng thái expired cho tất cả key hết hạn (dùng prepared statement)
    $pdo->prepare("UPDATE keys SET status = 'expired'
                   WHERE expires_at IS NOT NULL AND expires_at < ? AND status != 'expired'")
        ->execute([$now]);

    // Xóa các key hết hạn, ngoại trừ key có prefix là 'vip'
    $stmt = $pdo->prepare("DELETE FROM keys
                           WHERE expires_at IS NOT NULL AND expires_at < ?
                           AND LOWER(COALESCE(prefix, '')) != 'vip'");
    $stmt->execute([$now]);

    return $stmt->rowCount();
}

// Hàm tính thời gian còn lại
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

// ===== RATE LIMIT (lưu DB, hoạt động cả trên Render) =====
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