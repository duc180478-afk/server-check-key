<?php
// db.php

class Database {
    private static $pdo = null;

    public static function getConnection() {
        if (self::$pdo === null) {
            if (!is_dir(dirname(DB_PATH))) {
                mkdir(dirname(DB_PATH), 0755, true);
            }
            self::$pdo = new PDO('sqlite:' . DB_PATH);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::initTables();
        }
        return self::$pdo;
    }

    private static function initTables() {
        $pdo = self::$pdo;
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