<?php
// db.php

class Database {
    private static $pdo = null;

    public static function getConnection() {
        if (self::$pdo === null) {
            $dbUrl = getenv('DATABASE_URL');
            if ($dbUrl) {
                // Sử dụng PostgreSQL
                $parts = parse_url($dbUrl);
                $host = $parts['host'];
                $port = $parts['port'] ?? 5432;
                $dbname = ltrim($parts['path'], '/');
                $user = $parts['user'];
                $password = $parts['pass'] ?? '';
                $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
                self::$pdo = new PDO($dsn, $user, $password);
            } else {
                // Fallback SQLite khi chạy local
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
                note TEXT
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
                note TEXT
            )");
        }
    }
}