<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/admin_auth.php';

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Phiên không hợp lệ, thử lại!';
    } else {
        $attempts = $_SESSION['login_attempts'] ?? 0;
        $lastAttempt = $_SESSION['last_attempt'] ?? 0;
        if ($attempts >= 5 && (time() - $lastAttempt) < 300) {
            $wait = 300 - (time() - $lastAttempt);
            $error = "Sai quá nhiều lần! Thử lại sau {$wait}s.";
        } else {
            $res = adminTryLogin(trim($_POST['username'] ?? ''), $_POST['password'] ?? '');
            if ($res['ok']) {
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $res['username'];
                $_SESSION['admin_role'] = $res['role'];
                unset($_SESSION['login_attempts'], $_SESSION['last_attempt']);
                header('Location: index.php');
                exit;
            }
            $_SESSION['login_attempts'] = $attempts + 1;
            $_SESSION['last_attempt'] = time();
            $error = 'Sai tài khoản hoặc mật khẩu!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MDUCDZ · Panel Login</title>
<style>
:root { --bg:#0b0f14; --card:#121a24; --line:#1e2a3a; --accent:#3b82f6; --text:#e8eef7; --muted:#8b9bb4; --danger:#f87171; }
* { box-sizing:border-box; margin:0; padding:0; }
body { min-height:100vh; display:flex; align-items:center; justify-content:center; font-family:Inter,system-ui,sans-serif; background:radial-gradient(1200px 600px at 20% -10%, #1e3a5f55, transparent), var(--bg); color:var(--text); }
.card { width:100%; max-width:400px; margin:16px; background:var(--card); border:1px solid var(--line); border-radius:16px; padding:28px 24px; box-shadow:0 20px 50px #0008; }
.logo { font-weight:800; font-size:1.35rem; letter-spacing:.02em; margin-bottom:6px; }
.logo span { color:var(--accent); }
.sub { color:var(--muted); font-size:.9rem; margin-bottom:22px; }
label { display:block; font-size:.78rem; color:var(--muted); margin:12px 0 6px; text-transform:uppercase; letter-spacing:.04em; }
input { width:100%; padding:12px 14px; border-radius:10px; border:1px solid var(--line); background:#0a1018; color:var(--text); font-size:1rem; }
input:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px #3b82f633; }
button { margin-top:20px; width:100%; padding:13px; border:0; border-radius:10px; background:linear-gradient(135deg,#3b82f6,#2563eb); color:#fff; font-weight:700; font-size:1rem; cursor:pointer; }
button:hover { filter:brightness(1.08); }
.err { background:#3f1d1d; color:var(--danger); padding:10px 12px; border-radius:8px; font-size:.9rem; margin-bottom:12px; }
</style>
</head>
<body>
<div class="card">
  <div class="logo">MDU<span>CDZ</span> Panel</div>
  <p class="sub">Đăng nhập admin / reseller để tạo key</p>
  <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post">
    <?= csrfField() ?>
    <label>Tài khoản</label>
    <input name="username" autocomplete="username" placeholder="admin hoặc noa" required>
    <label>Mật khẩu</label>
    <input type="password" name="password" autocomplete="current-password" required>
    <button type="submit">Đăng nhập</button>
  </form>
</div>
</body>
</html>
