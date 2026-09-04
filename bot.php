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
    sendTelegramMessage($chatId, "🚫 Bạn không có quyền sử dụng bot này.");
    exit;
}

// Lệnh /start hoặc /help
if (strpos($text, '/start') === 0 || strpos($text, '/help') === 0) {
    $help = "🤖 <b>Chào Admin!</b>\n\n";
    $help .= "Các lệnh khả dụng:\n";
    $help .= "➤ <code>/genkey &lt;prefix&gt; &lt;số_lượng&gt; &lt;số_ngày&gt; [hwid_limit]</code> - Tạo key\n";
    $help .= "➤ <code>/keys</code> - Danh sách key gần nhất\n";
    $help .= "➤ <code>/check &lt;key&gt;</code> - Xem thông tin key\n";
    $help .= "➤ <code>/delete &lt;key&gt;</code> - Xóa key\n";
    $help .= "➤ <code>/extend &lt;key&gt; &lt;số_ngày&gt;</code> - Gia hạn key\n";
    $help .= "➤ <code>/myid</code> - Lấy Chat ID\n";
    sendTelegramMessage($chatId, $help);
}
// Lệnh /myid
elseif (strpos($text, '/myid') === 0) {
    sendTelegramMessage($chatId, "🆔 Chat ID của bạn: <code>$chatId</code>");
    exit;
}
// Lệnh /genkey
elseif (strpos($text, '/genkey') === 0) {
    $parts = explode(' ', $text);
    if (count($parts) < 4) {
        sendTelegramMessage($chatId, "⚠️ Cú pháp: <code>/genkey &lt;prefix&gt; &lt;số_lượng&gt; &lt;số_ngày&gt; [hwid_limit]</code>");
        exit;
    }
    $prefix = $parts[1];
    $count = intval($parts[2]);
    $days = intval($parts[3]);
    $limit = isset($parts[4]) ? intval($parts[4]) : 1;
    $keys = createKeyBatch($prefix, $count, $days, $limit);
    $msg = "✅ Đã tạo <b>$count</b> key:\n\n";
    foreach ($keys as $k) {
        $msg .= "<code>$k</code>\n";
    }
    sendTelegramMessage($chatId, $msg);
}
// Lệnh /keys
elseif (strpos($text, '/keys') === 0) {
    $keys = listKeys(10);
    if (empty($keys)) {
        sendTelegramMessage($chatId, "📭 <b>Chưa có key nào.</b>");
        exit;
    }
    $msg = "📋 <b>Danh sách key gần nhất:</b>\n\n";
    foreach ($keys as $k) {
        $expire = $k['expires_at'] ?: 'Vĩnh viễn';
        $statusIcon = ($k['status'] === 'active') ? '✅' : '⛔';
        $msg .= "<code>{$k['key']}</code>\n";
        $msg .= "├─ Hết hạn: <i>$expire</i>\n";
        $msg .= "└─ Trạng thái: $statusIcon {$k['status']}\n\n";
    }
    sendTelegramMessage($chatId, $msg);
}
// Lệnh /check
elseif (strpos($text, '/check') === 0) {
    $key = trim(substr($text, 7));
    if (empty($key)) {
        sendTelegramMessage($chatId, "⚠️ Cú pháp: <code>/check &lt;key&gt;</code>");
        exit;
    }
    $keyData = getKeyByKey($key);
    if (!$keyData) {
        sendTelegramMessage($chatId, "❌ <b>Key không tồn tại.</b>");
        exit;
    }
    $msg = "🔑 <b>Thông tin key:</b>\n";
    $msg .= "<code>{$keyData['key']}</code>\n";
    $msg .= "├─ Trạng thái: <b>{$keyData['status']}</b>\n";
    $msg .= "├─ Hết hạn: <i>" . ($keyData['expires_at'] ?: 'Vĩnh viễn') . "</i>\n";
    $msg .= "├─ Giới hạn thiết bị: {$keyData['hwid_limit']}\n";
    $msg .= "├─ Đã dùng: {$keyData['times_used']} lần\n";
    $msg .= "└─ HWIDs đã dùng: <code>{$keyData['hwids']}</code>\n";
    sendTelegramMessage($chatId, $msg);
}
// Lệnh /delete
elseif (strpos($text, '/delete') === 0) {
    $key = trim(substr($text, 8));
    if (empty($key)) {
        sendTelegramMessage($chatId, "⚠️ Cú pháp: <code>/delete &lt;key&gt;</code>");
        exit;
    }
    $keyData = getKeyByKey($key);
    if (!$keyData) {
        sendTelegramMessage($chatId, "❌ <b>Key không tồn tại.</b>");
        exit;
    }
    deleteKey($keyData['id']);
    sendTelegramMessage($chatId, "✅ Đã xóa key: <code>$key</code>");
}
// Lệnh /extend
elseif (strpos($text, '/extend') === 0) {
    $parts = explode(' ', $text);
    if (count($parts) < 3) {
        sendTelegramMessage($chatId, "⚠️ Cú pháp: <code>/extend &lt;key&gt; &lt;số_ngày&gt;</code>");
        exit;
    }
    $key = $parts[1];
    $days = intval($parts[2]);
    $keyData = getKeyByKey($key);
    if (!$keyData) {
        sendTelegramMessage($chatId, "❌ <b>Key không tồn tại.</b>");
        exit;
    }
    $newExpire = date('Y-m-d H:i:s', time() + $days * 86400);
    updateKey($keyData['id'], ['expires_at' => $newExpire, 'status' => 'active']);
    sendTelegramMessage($chatId, "✅ Đã gia hạn key <code>$key</code> thêm <b>$days</b> ngày.");
}
// Lệnh không hợp lệ
else {
    sendTelegramMessage($chatId, "❌ Lệnh không hợp lệ. Gõ <code>/start</code> để xem hướng dẫn.");
}