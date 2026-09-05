<?php
// bot.php

require_once 'config.php';
require_once 'functions.php';

// ... (các hàm sendTelegramMessageLocal, editTelegramMessageLocal, sendOrEditMessage giữ nguyên)

$content = file_get_contents('php://input');
$update = json_decode($content, true);

// ===== XỬ LÝ CALLBACK QUERY =====
if (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $chatId = $callback['message']['chat']['id'];
    $messageId = $callback['message']['message_id'];
    $data = $callback['data'];

    // Kiểm tra admin
    if (!in_array($chatId, TELEGRAM_ADMIN_CHAT_IDS)) {
        sendOrEditMessage($chatId, "🚫 Bạn không có quyền sử dụng bot này.", null, $messageId);
        exit;
    }

    // Dọn key hết hạn trước khi thao tác
    cleanupExpiredKeys();

    // Luôn xóa trạng thái chờ khi nhận callback
    clearBotState($chatId);

    switch ($data) {
        case 'menu':
            sendMainMenu($chatId, $messageId);
            break;

        case 'genkey':
            setBotState($chatId, 'awaiting_genkey');
            sendOrEditMessage($chatId, "🔑 <b>Tạo key mới</b>\n\nVui lòng nhập theo cú pháp:\n<code>&lt;prefix&gt; &lt;số ngày&gt; &lt;số lượng&gt;</code>\n\nVí dụ: <code>VIP 30 5</code> (tạo 5 key VIP hết hạn 30 ngày)", null, $messageId);
            break;

        case 'keys':
            showKeysList($chatId, $messageId);
            break;

        case 'check':
            setBotState($chatId, 'awaiting_check');
            sendOrEditMessage($chatId, "🔍 <b>Kiểm tra key</b>\n\nVui lòng nhập key cần kiểm tra:", null, $messageId);
            break;

        case 'delete':
            showDeleteMenu($chatId, $messageId);
            break;

        case 'extend':
            showExtendMenu($chatId, $messageId);
            break;

        default:
            // Xử lý callback delete_<id> hoặc extend_<id>
            if (strpos($data, 'delete_') === 0) {
                $id = intval(substr($data, 7));
                $keyData = getKeyById($id);
                if ($keyData) {
                    deleteKey($id);
                    sendOrEditMessage($chatId, "✅ Đã xóa key: <code>{$keyData['key']}</code>", null, $messageId);
                } else {
                    sendOrEditMessage($chatId, "❌ Key không tồn tại.", null, $messageId);
                }
                sendMainMenu($chatId, $messageId);
            } elseif (strpos($data, 'extend_') === 0) {
                $id = intval(substr($data, 7));
                $keyData = getKeyById($id);
                if ($keyData) {
                    setBotState($chatId, "awaiting_extend_$id");
                    sendOrEditMessage($chatId, "⏳ <b>Gia hạn key</b>\n\nKey: <code>{$keyData['key']}</code>\n\nVui lòng nhập số ngày muốn gia hạn:", null, $messageId);
                } else {
                    sendOrEditMessage($chatId, "❌ Key không tồn tại.", null, $messageId);
                }
            } else {
                sendOrEditMessage($chatId, "❌ Lệnh không hợp lệ.", null, $messageId);
            }
            break;
    }
    exit;
}

// ===== XỬ LÝ TIN NHẮN THƯỜNG =====
if (!isset($update['message'])) {
    exit;
}

$message = $update['message'];
$chatId = $message['chat']['id'];
$text = trim($message['text'] ?? '');

// Kiểm tra admin
if (!in_array($chatId, TELEGRAM_ADMIN_CHAT_IDS)) {
    sendTelegramMessageLocal($chatId, "🚫 Bạn không có quyền sử dụng bot này.");
    exit;
}

// Dọn key hết hạn trước khi xử lý
cleanupExpiredKeys();

// Nếu tin nhắn là lệnh bắt đầu bằng "/" thì luôn xóa trạng thái chờ
if (strpos($text, '/') === 0) {
    clearBotState($chatId);
}

// Nếu có trạng thái chờ, xử lý trước
$state = getBotState($chatId);
if ($state) {
    $stateName = $state['state'];
    $stateData = $state['data'];

    switch ($stateName) {
        case 'awaiting_genkey':
            // Parse: prefix days count
            $parts = explode(' ', $text);
            if (count($parts) < 3) {
                sendTelegramMessageLocal($chatId, "⚠️ Cú pháp không đúng. Vui lòng nhập: <code>&lt;prefix&gt; &lt;số ngày&gt; &lt;số lượng&gt;</code>");
                break;
            }
            $prefix = $parts[0];
            $days = intval($parts[1]);
            $count = intval($parts[2]);
            if ($count <= 0) {
                sendTelegramMessageLocal($chatId, "⚠️ Số lượng phải lớn hơn 0.");
                break;
            }
            $keys = createKeyBatch($prefix, $count, $days);
            clearBotState($chatId);
            $msg = "✅ Đã tạo <b>$count</b> key:\n";
            foreach ($keys as $k) {
                $msg .= "<code>$k</code>\n";
            }
            $msg .= "\nHết hạn: " . ($days > 0 ? date('Y-m-d H:i:s', time() + $days * 86400) : 'Vĩnh viễn');
            sendTelegramMessageLocal($chatId, $msg);
            sendMainMenu($chatId);
            break;

        case 'awaiting_check':
            $keyData = getKeyByKey($text);
            if (!$keyData) {
                sendTelegramMessageLocal($chatId, "❌ Key không tồn tại.");
            } else {
                $msg = "🔑 <b>Thông tin key:</b>\n";
                $msg .= "<code>{$keyData['key']}</code>\n";
                $msg .= "├─ Trạng thái: <b>{$keyData['status']}</b>\n";
                $msg .= "├─ Hết hạn: <i>" . ($keyData['expires_at'] ?: 'Vĩnh viễn') . "</i>\n";
                $msg .= "├─ Thời gian còn lại: " . getTimeRemaining($keyData['expires_at']) . "\n";
                $msg .= "├─ Giới hạn thiết bị: {$keyData['hwid_limit']}\n";
                $msg .= "├─ Đã dùng: {$keyData['times_used']} lần\n";
                $msg .= "└─ HWIDs đã dùng: <code>{$keyData['hwids']}</code>\n";
                sendTelegramMessageLocal($chatId, $msg);
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
                    sendTelegramMessageLocal($chatId, "✅ Đã gia hạn key <code>{$keyData['key']}</code> thêm <b>$days</b> ngày.\nHết hạn mới: $newExpire");
                } else {
                    sendTelegramMessageLocal($chatId, "❌ Số ngày không hợp lệ hoặc key không tồn tại.");
                }
                clearBotState($chatId);
                sendMainMenu($chatId);
            } else {
                clearBotState($chatId);
                sendTelegramMessageLocal($chatId, "❌ Trạng thái không hợp lệ, đã hủy.");
                sendMainMenu($chatId);
            }
            break;
    }
    exit;
}

