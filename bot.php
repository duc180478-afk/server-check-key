<?php
// bot.php

require_once 'config.php';
require_once 'functions.php';

$content = file_get_contents('php://input');
$update = json_decode($content, true);

if (!$update || !isset($update['message'])) {
    http_response_code(200);
    exit;
}

$message = $update['message'];
$chatId = $message['chat']['id'];
$text = trim($message['text'] ?? '');

// Kiểm tra admin
if ($chatId != TELEGRAM_ADMIN_CHAT_ID) {
    sendTelegramMessage($chatId, "Bạn không có quyền sử dụng bot này.");
    exit;
}

// Xử lý lệnh
if (strpos($text, '/start') === 0) {
    $help = "Chào Admin! Các lệnh:\n";
    $help .= "/genkey <prefix> <số_lượng> <số_ngày> [hwid_limit] - Tạo key\n";
    $help .= "/keys - Liệt kê 10 key gần nhất\n";
    $help .= "/check <key> - Xem thông tin key\n";
    $help .= "/delete <key> - Xóa key\n";
    $help .= "/extend <key> <số_ngày> - Gia hạn key\n";
    sendTelegramMessage($chatId, $help);
}
elseif (strpos($text, '/genkey') === 0) {
    $parts = explode(' ', $text);
    if (count($parts) < 4) {
        sendTelegramMessage($chatId, "Cú pháp: /genkey <prefix> <số_lượng> <số_ngày> [hwid_limit]");
        exit;
    }
    $prefix = $parts[1];
    $count = intval($parts[2]);
    $days = intval($parts[3]);
    $limit = isset($parts[4]) ? intval($parts[4]) : 1;
    $keys = createKeyBatch($prefix, $count, $days, $limit);
    $msg = "Đã tạo $count key:\n" . implode("\n", $keys);
    sendTelegramMessage($chatId, $msg);
}
elseif (strpos($text, '/keys') === 0) {
    $keys = listKeys(10);
    if (empty($keys)) {
        sendTelegramMessage($chatId, "Chưa có key nào.");
        exit;
    }
    $msg = "Danh sách key gần nhất:\n";
    foreach ($keys as $k) {
        $expire = $k['expires_at'] ?: 'Vĩnh viễn';
        $msg .= "{$k['key']} | Hết hạn: $expire | Status: {$k['status']}\n";
    }
    sendTelegramMessage($chatId, $msg);
}
elseif (strpos($text, '/check') === 0) {
    $key = substr($text, 7);
    $keyData = getKeyByKey($key);
    if (!$keyData) {
        sendTelegramMessage($chatId, "Key không tồn tại.");
        exit;
    }
    $msg = "Key: {$keyData['key']}\n";
    $msg .= "Trạng thái: {$keyData['status']}\n";
    $msg .= "Hết hạn: " . ($keyData['expires_at'] ?: 'Vĩnh viễn') . "\n";
    $msg .= "Giới hạn thiết bị: {$keyData['hwid_limit']}\n";
    $msg .= "Đã dùng: {$keyData['times_used']} lần\n";
    $msg .= "HWIDs: {$keyData['hwids']}\n";
    sendTelegramMessage($chatId, $msg);
}
elseif (strpos($text, '/delete') === 0) {
    $key = substr($text, 8);
    $keyData = getKeyByKey($key);
    if (!$keyData) {
        sendTelegramMessage($chatId, "Key không tồn tại.");
        exit;
    }
    deleteKey($keyData['id']);
    sendTelegramMessage($chatId, "Đã xóa key: $key");
}
elseif (strpos($text, '/extend') === 0) {
    $parts = explode(' ', $text);
    if (count($parts) < 3) {
        sendTelegramMessage($chatId, "Cú pháp: /extend <key> <số_ngày>");
        exit;
    }
    $key = $parts[1];
    $days = intval($parts[2]);
    $keyData = getKeyByKey($key);
    if (!$keyData) {
        sendTelegramMessage($chatId, "Key không tồn tại.");
        exit;
    }
    $newExpire = date('Y-m-d H:i:s', time() + $days * 86400);
    updateKey($keyData['id'], ['expires_at' => $newExpire, 'status' => 'active']);
    sendTelegramMessage($chatId, "Đã gia hạn key $key thêm $days ngày.");
}
else {
    sendTelegramMessage($chatId, "Lệnh không hợp lệ. Gõ /start để xem hướng dẫn.");
}