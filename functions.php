<?php
// functions.php

require_once 'db.php';

// ===== DANH SÁCH APP HỆ THỐNG (dùng chung: web / bot / admin) =====
define('SYSTEM_APPS', [
 'fakelag' => ['label' => 'FakeLag / FakePing', 'default_prefix' => 'FLFF'],
 'locmod' => ['label' => 'Loc Mod Proxy', 'default_prefix' => 'LOCMOD'],
 'modskin' => ['label' => 'Mod Skin FF', 'default_prefix' => 'MODSKIN'],
 'proxy' => ['label' => 'AOV ESP', 'default_prefix' => 'PRX'],
]);

// Lấy tên app hiển thị từ giá trị cột app
function getAppLabel($appKey) {
 $appKey = strtolower(trim((string)$appKey));
 if (isset(SYSTEM_APPS[$appKey])) {
 return SYSTEM_APPS[$appKey]['label'];
 }
 return 'Other';
}

// FF product family: FakePing (fakelag) + LocMod + Mod Skin share keys.
function is_ff_family($app) {
    $a = strtolower(trim((string)$app));
    return ($a === 'fakelag' || $a === 'locmod' || $a === 'modskin');
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
 $allowed = ['key', 'prefix', 'expires_at', 'hwid_limit', 'hwids', 'status', 'times_used', 'last_used_at', 'note', 'app',
 'bound_hwid', 'session_token', 'last_ip', 'last_seen_at', 'ban_reason', 'anomaly_count'];
 $sets = [];
 $params = [];
 foreach ($fields as $k => $v) {
 if (in_array($k, $allowed, true)) {
 $sets[] = "$k = ?";
 $params[] = $v;
 }
 }
 if (empty($sets)) return false;
 $params[] = (int)$id;
 $stmt = $pdo->prepare("UPDATE keys SET " . implode(', ', $sets) . " WHERE id = ?");
 return $stmt->execute($params);
}

function validateKey($key, $hwid = '', $opts = []) {
 $keyData = getKeyByKey($key);
 if (!$keyData) {
 return ['status' => 'error', 'message' => 'Key không tồn tại!', 'valid' => false];
 }
 if ($keyData['status'] === 'banned' || $keyData['status'] === 'revoked') {
 return ['status' => 'error', 'message' => 'Key đã bị khóa!', 'valid' => false, 'ban_reason' => $keyData['ban_reason'] ?? ''];
 }
 if ($keyData['status'] !== 'active') {
 return ['status' => 'error', 'message' => 'Key không còn hiệu lực!', 'valid' => false];
 }
 if ($keyData['expires_at'] !== null && strtotime($keyData['expires_at']) < time()) {
 updateKey($keyData['id'], ['status' => 'expired']);
 return ['status' => 'error', 'message' => 'Key đã hết hạn!', 'valid' => false];
 }

 $bound = $keyData['bound_hwid'] ?? '';
 $kicked = false;
 $sessionToken = $keyData['session_token'] ?? '';

 if ($hwid !== '') {
 if ($bound === '') {
 // Bind lần đầu
 $sessionToken = bin2hex(random_bytes(16));
 updateKey($keyData['id'], [
 'bound_hwid' => $hwid,
 'session_token' => $sessionToken,
 'last_seen_at' => date('Y-m-d H:i:s'),
 'last_ip' => getClientIp(),
 'times_used' => ((int)($keyData['times_used'] ?? 0)) + 1,
 'last_used_at' => date('Y-m-d H:i:s'),
 ]);
 } elseif ($bound !== $hwid) {
 // HWID khác → đá phiên cũ
 $oldSession = $opts['old_session'] ?? '';
 $sessionToken = bin2hex(random_bytes(16));
 updateKey($keyData['id'], [
 'bound_hwid' => $hwid,
 'session_token' => $sessionToken,
 'last_seen_at' => date('Y-m-d H:i:s'),
 'last_ip' => getClientIp(),
 'times_used' => ((int)($keyData['times_used'] ?? 0)) + 1,
 'last_used_at' => date('Y-m-d H:i:s'),
 ]);
 $kicked = true;
 } else {
 // Cùng HWID
 if (empty($sessionToken)) {
 $sessionToken = bin2hex(random_bytes(16));
 }
 updateKey($keyData['id'], [
 'session_token' => $sessionToken,
 'last_seen_at' => date('Y-m-d H:i:s'),
 'last_ip' => getClientIp(),
 'times_used' => ((int)($keyData['times_used'] ?? 0)) + 1,
 'last_used_at' => date('Y-m-d H:i:s'),
 ]);
 }
 }

 return [
 'status' => 'success',
 'message' => $kicked ? 'Key hợp lệ! (đã đá phiên cũ)' : 'Key hợp lệ!',
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
 if (!defined('TELEGRAM_BOT_TOKEN') || TELEGRAM_BOT_TOKEN === '' || TELEGRAM_BOT_TOKEN === 'YOUR_BOT_TOKEN') {
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
 'header' => "Content-type: application/x-www-form-urlencoded\r\n",
 'method' => 'POST',
 'content' => http_build_query($data)
 ]
 ];
 $context = stream_context_create($options);
 @file_get_contents($url, false, $context);
}

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

// ===== RATE LIMIT (Bảo vệ chống giả mạo IP trên Render) =====
function getClientIp() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        $realIp = end($ips);
        if (filter_var($realIp, FILTER_VALIDATE_IP)) return $realIp;
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = trim($_SERVER['HTTP_X_REAL_IP']);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
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
 if (random_int(1, 20) === 1) {
 $pdo->prepare("DELETE FROM rate_limits WHERE created_at < ?")
 ->execute([date('Y-m-d H:i:s', $now - 3600)]);
 }
 return true;
}

function consumeNonce($nonce) {
 if ($nonce === '' || strlen($nonce) > 64) return false;
 $pdo = Database::getConnection();
 try {
 $pdo->prepare("INSERT INTO used_nonces (nonce, created_at) VALUES (?, ?)")
 ->execute([$nonce, date('Y-m-d H:i:s')]);
 // cleanup cũ
 if (random_int(1, 30) === 1) {
 $pdo->prepare("DELETE FROM used_nonces WHERE created_at < ?")
 ->execute([date('Y-m-d H:i:s', time() - 3600)]);
 }
 return true;
 } catch (PDOException $e) {
 return false; // đã dùng
 }
}