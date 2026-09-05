<?php
// config.php

// Đường dẫn database SQLite (fallback nếu không có PostgreSQL)
define('DB_PATH', __DIR__ . '/data/keys.db');

// Mật khẩu admin panel (nên đổi thành hash bcrypt)
// Dùng password_hash('your_password', PASSWORD_BCRYPT) để tạo hash
define('ADMIN_PASSWORD_HASH', getenv('ADMIN_PASSWORD_HASH') ?: '$2a$10$/nEUgCdjmiSe/1ljkvdIPOuv8qZeoQvO9Cw8ZTsYyzrvlAVJueBMS'); // hash cho "admin123"

// Cấu hình Telegram Bot
define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: '8430522560:AAFfK8CT0S9cr2GOGcWfqN8asF_qkLLrqqg');

// Danh sách admin chat IDs (có thể thêm nhiều)
$adminIds = ['8496514219', '8361524404']; // ID cũ và ID mới
define('TELEGRAM_ADMIN_CHAT_IDS', $adminIds);

// URL webhook của bot
define('BOT_WEBHOOK_URL', getenv('BOT_WEBHOOK_URL') ?: 'https://server-check-key.onrender.com/bot.php');