<?php
// bot.php

require_once 'config.php';
require_once 'functions.php';

// ===== HÀM GỬI TIN NHẮN =====
function sendTelegramMessageLocal($chatId, $text, $keyboard = null) {
    if (!defined('TELEGRAM_BOT_TOKEN') || TELEGRAM_BOT_TOKEN === 'YOUR_BOT_TOKEN') return false;
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    $data = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($keyboard !== null) $data['reply_markup'] = json_encode($keyboard);
    $context = stream_context_create(['http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data)
    ]]);
    return @file_get_contents($url, false, $context);
}

function editTelegramMessageLocal($chatId, $messageId, $text, $keyboard = null) {
    if (!defined('TELEGRAM_BOT_TOKEN') || TELEGRAM_BOT_TOKEN === 'YOUR_BOT_TOKEN') return false;
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/editMessageText";
    $data = ['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($keyboard !== null) $data['reply_markup'] = json_encode($keyboard);
    $context = stream_context_create(['http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data)
    ]]);
    return @file_get_contents($url, false, $context);
}

function sendOrEditMessage($chatId, $text, $keyboard = null, $messageId = null) {
    if ($messageId !== null) {
        $result = editTelegramMessageLocal($chatId, $messageId, $text, $keyboard);
        if ($result !== false) return true;
    }
    return sendTelegramMessageLocal($chatId, $text, $keyboard);
}

// ===== MENU CHỌN APP (dùng chung) =====
function sendAppMenu($chatId, $messageId, $baseAction, $title, $subtitle) {
    $row = [];
    foreach (SYSTEM_APPS as $key => $app) {
        $row[] = ['text' => '🎮 ' . $app['label'], 'callback_data' => $baseAction . '_' . $key];
    }
    $keyboard = ['inline_keyboard' => [$row]];
    sendOrEditMessage($chatId, $title . "\n\n" . $subtitle, $keyboard, $messageId);
}

// ===== XỬ LÝ CALLBACK QUERY =====
 $content = file_get_contents('php://input');
 $update = json_decode($content, true);

