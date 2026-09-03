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
    $stmt->execute([$key, $prefix, $createdAt, $expiresAt, $hwidLimit, $note]);
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
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function updateKey($id, $fields) {
    $pdo = Database::getConnection();
    $sets = [];
    $params = [];
    foreach ($fields as $col => $val) {
        $sets[] = "$col = ?";
        $params[] = $val;
    }
    $params[] = $id;
    $sql = "UPDATE keys SET " . implode(', ', $sets) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

function deleteKey($id) {
    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("DELETE FROM keys WHERE id = ?");
    $stmt->execute([$id]);
}

function listKeys($limit = 100) {
    $pdo = Database::getConnection();
    $stmt = $pdo->query("SELECT * FROM keys ORDER BY created_at DESC LIMIT $limit");
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

    // Tăng số lần sử dụng (không cần hwid)
    updateKey($keyData['id'], [
        'times_used' => $keyData['times_used'] + 1,
        'last_used_at' => date('Y-m-d H:i:s')
    ]);

    return ['status' => 'success', 'message' => 'Key hợp lệ!'];
}

function sendTelegramMessage($chatId, $text) {
    if (!defined('TELEGRAM_BOT_TOKEN') || TELEGRAM_BOT_TOKEN === 'YOUR_BOT_TOKEN') {
        return false;
    }
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    $context  = stream_context_create($options);
    file_get_contents($url, false, $context);
}