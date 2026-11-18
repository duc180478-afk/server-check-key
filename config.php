<?php
// config.php

// Đường dẫn database SQLite (fallback nếu không có PostgreSQL)
define('DB_PATH', __DIR__ . '/data/keys.db');

// Mật khẩu admin panel
define('ADMIN_PASSWORD_HASH', getenv('ADMIN_PASSWORD_HASH') ?: '$2a$10$/nEUgCdjmiSe/1ljkvdIPOuv8qZeoQvO9Cw8ZTsYyzrvlAVJueBMS'); // hash cho "admin123"

// Cấu hình Telegram Bot
define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: '8430522560:AAEZ8T7Kyz5nVLww6nYs9g4oCIiAqxuXcDE');

// Danh sách admin chat IDs (có thể thêm nhiều)
 $adminIds = ['8496514219', '8361524404'];
define('TELEGRAM_ADMIN_CHAT_IDS', $adminIds);

// URL webhook của bot
define('BOT_WEBHOOK_URL', getenv('BOT_WEBHOOK_URL') ?: 'https://getkey2.onrender.com');