if (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $chatId = $callback['message']['chat']['id'];
    $messageId = $callback['message']['message_id'];
    $data = $callback['data'];

    if (!in_array($chatId, TELEGRAM_ADMIN_CHAT_IDS)) {
        sendOrEditMessage($chatId, "🚫 Bạn không có quyền sử dụng bot này.", null, $messageId);
        exit;
    }

    cleanupExpiredKeys();
    clearBotState($chatId);

    // ==== CHỨC NĂNG -> hiện menu chọn app ====
    if ($data === 'genkey' || $data === 'keys' || $data === 'check' || $data === 'delete' || $data === 'extend') {
        $titles = [
            'genkey' => ['🔑 <b>Tạo Key</b>', "Chọn app cần tạo key:\n(Sau khi chọn, bạn tự nhập prefix tùy ý)"],
            'keys'   => ['📋 <b>Danh sách Key</b>', 'Chọn app để xem key:'],
            'check'  => ['🔍 <b>Kiểm tra Key</b>', 'Chọn app của key cần kiểm tra:'],
            'delete' => ['🗑️ <b>Xóa Key</b>', 'Chọn app để xem key cần xóa:'],
            'extend' => ['⏳ <b>Gia hạn Key</b>', 'Chọn app để xem key cần gia hạn:'],
        ];
        sendAppMenu($chatId, $messageId, $data, $titles[$data][0], $titles[$data][1]);
        exit;
    }

    // ==== TẠO KEY: chọn app xong -> hỏi nhập prefix + ngày + số lượng ====
    if (strpos($data, 'genkey_') === 0) {
        $appKey = substr($data, 7);
        if (isset(SYSTEM_APPS[$appKey])) {
            $appLabel = SYSTEM_APPS[$appKey]['label'];
            $defPrefix = SYSTEM_APPS[$appKey]['default_prefix'];
            setBotState($chatId, 'awaiting_genkey_' . $appKey);
            sendOrEditMessage($chatId,
                "🔑 <b>Tạo key — $appLabel</b>\n\nNhập theo cú pháp:\n<code>&lt;prefix&gt; &lt;số ngày&gt; &lt;số lượng&gt;</code>\n\nVí dụ: <code>$defPrefix 30 5</code>\n• Prefix tùy ý (gợi ý: <code>$defPrefix</code>)\n• Ngày <code>0</code> = vĩnh viễn",
                null, $messageId);
        }
        exit;
    }

    // ==== DANH SÁCH KEY THEO APP ====
    if (strpos($data, 'keys_') === 0) {
        $appKey = substr($data, 5);
        if (isset(SYSTEM_APPS[$appKey])) {
            showKeysList($chatId, $messageId, $appKey);
        }
        exit;
    }

    // ==== KIỂM TRA KEY THEO APP ====
    if (strpos($data, 'check_') === 0) {
        $appKey = substr($data, 6);
        if (isset(SYSTEM_APPS[$appKey])) {
            setBotState($chatId, 'awaiting_check');
            sendOrEditMessage($chatId, "🔍 <b>Kiểm tra key " . SYSTEM_APPS[$appKey]['label'] . "</b>\n\nVui lòng nhập key cần kiểm tra:", null, $messageId);
        }
        exit;
    }

    // ==== XÓA KEY ====
    if (strpos($data, 'delete_') === 0) {
        $arg = substr($data, 7);
        if (ctype_digit($arg)) {
            $id = intval($arg);
            $keyData = getKeyById($id);
            if ($keyData) {
                deleteKey($id);
                sendOrEditMessage($chatId, "✅ Đã xóa key: <code>{$keyData['key']}</code>", null, $messageId);
            } else {
                sendOrEditMessage($chatId, "❌ Key không tồn tại.", null, $messageId);
            }
            sendMainMenu($chatId, $messageId);
        } elseif (isset(SYSTEM_APPS[$arg])) {
            showDeleteMenu($chatId, $messageId, $arg);
        }
        exit;
    }

    // ==== GIA HẠN KEY ====
    if (strpos($data, 'extend_') === 0) {
        $arg = substr($data, 7);
        if (ctype_digit($arg)) {
            $id = intval($arg);
            $keyData = getKeyById($id);
            if ($keyData) {
                setBotState($chatId, "awaiting_extend_$id");
                sendOrEditMessage($chatId, "⏳ <b>Gia hạn key</b>\n\nKey: <code>{$keyData['key']}</code>\n\nVui lòng nhập số ngày muốn gia hạn:", null, $messageId);
            } else {
                sendOrEditMessage($chatId, "❌ Key không tồn tại.", null, $messageId);
            }
        } elseif (isset(SYSTEM_APPS[$arg])) {
            showExtendMenu($chatId, $messageId, $arg);
        }
        exit;
    }

    if ($data === 'menu') {
        sendMainMenu($chatId, $messageId);
        exit;
    }

    sendOrEditMessage($chatId, "❌ Lệnh không hợp lệ.", null, $messageId);
    exit;
}

// ===== XỬ LÝ TIN NHẮN THƯỜNG =====
if (!isset($update['message'])) {
    exit;
}

 $message = $update['message'];
 $chatId = $message['chat']['id'];
 $text = trim($message['text'] ?? '');

if (!in_array($chatId, TELEGRAM_ADMIN_CHAT_IDS)) {
    sendTelegramMessageLocal($chatId, "🚫 Bạn không có quyền sử dụng bot này.");
    exit;
}

cleanupExpiredKeys();

if (strpos($text, '/') === 0) {
    clearBotState($chatId);
}

 $state = getBotState($chatId);
