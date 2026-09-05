<?php
// bot.php

require_once 'config.php';
require_once 'functions.php';

$content = file_get_contents('php://input');
$update = json_decode($content, true);

// Xử lý callback query (nút bấm)
if (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $chatId = $callback['message']['chat']['id'];
    $data = $callback['data'];

    // Kiểm tra admin
    if (!in_array($chatId, TELEGRAM_ADMIN_CHAT_IDS)) {
        sendTelegramMessage($chatId, "🚫 Bạn không có quyền sử dụng bot này.");
        exit;
    }

    // Xử lý các callback
    switch ($data) {
        case 'menu':
            sendMainMenu($chatId);
            break;

        case 'genkey':
            setBotState($chatId, 'awaiting_genkey');
            sendTelegramMessage($chatId, "🔑 <b>Tạo key mới</b>\n\nVui lòng nhập theo cú pháp:\n<code>&lt;prefix&gt; &lt;số ngày&gt; &lt;số thiết bị&gt;</code>\n\nVí dụ: <code>VIP 30 1</code>");
            break;

        case 'keys':
            showKeysList($chatId);
            break;

        case 'check':
            setBotState($chatId, 'awaiting_check');
            sendTelegramMessage($chatId, "🔍 <b>Kiểm tra key</b>\n\nVui lòng nhập key cần kiểm tra:");
            break;

        case 'delete':
            showDeleteMenu($chatId);
            break;

        case 'extend':
            showExtendMenu($chatId);
            break;

        default:
            // Xử lý callback delete_<id> hoặc extend_<id>
            if (strpos($data, 'delete_') === 0) {
                $id = intval(substr($data, 7));
                $keyData = getKeyById($id);
                if ($keyData) {
                    deleteKey($id);
                    sendTelegramMessage($chatId, "✅ Đã xóa key: <code>{$keyData['key']}</code>");
                    sendMainMenu($chatId);
                } else {
                    sendTelegramMessage($chatId, "❌ Key không tồn tại.");
                }
            } elseif (strpos($data, 'extend_') === 0) {
                $id = intval(substr($data, 7));
                $keyData = getKeyById($id);
                if ($keyData) {
                    setBotState($chatId, "awaiting_extend_$id");
                    sendTelegramMessage($chatId, "⏳ <b>Gia hạn key</b>\n\nKey: <code>{$keyData['key']}</code>\n\nVui lòng nhập số ngày muốn gia hạn:");
                } else {
                    sendTelegramMessage($chatId, "❌ Key không tồn tại.");
                }
            } else {
                sendTelegramMessage($chatId, "❌ Lệnh không hợp lệ.");
            }
            break;
    }
    exit;
}

// Xử lý tin nhắn thường
if (!isset($update['message'])) {
    exit;
}

$message = $update['message'];
$chatId = $message['chat']['id'];
$text = trim($message['text'] ?? '');

// Kiểm tra admin
if (!in_array($chatId, TELEGRAM_ADMIN_CHAT_IDS)) {
    sendTelegramMessage($chatId, "🚫 Bạn không có quyền sử dụng bot này.");
    exit;
}

