<?php
// config.php

// Đường dẫn database SQLite (fallback nếu không có PostgreSQL)
define('DB_PATH', __DIR__ . '/data/keys.db');

// Mật khẩu admin panel
define('ADMIN_PASSWORD_HASH', getenv('ADMIN_PASSWORD_HASH') ?: '$2a$10$/nEUgCdjmiSe/1ljkvdIPOuv8qZeoQvO9Cw8ZTsYyzrvlAVJueBMS'); // hash cho "admin123"

// Cấu hình Telegram Bot
define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: 8430522560:AAGCEgynTxlzlSrgc_0_EUq1_3I1bPTvOTQ');

// Danh sách admin chat IDs (có thể thêm nhiều)
 $adminIds = ['8496514219', '8361524404'];
define('TELEGRAM_ADMIN_CHAT_IDS', $adminIds);

// URL webhook của bot
define('BOT_WEBHOOK_URL', getenv('BOT_WEBHOOK_URL') ?: 'https://getkey2.onrender.com');
// === CHIMERA SHIELD v16 (Server-Side Anti-Crack) ===
// Đặt biến môi trường CHIMERA_STRICT_SHIELD=0 nếu muốn tạm tắt bắt buộc chữ ký để test
define('CHIMERA_STRICT_SHIELD', getenv('CHIMERA_STRICT_SHIELD') !== '0');
// Chỉ cho phép key=DEBUG_BYPASS khi đặt CHIMERA_ALLOW_DEBUG_BYPASS=1
define('CHIMERA_ALLOW_DEBUG_BYPASS', getenv('CHIMERA_ALLOW_DEBUG_BYPASS') === '1');
define('CHIMERA_CRACK_NOTICE', 'Crack cái con cặc ba mày hahahaah');
