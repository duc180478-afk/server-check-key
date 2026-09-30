<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

/** Token cứng để reseller tự đăng ký tài khoản */
define('RESELLER_REGISTER_TOKEN', 'RESELLER');

function adminUsersInit() {
    static $done = false;
    if ($done) return;
    $pdo = Database::getConnection();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'pgsql') {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
            id SERIAL PRIMARY KEY,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'reseller',
            active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL
        )");
    } else {
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'reseller',
            active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL
        )");
    }
    // Đảm bảo cột created_by trên keys (gắn key với reseller)
    try {
        if ($driver === 'pgsql') {
            $pdo->exec("ALTER TABLE keys ADD COLUMN IF NOT EXISTS created_by TEXT DEFAULT ''");
        } else {
            // SQLite: thử add, ignore nếu đã có
            @$pdo->exec("ALTER TABLE keys ADD COLUMN created_by TEXT DEFAULT ''");
        }
    } catch (Throwable $e) { /* already exists */ }
    $done = true;
}

function adminTryLogin($username, $password) {
    adminUsersInit();
    $username = strtolower(trim($username));
    if ($username === '') return ['ok' => false];

    $pdo = Database::getConnection();
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? AND active = 1 LIMIT 1");
    $stmt->execute([$username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && password_verify($password, $row['password_hash'])) {
        $role = ($row['role'] === 'admin') ? 'admin' : 'reseller';
        return ['ok' => true, 'username' => $row['username'], 'role' => $role];
    }

    // Admin gốc (config) — username admin
    if ($username === 'admin' && defined('ADMIN_PASSWORD_HASH') && password_verify($password, ADMIN_PASSWORD_HASH)) {
        return ['ok' => true, 'username' => 'admin', 'role' => 'admin'];
    }
    return ['ok' => false];
}

/** Reseller tự đăng ký: bắt buộc token = RESELLER */
function adminRegisterReseller($username, $password, $token) {
    adminUsersInit();
    if (!hash_equals(RESELLER_REGISTER_TOKEN, (string)$token)) {
        return ['ok' => false, 'message' => 'Token đăng ký không đúng'];
    }
    $username = strtolower(trim($username));
    if (!preg_match('/^[a-z0-9_]{3,32}$/', $username)) {
        return ['ok' => false, 'message' => 'Username 3-32 ký tự (a-z, 0-9, _)'];
    }
    if ($username === 'admin') {
        return ['ok' => false, 'message' => 'Không dùng tên admin'];
    }
    if (strlen($password) < 6) {
        return ['ok' => false, 'message' => 'Mật khẩu tối thiểu 6 ký tự'];
    }
    $pdo = Database::getConnection();
    $chk = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
    $chk->execute([$username]);
    if ($chk->fetch()) {
        return ['ok' => false, 'message' => 'Username đã tồn tại'];
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins = $pdo->prepare("INSERT INTO admin_users (username, password_hash, role, active, created_at) VALUES (?, ?, 'reseller', 1, ?)");
    $ins->execute([$username, $hash, date('Y-m-d H:i:s')]);
    return ['ok' => true, 'message' => 'Tạo tài khoản reseller thành công', 'username' => $username];
}

function adminRequireLogin() {
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: login.php');
        exit;
    }
}
function adminRole() { return $_SESSION['admin_role'] ?? 'admin'; }
function adminIsReseller() { return adminRole() === 'reseller'; }
function adminIsAdmin() { return adminRole() === 'admin'; }
function adminUsername() { return $_SESSION['admin_username'] ?? ''; }
