<?php
/**
 * Multi-user admin/reseller
 * - admin: full quyền
 * - reseller: chỉ tạo key (single/batch)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

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
    // Seed reseller Noa nếu chưa có
    $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
    $stmt->execute(['noa']);
    if (!$stmt->fetch()) {
        // password: Noa@2026  — đổi sau khi giao
        $hash = '$2y$10$loIKvtWvOfbZX/cqmjuSl.xngqRDlMNNSj7vkduoEcqX/2wn4wUtW';
        $ins = $pdo->prepare("INSERT INTO admin_users (username, password_hash, role, active, created_at) VALUES (?, ?, 'reseller', 1, ?)");
        $ins->execute(['noa', $hash, date('Y-m-d H:i:s')]);
    }
    $done = true;
}

function adminTryLogin($username, $password) {
    adminUsersInit();
    $username = strtolower(trim($username));

    // 1) Bảng admin_users
    if ($username !== '') {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? AND active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && password_verify($password, $row['password_hash'])) {
            return [
                'ok' => true,
                'username' => $row['username'],
                'role' => ($row['role'] === 'admin') ? 'admin' : 'reseller',
            ];
        }
    }

    // 2) Admin gốc (chỉ mật khẩu cũ, username để trống hoặc "admin")
    if (($username === '' || $username === 'admin') && password_verify($password, ADMIN_PASSWORD_HASH)) {
        return ['ok' => true, 'username' => 'admin', 'role' => 'admin'];
    }

    return ['ok' => false];
}

function adminRequireLogin() {
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: login.php');
        exit;
    }
}

function adminRole() {
    return $_SESSION['admin_role'] ?? 'admin';
}

function adminIsReseller() {
    return adminRole() === 'reseller';
}

function adminIsAdmin() {
    return adminRole() === 'admin';
}
