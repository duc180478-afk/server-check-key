<?php
// config.php

// Đường dẫn database SQLite
define('DB_PATH', __DIR__ . '/data/keys.db');

// Mật khẩu admin panel (nên đổi thành hash bcrypt)
// Dùng password_hash('your_password', PASSWORD_BCRYPT) để tạo hash
define('ADMIN_PASSWORD_HASH', getenv('ADMIN_PASSWORD_HASH') ?: '$2a$12$aVDuDN5tWdnJ2krf.PCrYe8tRhzvYNWdN1F8eOfCAtyWyg8MFAiOC); // default: "admin123"

// Cấu hình Telegram Bot
define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: '8430522560:AAFfK8CT0S9cr2GOGcWfqN8asF_qkLLrqqg');
define('TELEGRAM_ADMIN_CHAT_ID', getenv('TELEGRAM_ADMIN_CHAT_ID') ?: '8496514219');

// URL webhook của bot
define('BOT_WEBHOOK_URL', getenv('BOT_WEBHOOK_URL') ?: 'https://server-check-key.onrender.com/bot.php');