if ($state) {
    $stateName = $state['state'];

    // ==== TẠO KEY: nhận <prefix> <ngày> <số lượng> — prefix TÙY Ý ====
    if (strpos($stateName, 'awaiting_genkey_') === 0) {
        $appKey = substr($stateName, 16);
        $parts = preg_split('/\s+/', $text);
        if (!isset(SYSTEM_APPS[$appKey]) || count($parts) < 3) {
            sendTelegramMessageLocal($chatId, "⚠️ Cú pháp: <code>&lt;prefix&gt; &lt;số ngày&gt; &lt;số lượng&gt;</code>\nVí dụ: <code>FLFF 30 5</code>");
            exit;
        }
        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $parts[0]));
        $days = intval($parts[1]);
        $count = intval($parts[2]);
        if ($prefix === '') {
            sendTelegramMessageLocal($chatId, "⚠️ Prefix không hợp lệ (chỉ chữ + số, không dấu cách).");
            exit;
        }
        if ($count <= 0 || $count > 100) {
            sendTelegramMessageLocal($chatId, "⚠️ Số lượng phải từ 1 đến 100.");
            exit;
        }
        $keys = createKeyBatch($prefix, $count, $days > 0 ? $days : null, 1, "Admin bot - $appKey", $appKey);
        clearBotState($chatId);
        $msg = "✅ Đã tạo <b>$count</b> key [" . SYSTEM_APPS[$appKey]['label'] . "] (prefix <code>$prefix</code>):\n";
        foreach ($keys as $k) {
            $msg .= "<code>$k</code>\n";
        }
        $msg .= "\nHết hạn: " . ($days > 0 ? date('Y-m-d H:i:s', time() + $days * 86400) : 'Vĩnh viễn');
        sendTelegramMessageLocal($chatId, $msg);
        sendMainMenu($chatId);
        exit;
    }

    // ==== KIỂM TRA KEY ====
    if ($stateName === 'awaiting_check') {
        $keyData = getKeyByKey($text);
        if (!$keyData) {
            sendTelegramMessageLocal($chatId, "❌ Key không tồn tại.");
        } else {
            $msg = "🔑 <b>Thông tin key:</b>\n";
            $msg .= "<code>{$keyData['key']}</code>\n";
            $msg .= "├─ App: <b>" . getAppLabel($keyData['app'] ?? '') . "</b>\n";
            $msg .= "├─ Trạng thái: <b>{$keyData['status']}</b>\n";
            $msg .= "├─ Hết hạn: <i>" . ($keyData['expires_at'] ?: 'Vĩnh viễn') . "</i>\n";
            $msg .= "├─ Thời gian còn lại: " . getTimeRemaining($keyData['expires_at']) . "\n";
            $msg .= "├─ Đã dùng: {$keyData['times_used']} lần\n";
            $msg .= "└─ HWIDs: <code>{$keyData['hwids']}</code>\n";
            sendTelegramMessageLocal($chatId, $msg);
        }
        clearBotState($chatId);
        sendMainMenu($chatId);
        exit;
    }

    // ==== GIA HẠN ====
    if (strpos($stateName, 'awaiting_extend_') === 0) {
        $id = intval(substr($stateName, 16));
        $days = intval($text);
        $keyData = getKeyById($id);
        if ($keyData && $days > 0) {
            // Gia hạn cộng dồn
            $currentExpire = $keyData['expires_at'] ? strtotime($keyData['expires_at']) : 0;
            $baseTime = ($currentExpire > time()) ? $currentExpire : time();
            $newExpire = date('Y-m-d H:i:s', $baseTime + $days * 86400);
            updateKey($id, ['expires_at' => $newExpire, 'status' => 'active']);
            sendTelegramMessageLocal($chatId, "✅ Đã gia hạn key <code>{$keyData['key']}</code> thêm <b>$days</b> ngày.\nHạn mới: $newExpire");
        } else {
            sendTelegramMessageLocal($chatId, "❌ Số ngày không hợp lệ hoặc key không tồn tại.");
        }
        clearBotState($chatId);
        sendMainMenu($chatId);
        exit;
    }

    clearBotState($chatId);
    sendTelegramMessageLocal($chatId, "❌ Trạng thái không hợp lệ, đã hủy.");
    sendMainMenu($chatId);
    exit;
}

