<?php
/**
 * shield_guard.php — Server-Side Anti-Crack, Anti-Replay Nonce & Auto-Ban Engine (Chimera v16)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

class ChimeraServerShield {
    public static function initTables(): void {
        try {
            $pdo = Database::getConnection();
            $pdo->exec("CREATE TABLE IF NOT EXISTS chimera_used_nonces (
                nonce TEXT PRIMARY KEY,
                created_at INTEGER NOT NULL
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS chimera_crack_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip TEXT NOT NULL,
                hwid TEXT DEFAULT '',
                key_val TEXT DEFAULT '',
                reason TEXT NOT NULL,
                created_at INTEGER NOT NULL
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS chimera_bans (
                target TEXT PRIMARY KEY,
                reason TEXT NOT NULL,
                expires_at INTEGER NOT NULL
            )");
        } catch (Throwable $e) {
            // Fallback cho PostgreSQL SERIAL neu AUTOINCREMENT khong ho tro
            try {
                $pdo = Database::getConnection();
                $pdo->exec("CREATE TABLE IF NOT EXISTS chimera_used_nonces (nonce VARCHAR(128) PRIMARY KEY, created_at BIGINT NOT NULL)");
                $pdo->exec("CREATE TABLE IF NOT EXISTS chimera_crack_logs (id SERIAL PRIMARY KEY, ip VARCHAR(64) NOT NULL, hwid VARCHAR(128) DEFAULT '', key_val VARCHAR(128) DEFAULT '', reason VARCHAR(128) NOT NULL, created_at BIGINT NOT NULL)");
                $pdo->exec("CREATE TABLE IF NOT EXISTS chimera_bans (target VARCHAR(128) PRIMARY KEY, reason VARCHAR(128) NOT NULL, expires_at BIGINT NOT NULL)");
            } catch (Throwable $e2) {}
        }
    }

    public static function isBanned(string $ip, string $hwid = ''): bool {
        self::initTables();
        try {
            $pdo = Database::getConnection();
            $now = time();
            $stmt = $pdo->prepare("SELECT expires_at FROM chimera_bans WHERE target = ? AND expires_at > ?");
            $stmt->execute(['ip:' . $ip, $now]);
            if ($stmt->fetchColumn()) return true;
            if ($hwid !== '') {
                $stmt->execute(['hwid:' . $hwid, $now]);
                if ($stmt->fetchColumn()) return true;
            }
        } catch (Throwable $e) {}
        return false;
    }

    public static function verifyAndConsumeNonce(string $nonce, int $ts): bool {
        if ($nonce === '' || strlen($nonce) < 8 || abs(time() - $ts) > 120) {
            return false;
        }
        self::initTables();
        try {
            $pdo = Database::getConnection();
            $now = time();
            $pdo->prepare("DELETE FROM chimera_used_nonces WHERE created_at < ?")->execute([$now - 300]);
            $stmt = $pdo->prepare("SELECT 1 FROM chimera_used_nonces WHERE nonce = ?");
            $stmt->execute([$nonce]);
            if ($stmt->fetchColumn()) {
                return false; // Replay attack!
            }
            $pdo->prepare("INSERT INTO chimera_used_nonces (nonce, created_at) VALUES (?, ?)")->execute([$nonce, $now]);
            return true;
        } catch (Throwable $e) {
            return true;
        }
    }

    public static function rejectCracker(string $reason, string $ip, string $hwid = '', string $key = '', bool $banNow = false): void {
        self::initTables();
        $now = time();
        try {
            $pdo = Database::getConnection();
            $pdo->prepare("INSERT INTO chimera_crack_logs (ip, hwid, key_val, reason, created_at) VALUES (?, ?, ?, ?, ?)")
                ->execute([$ip, $hwid, $key, $reason, $now]);

            // Auto-ban 24h neu bi phat hien crack/tamper truc tiep hoac >= 3 lan sai chu ky trong 10 phut
            $shouldBan = $banNow;
            if (!$shouldBan) {
                $st = $pdo->prepare("SELECT COUNT(*) FROM chimera_crack_logs WHERE ip = ? AND created_at > ?");
                $st->execute([$ip, $now - 600]);
                if ((int)$st->fetchColumn() >= 3) $shouldBan = true;
            }
            if ($shouldBan) {
                $exp = $now + 86400;
                $pdo->prepare("DELETE FROM chimera_bans WHERE target = ?")->execute(['ip:' . $ip]);
                $pdo->prepare("INSERT INTO chimera_bans (target, reason, expires_at) VALUES (?, ?, ?)")->execute(['ip:' . $ip, $reason, $exp]);
                if ($hwid !== '') {
                    $pdo->prepare("DELETE FROM chimera_bans WHERE target = ?")->execute(['hwid:' . $hwid]);
                    $pdo->prepare("INSERT INTO chimera_bans (target, reason, expires_at) VALUES (?, ?, ?)")->execute(['hwid:' . $hwid, $reason, $exp]);
                }
            }
        } catch (Throwable $e) {}

        http_response_code(403);
        echo json_encode([
            'status'  => 'blocked',
            'error'   => $reason,
            'notice'  => defined('CHIMERA_CRACK_NOTICE') ? CHIMERA_CRACK_NOTICE : 'Crack cái con cặc',
            'message' => defined('CHIMERA_CRACK_NOTICE') ? CHIMERA_CRACK_NOTICE : 'Crack cái con cặc',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function getFlag(string $name, bool $default): bool {
        self::initTables();
        try {
            $pdo = Database::getConnection();
            $pdo->exec("CREATE TABLE IF NOT EXISTS chimera_shield_settings (k VARCHAR(64) PRIMARY KEY, v VARCHAR(16) NOT NULL)");
            $st = $pdo->prepare("SELECT v FROM chimera_shield_settings WHERE k = ?");
            $st->execute([$name]);
            $val = $st->fetchColumn();
            if ($val !== false) return $val === '1';
        } catch (Throwable $e) {}
        return $default;
    }

    public static function setFlag(string $name, bool $val): void {
        self::initTables();
        try {
            $pdo = Database::getConnection();
            $pdo->exec("CREATE TABLE IF NOT EXISTS chimera_shield_settings (k VARCHAR(64) PRIMARY KEY, v VARCHAR(16) NOT NULL)");
            $pdo->prepare("DELETE FROM chimera_shield_settings WHERE k = ?")->execute([$name]);
            $pdo->prepare("INSERT INTO chimera_shield_settings (k, v) VALUES (?, ?)")->execute([$name, $val ? '1' : '0']);
        } catch (Throwable $e) {}
    }

    public static function clearAllBans(): void {
        self::initTables();
        try {
            $pdo = Database::getConnection();
            $pdo->exec("DELETE FROM chimera_bans");
        } catch (Throwable $e) {}
    }

    public static function getStats(): array {
        self::initTables();
        $out = ['bans' => 0, 'logs' => 0];
        try {
            $pdo = Database::getConnection();
            $out['bans'] = (int)$pdo->query("SELECT COUNT(*) FROM chimera_bans WHERE expires_at > " . time())->fetchColumn();
            $out['logs'] = (int)$pdo->query("SELECT COUNT(*) FROM chimera_crack_logs")->fetchColumn();
        } catch (Throwable $e) {}
        return $out;
    }
}
