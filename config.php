<?php
// config.php

// Đường dẫn database SQLite
define('DB_PATH', __DIR__ . '/data/keys.db');

// Mật khẩu admin panel (nên đổi thành hash bcrypt)
// Dùng password_hash('your_password', PASSWORD_BCRYPT) để tạo hash
define('ADMIN_PASSWORD_HASH', getenv('ADMIN_PASSWORD_HASH') ?: '$2y$10$e0MYzXyjpJS7Pd0RVvHwHe1HlC7hQhYdQ9Y2rD2mh2s2F2oP0oK6e'); // default: "admin123"

// Cấu hình Telegram Bot
define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: '8430522560:AAFfK8CT0S9cr2GOGcWfqN8asF_qkLLrqqg');
define('TELEGRAM_ADMIN_CHAT_ID', getenv('TELEGRAM_ADMIN_CHAT_ID') ?: '8496514219');

// URL webhook của bot (sẽ được thiết lập tự động nếu cần)
define('BOT_WEBHOOK_URL', getenv('BOT_WEBHOOK_URL') ?: 'https://your-app.onrender.com/bot.php');