// Kiểm tra nếu có trạng thái chờ
$state = getBotState($chatId);
if ($state) {
    $stateName = $state['state'];
    $stateData = $state['data'];

    switch ($stateName) {
        case 'awaiting_genkey':
            // Parse input: prefix days limit
            $parts = explode(' ', $text);
            if (count($parts) < 3) {
                sendTelegramMessage($chatId, "⚠️ Cú pháp không đúng. Vui lòng nhập: <code>&lt;prefix&gt; &lt;số ngày&gt; &lt;số thiết bị&gt;</code>");
                break;
            }
            $prefix = $parts[0];
            $days = intval($parts[1]);
            $limit = intval($parts[2]);
            $key = createKey($prefix, $days, $limit);
            clearBotState($chatId);
            sendTelegramMessage($chatId, "✅ Đã tạo key:\n<code>$key</code>\n\nHết hạn: " . ($days > 0 ? date('Y-m-d H:i:s', time() + $days * 86400) : 'Vĩnh viễn'));
            sendMainMenu($chatId);
            break;

        case 'awaiting_check':
            $keyData = getKeyByKey($text);
            if (!$keyData) {
                sendTelegramMessage($chatId, "❌ Key không tồn tại.");
            } else {
                $msg = "🔑 <b>Thông tin key:</b>\n";
                $msg .= "<code>{$keyData['key']}</code>\n";
                $msg .= "├─ Trạng thái: <b>{$keyData['status']}</b>\n";
                $msg .= "├─ Hết hạn: <i>" . ($keyData['expires_at'] ?: 'Vĩnh viễn') . "</i>\n";
                $msg .= "├─ Thời gian còn lại: " . getTimeRemaining($keyData['expires_at']) . "\n";
                $msg .= "├─ Giới hạn thiết bị: {$keyData['hwid_limit']}\n";
                $msg .= "├─ Đã dùng: {$keyData['times_used']} lần\n";
                $msg .= "└─ HWIDs đã dùng: <code>{$keyData['hwids']}</code>\n";
                sendTelegramMessage($chatId, $msg);
            }
            clearBotState($chatId);
            sendMainMenu($chatId);
            break;

        default:
            if (strpos($stateName, 'awaiting_extend_') === 0) {
                $id = intval(substr($stateName, 15));
                $days = intval($text);
                $keyData = getKeyById($id);
                if ($keyData && $days > 0) {
                    $newExpire = date('Y-m-d H:i:s', time() + $days * 86400);
                    updateKey($id, ['expires_at' => $newExpire, 'status' => 'active']);
                    sendTelegramMessage($chatId, "✅ Đã gia hạn key <code>{$keyData['key']}</code> thêm <b>$days</b> ngày.\nHết hạn mới: $newExpire");
                } else {
                    sendTelegramMessage($chatId, "❌ Số ngày không hợp lệ hoặc key không tồn tại.");
                }
                clearBotState($chatId);
                sendMainMenu($chatId);
            } else {
                clearBotState($chatId);
                sendTelegramMessage($chatId, "❌ Trạng thái không hợp lệ, đã hủy.");
                sendMainMenu($chatId);
            }
            break;
    }
    exit;
}

// Xử lý các lệnh text cũ (tương thích)
if (strpos($text, '/start') === 0 || strpos($text, '/help') === 0 || strpos($text, '/menu') === 0) {
    sendMainMenu($chatId);
} elseif (strpos($text, '/myid') === 0) {
    sendTelegramMessage($chatId, "🆔 Chat ID của bạn: <code>$chatId</code>");
} elseif (strpos($text, '/genkey') === 0) {
    $parts = explode(' ', $text);
    if (count($parts) < 4) {
        sendTelegramMessage($chatId, "⚠️ Cú pháp: <code>/genkey &lt;prefix&gt; &lt;số ngày&gt; &lt;số thiết bị&gt;</code>");
        exit;
    }
    $prefix = $parts[1];
    $days = intval($parts[2]);
    $limit = intval($parts[3]);
    $key = createKey($prefix, $days, $limit);
    sendTelegramMessage($chatId, "✅ Đã tạo key:\n<code>$key</code>");
} elseif (strpos($text, '/keys') === 0) {
    showKeysList($chatId);
} elseif (strpos($text, '/check') === 0) {
    $key = trim(substr($text, 7));
    if (empty($key)) {
        sendTelegramMessage($chatId, "⚠️ Cú pháp: <code>/check &lt;key&gt;</code>");
        exit;
    }
    $keyData = getKeyByKey($key);
    if (!$keyData) {
        sendTelegramMessage($chatId, "❌ Key không tồn tại.");
    } else {
        $msg = "🔑 <b>Thông tin key:</b>\n";
        $msg .= "<code>{$keyData['key']}</code>\n";
        $msg .= "├─ Trạng thái: <b>{$keyData['status']}</b>\n";
        $msg .= "├─ Hết hạn: <i>" . ($keyData['expires_at'] ?: 'Vĩnh viễn') . "</i>\n";
        $msg .= "├─ Thời gian còn lại: " . getTimeRemaining($keyData['expires_at']) . "\n";
        $msg .= "├─ Giới hạn thiết bị: {$keyData['hwid_limit']}\n";
        $msg .= "├─ Đã dùng: {$keyData['times_used']} lần\n";
        $msg .= "└─ HWIDs đã dùng: <code>{$keyData['hwids']}</code>\n";
        sendTelegramMessage($chatId, $msg);
    }
} elseif (strpos($text, '/delete') === 0) {
    showDeleteMenu($chatId);
} elseif (strpos($text, '/extend') === 0) {
    showExtendMenu($chatId);
} else {
    sendTelegramMessage($chatId, "❌ Lệnh không hợp lệ. Gõ <code>/start</code> để xem menu.");
}

