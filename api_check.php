<?php
// db.php

class Database {
    private static $pdo = null;

    // Bump SCHEMA_VERSION mỗi lần thêm migration mới
    private const SCHEMA_VERSION = 3;

    public static function getConnection() {
        if (self::$pdo === null) {
            $dbUrl = getenv('DATABASE_URL');
            if ($dbUrl) {
                $parts = parse_url($dbUrl);
                $host = $parts['host'];
                $port = $parts['port'] ?? 5432;
                $dbname = ltrim($parts['path'], '/');
                $user = $parts['user'];
                $password = $parts['pass'] ?? '';
                $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
                self::$pdo = new PDO($dsn, $user, $password);
            } else {
                if (!is_dir(dirname(DB_PATH))) {
                    mkdir(dirname(DB_PATH), 0755, true);
                }
                self::$pdo = new PDO('sqlite:' . DB_PATH);
            }
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::initTables();
        }
        return self::$pdo;
    }

    private static function initTables() {
        $pdo = self::$pdo;
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        // schema_version — guard migration race
        if ($driver === 'pgsql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS schema_version (
                id INTEGER PRIMARY KEY DEFAULT 1 CHECK (id = 1),
                version INTEGER NOT NULL DEFAULT 0
            )");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS schema_version (
                id INTEGER PRIMARY KEY CHECK (id = 1),
                version INTEGER NOT NULL DEFAULT 0
            )");
        }
        // Đảm bảo có đúng 1 hàng
        try {
            $pdo->exec("INSERT INTO schema_version (id, version) VALUES (1, 0)");
        } catch (PDOException $e) { /* already exists */ }

        $current = (int)$pdo->query("SELECT version FROM schema_version WHERE id = 1")->fetchColumn();
        if ($current >= self::SCHEMA_VERSION) {
            return; // đã migrate xong, skip toàn bộ
        }

        // Base tables
        if ($driver === 'pgsql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS keys (
                id SERIAL PRIMARY KEY,
                key TEXT UNIQUE NOT NULL,
                prefix TEXT,
                created_at TEXT NOT NULL,
                expires_at TEXT,
                hwid_limit INTEGER DEFAULT 1,
                hwids TEXT DEFAULT '[]',
                status TEXT DEFAULT 'active',
                times_used INTEGER DEFAULT 0,
                last_used_at TEXT,
                note TEXT,
                app TEXT DEFAULT 'other'
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS bot_states (
                chat_id TEXT PRIMARY KEY,
                state TEXT,
                data TEXT
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
                id SERIAL PRIMARY KEY,
                identifier TEXT NOT NULL,
                created_at TEXT NOT NULL
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS key_events (
                id SERIAL PRIMARY KEY,
                key_text TEXT,
                hwid TEXT,
                ip TEXT,
                event TEXT NOT NULL,
                detail TEXT,
                created_at TEXT NOT NULL
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS used_nonces (
                nonce TEXT PRIMARY KEY,
                created_at TEXT NOT NULL
            )");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS keys (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key TEXT UNIQUE NOT NULL,
                prefix TEXT,
                created_at TEXT NOT NULL,
                expires_at TEXT,
                hwid_limit INTEGER DEFAULT 1,
                hwids TEXT DEFAULT '[]',
                status TEXT DEFAULT 'active',
                times_used INTEGER DEFAULT 0,
                last_used_at TEXT,
                note TEXT,
                app TEXT DEFAULT 'other'
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS bot_states (
                chat_id TEXT PRIMARY KEY,
                state TEXT,
                data TEXT
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                identifier TEXT NOT NULL,
                created_at TEXT NOT NULL
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS key_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key_text TEXT,
                hwid TEXT,
                ip TEXT,
                event TEXT NOT NULL,
                detail TEXT,
                created_at TEXT NOT NULL
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS used_nonces (
                nonce TEXT PRIMARY KEY,
                created_at TEXT NOT NULL
            )");
        }

        // Column migrations — chỉ chạy khi version < SCHEMA_VERSION
        $migrate = [
            "ALTER TABLE keys ADD COLUMN app TEXT DEFAULT 'other'",
            "ALTER TABLE keys ADD COLUMN bound_hwid TEXT",
            "ALTER TABLE keys ADD COLUMN session_token TEXT",
            "ALTER TABLE keys ADD COLUMN last_ip TEXT",
            "ALTER TABLE keys ADD COLUMN last_seen_at TEXT",
            "ALTER TABLE keys ADD COLUMN ban_reason TEXT",
            "ALTER TABLE keys ADD COLUMN anomaly_count INTEGER DEFAULT 0",
        ];
        foreach ($migrate as $sql) {
            try { $pdo->exec($sql); } catch (PDOException $e) { /* already exists */ }
        }

        // Phase 4: device hardware public keys
        if ($driver === 'pgsql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS device_keys (
                id SERIAL PRIMARY KEY,
                key_text TEXT NOT NULL,
                hwid TEXT NOT NULL,
                pubkey TEXT NOT NULL,
                strongbox INTEGER DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (key_text, hwid)
            )");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS device_keys (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key_text TEXT NOT NULL,
                hwid TEXT NOT NULL,
                pubkey TEXT NOT NULL,
                strongbox INTEGER DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (key_text, hwid)
            )");
        }

        // Đánh dấu đã migrate — process khác thấy version đủ sẽ skip
        $pdo->prepare("UPDATE schema_version SET version = ? WHERE id = 1")
            ->execute([self::SCHEMA_VERSION]);
    }
}