// Xử lý các lệnh text
if (strpos($text, '/start') === 0 || strpos($text, '/help') === 0 || strpos($text, '/menu') === 0) {
    clearBotState($chatId);
    sendMainMenu($chatId);
} elseif (strpos($text, '/myid') === 0) {
    clearBotState($chatId);
    sendTelegramMessageLocal($chatId, "🆔 Chat ID của bạn: <code>$chatId</code>");
} elseif (strpos($text, '/genkey') === 0) {
    clearBotState($chatId);
    $parts = explode(' ', $text);
    if (count($parts) < 4) {
        sendTelegramMessageLocal($chatId, "⚠️ Cú pháp: <code>/genkey &lt;prefix&gt; &lt;số ngày&gt; &lt;số lượng&gt;</code>");
        exit;
    }
    $prefix = $parts[1];
    $days = intval($parts[2]);
    $count = intval($parts[3]);
    $keys = createKeyBatch($prefix, $count, $days);
    $msg = "✅ Đã tạo <b>$count</b> key:\n";
    foreach ($keys as $k) {
        $msg .= "<code>$k</code>\n";
    }
    sendTelegramMessageLocal($chatId, $msg);
} elseif (strpos($text, '/keys') === 0) {
    clearBotState($chatId);
    showKeysList($chatId);
} elseif (strpos($text, '/check') === 0) {
    clearBotState($chatId);
    $key = trim(substr($text, 7));
    if (empty($key)) {
        sendTelegramMessageLocal($chatId, "⚠️ Cú pháp: <code>/check &lt;key&gt;</code>");
        exit;
    }
    $keyData = getKeyByKey($key);
    if (!$keyData) {
        sendTelegramMessageLocal($chatId, "❌ Key không tồn tại.");
    } else {
        $msg = "🔑 <b>Thông tin key:</b>\n";
        $msg .= "<code>{$keyData['key']}</code>\n";
        $msg .= "├─ Trạng thái: <b>{$keyData['status']}</b>\n";
        $msg .= "├─ Hết hạn: <i>" . ($keyData['expires_at'] ?: 'Vĩnh viễn') . "</i>\n";
        $msg .= "├─ Thời gian còn lại: " . getTimeRemaining($keyData['expires_at']) . "\n";
        $msg .= "├─ Giới hạn thiết bị: {$keyData['hwid_limit']}\n";
        $msg .= "├─ Đã dùng: {$keyData['times_used']} lần\n";
        $msg .= "└─ HWIDs đã dùng: <code>{$keyData['hwids']}</code>\n";
        sendTelegramMessageLocal($chatId, $msg);
    }
} elseif (strpos($text, '/delete') === 0) {
    clearBotState($chatId);
    showDeleteMenu($chatId);
} elseif (strpos($text, '/extend') === 0) {
    clearBotState($chatId);
    showExtendMenu($chatId);
} else {
    sendTelegramMessageLocal($chatId, "❌ Lệnh không hợp lệ. Gõ <code>/start</code> để xem menu.");
}

// ===== CÁC HÀM HIỂN THỊ =====

function sendMainMenu($chatId, $messageId = null) {
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
    sendOrEditMessage($chatId, $msg, $keyboard, $messageId);
}

function showKeysList($chatId, $messageId = null) {
    cleanupExpiredKeys();
    $keys = listKeys(20);
    if (empty($keys)) {
        sendOrEditMessage($chatId, "📭 <b>Chưa có key nào.</b>", null, $messageId);
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
    sendOrEditMessage($chatId, $msg, null, $messageId);
}

function showDeleteMenu($chatId, $messageId = null) {
    cleanupExpiredKeys();
    $keys = listKeys(20);
    if (empty($keys)) {
        sendOrEditMessage($chatId, "📭 <b>Chưa có key nào để xóa.</b>", null, $messageId);
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
    sendOrEditMessage($chatId, $msg, $keyboard, $messageId);
}

function showExtendMenu($chatId, $messageId = null) {
    cleanupExpiredKeys();
    $keys = listKeys(20);
    if (empty($keys)) {
        sendOrEditMessage($chatId, "📭 <b>Chưa có key nào để gia hạn.</b>", null, $messageId);
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
    sendOrEditMessage($chatId, $msg, $keyboard, $messageId);
}