// ===== LỆNH TEXT =====
if (strpos($text, '/start') === 0 || strpos($text, '/help') === 0 || strpos($text, '/menu') === 0) {
    clearBotState($chatId);
    sendMainMenu($chatId);
} elseif (strpos($text, '/myid') === 0) {
    clearBotState($chatId);
    sendTelegramMessageLocal($chatId, "🆔 Chat ID của bạn: <code>$chatId</code>");
} elseif (strpos($text, '/genkey') === 0) {
    clearBotState($chatId);
    sendAppMenu($chatId, null, 'genkey', '🔑 <b>Tạo Key</b>', "Chọn app cần tạo key:\n(Sau khi chọn, bạn tự nhập prefix tùy ý)");
} elseif (strpos($text, '/keys') === 0) {
    clearBotState($chatId);
    sendAppMenu($chatId, null, 'keys', '📋 <b>Danh sách Key</b>', 'Chọn app để xem key:');
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
        $msg .= "├─ App: <b>" . getAppLabel($keyData['app'] ?? '') . "</b>\n";
        $msg .= "├─ Trạng thái: <b>{$keyData['status']}</b>\n";
        $msg .= "├─ Hết hạn: <i>" . ($keyData['expires_at'] ?: 'Vĩnh viễn') . "</i>\n";
        $msg .= "├─ Còn lại: " . getTimeRemaining($keyData['expires_at']) . "\n";
        $msg .= "├─ Đã dùng: {$keyData['times_used']} lần\n";
        $msg .= "└─ HWIDs: <code>{$keyData['hwids']}</code>\n";
        sendTelegramMessageLocal($chatId, $msg);
    }
} elseif (strpos($text, '/delete') === 0) {
    clearBotState($chatId);
    sendAppMenu($chatId, null, 'delete', '🗑️ <b>Xóa Key</b>', 'Chọn app để xem key cần xóa:');
} elseif (strpos($text, '/extend') === 0) {
    clearBotState($chatId);
    sendAppMenu($chatId, null, 'extend', '⏳ <b>Gia hạn Key</b>', 'Chọn app để xem key cần gia hạn:');
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

function showKeysList($chatId, $messageId = null, $appKey = null) {
    cleanupExpiredKeys();
    $keys = listKeysByApp(20, $appKey);
    if (empty($keys)) {
        sendOrEditMessage($chatId, "📭 <b>Chưa có key nào.</b>", null, $messageId);
        return;
    }
    $appLabel = $appKey ? SYSTEM_APPS[$appKey]['label'] : 'Tất cả';
    $msg = "📋 <b>Danh sách key — $appLabel:</b>\n\n";
    foreach ($keys as $k) {
        $expire = $k['expires_at'] ?: 'Vĩnh viễn';
        $statusIcon = ($k['status'] === 'active') ? '✅' : '⛔';
        $msg .= "<code>{$k['key']}</code>\n";
        $msg .= "├─ Hết hạn: <i>$expire</i>\n";
        $msg .= "├─ Còn lại: " . getTimeRemaining($k['expires_at']) . "\n";
        $msg .= "└─ Trạng thái: $statusIcon {$k['status']}\n\n";
    }
    $buttons = [[['text' => '⬅️ Đổi app', 'callback_data' => 'keys']]];
    sendOrEditMessage($chatId, $msg, ['inline_keyboard' => $buttons], $messageId);
}

function showDeleteMenu($chatId, $messageId = null, $appKey = null) {
    cleanupExpiredKeys();
    $keys = listKeysByApp(20, $appKey);
    if (empty($keys)) {
        sendOrEditMessage($chatId, "📭 <b>Chưa có key nào để xóa.</b>", null, $messageId);
        return;
    }
    $msg = "🗑️ <b>Chọn key cần xóa:</b>\n\n";
    $buttons = [];
    $row = [];
    foreach ($keys as $index => $k) {
        $num = $index + 1;
        $msg .= "<b>$num.</b> <code>{$k['key']}</code> ({$k['status']})\n";
        $row[] = ['text' => strval($num), 'callback_data' => 'delete_' . $k['id']];
        if (count($row) == 5) {
            $buttons[] = $row;
            $row = [];
        }
    }
    if (!empty($row)) $buttons[] = $row;
    $buttons[] = [['text' => '⬅️ Đổi app', 'callback_data' => 'delete']];
    sendOrEditMessage($chatId, $msg, ['inline_keyboard' => $buttons], $messageId);
}

function showExtendMenu($chatId, $messageId = null, $appKey = null) {
    cleanupExpiredKeys();
    $keys = listKeysByApp(20, $appKey);
    if (empty($keys)) {
        sendOrEditMessage($chatId, "📭 <b>Chưa có key nào để gia hạn.</b>", null, $messageId);
        return;
    }
    $msg = "⏳ <b>Chọn key cần gia hạn:</b>\n\n";
    $buttons = [];
    $row = [];
    foreach ($keys as $index => $k) {
        $num = $index + 1;
        $msg .= "<b>$num.</b> <code>{$k['key']}</code>\n├─ Hết hạn: <i>" . ($k['expires_at'] ?: 'Vĩnh viễn') . "</i>\n└─ Còn lại: " . getTimeRemaining($k['expires_at']) . "\n\n";
        $row[] = ['text' => strval($num), 'callback_data' => 'extend_' . $k['id']];
        if (count($row) == 5) {
            $buttons[] = $row;
            $row = [];
        }
    }
    if (!empty($row)) $buttons[] = $row;
    $buttons[] = [['text' => '⬅️ Đổi app', 'callback_data' => 'extend']];
    sendOrEditMessage($chatId, $msg, ['inline_keyboard' => $buttons], $messageId);
}