// Hàm gửi menu chính với nút bấm
function sendMainMenu($chatId) {
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '🔑 Tạo Key', 'callback_data' => 'genkey'],
                ['text' => '📋 Danh sách Key', 'callback_data' => 'keys']
            ],
            [
                ['text' => '🔍 Kiểm tra Key', 'callback_data' => 'check'],
                ['text' => '🗑️ Xóa Key', 'callback_data' => 'delete']
            ],
            [
                ['text' => '⏳ Gia hạn Key', 'callback_data' => 'extend']
            ]
        ]
    ];
    $msg = "🤖 <b>Menu chính</b>\n\nChọn chức năng bên dưới:";
    sendTelegramMessage($chatId, $msg, $keyboard);
}

// Hàm hiển thị danh sách key (không có nút xóa)
function showKeysList($chatId) {
    $keys = listKeys(20);
    if (empty($keys)) {
        sendTelegramMessage($chatId, "📭 <b>Chưa có key nào.</b>");
        return;
    }
    $msg = "📋 <b>Danh sách key gần nhất:</b>\n\n";
    foreach ($keys as $k) {
        $expire = $k['expires_at'] ?: 'Vĩnh viễn';
        $statusIcon = ($k['status'] === 'active') ? '✅' : '⛔';
        $msg .= "<code>{$k['key']}</code>\n";
        $msg .= "├─ Hết hạn: <i>$expire</i>\n";
        $msg .= "├─ Thời gian còn lại: " . getTimeRemaining($k['expires_at']) . "\n";
        $msg .= "└─ Trạng thái: $statusIcon {$k['status']}\n\n";
    }
    sendTelegramMessage($chatId, $msg);
}

// Hàm hiển thị menu xóa key với các nút số
function showDeleteMenu($chatId) {
    $keys = listKeys(20);
    if (empty($keys)) {
        sendTelegramMessage($chatId, "📭 <b>Chưa có key nào để xóa.</b>");
        return;
    }
    $msg = "🗑️ <b>Chọn key cần xóa:</b>\n\n";
    $buttons = [];
    $row = [];
    foreach ($keys as $index => $k) {
        $num = $index + 1;
        $msg .= "<b>$num.</b> <code>{$k['key']}</code>\n";
        $msg .= "├─ Hết hạn: <i>" . ($k['expires_at'] ?: 'Vĩnh viễn') . "</i>\n";
        $msg .= "├─ Thời gian còn lại: " . getTimeRemaining($k['expires_at']) . "\n";
        $statusIcon = ($k['status'] === 'active') ? '✅' : '⛔';
        $msg .= "└─ Trạng thái: $statusIcon {$k['status']}\n\n";

        $row[] = ['text' => strval($num), 'callback_data' => 'delete_' . $k['id']];
        if (count($row) == 5) {
            $buttons[] = $row;
            $row = [];
        }
    }
    if (!empty($row)) {
        $buttons[] = $row;
    }
    $keyboard = ['inline_keyboard' => $buttons];
    sendTelegramMessage($chatId, $msg, $keyboard);
}

// Hàm hiển thị menu gia hạn key
function showExtendMenu($chatId) {
    $keys = listKeys(20);
    if (empty($keys)) {
        sendTelegramMessage($chatId, "📭 <b>Chưa có key nào để gia hạn.</b>");
        return;
    }
    $msg = "⏳ <b>Chọn key cần gia hạn:</b>\n\n";
    $buttons = [];
    $row = [];
    foreach ($keys as $index => $k) {
        $num = $index + 1;
        $msg .= "<b>$num.</b> <code>{$k['key']}</code>\n";
        $msg .= "├─ Hết hạn: <i>" . ($k['expires_at'] ?: 'Vĩnh viễn') . "</i>\n";
        $msg .= "├─ Thời gian còn lại: " . getTimeRemaining($k['expires_at']) . "\n";
        $statusIcon = ($k['status'] === 'active') ? '✅' : '⛔';
        $msg .= "└─ Trạng thái: $statusIcon {$k['status']}\n\n";

        $row[] = ['text' => strval($num), 'callback_data' => 'extend_' . $k['id']];
        if (count($row) == 5) {
            $buttons[] = $row;
            $row = [];
        }
    }
    if (!empty($row)) {
        $buttons[] = $row;
    }
    $keyboard = ['inline_keyboard' => $buttons];
    sendTelegramMessage($chatId, $msg, $keyboard);
}