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
$okMsg = '';
$tab = $_POST['form'] ?? $_GET['tab'] ?? 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Phiên không hợp lệ';
    } elseif (($_POST['form'] ?? '') === 'register') {
        $tab = 'register';
        $res = adminRegisterReseller(
            $_POST['username'] ?? '',
            $_POST['password'] ?? '',
            $_POST['reg_token'] ?? ''
        );
        if ($res['ok']) {
            $okMsg = $res['message'] . '. Hãy đăng nhập.';
            $tab = 'login';
        } else {
            $error = $res['message'];
        }
    } else {
        $tab = 'login';
        $attempts = $_SESSION['login_attempts'] ?? 0;
        $lastAttempt = $_SESSION['last_attempt'] ?? 0;
        if ($attempts >= 5 && (time() - $lastAttempt) < 300) {
            $error = 'Sai quá nhiều lần, đợi vài phút.';
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
            $error = 'Sai tài khoản hoặc mật khẩu';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel Login</title>
<style>
:root{--bg:#0b0f14;--card:#121a24;--line:#1e2a3a;--accent:#3b82f6;--text:#e8eef7;--muted:#8b9bb4;--danger:#f87171;--ok:#34d399}
*{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:system-ui,sans-serif;background:var(--bg);color:var(--text)}
.card{width:100%;max-width:400px;margin:16px;background:var(--card);border:1px solid var(--line);border-radius:16px;padding:24px}
.tabs{display:flex;gap:8px;margin-bottom:16px}
.tabs a{flex:1;text-align:center;padding:10px;border-radius:8px;color:var(--muted);text-decoration:none;border:1px solid var(--line);font-size:.9rem}
.tabs a.on{background:var(--accent);color:#fff;border-color:transparent}
label{display:block;font-size:.75rem;color:var(--muted);margin:12px 0 6px;text-transform:uppercase}
input{width:100%;padding:12px;border-radius:10px;border:1px solid var(--line);background:#0a1018;color:var(--text)}
button{margin-top:18px;width:100%;padding:13px;border:0;border-radius:10px;background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;font-weight:700;cursor:pointer}
.err{background:#3f1d1d;color:var(--danger);padding:10px;border-radius:8px;margin-bottom:10px;font-size:.9rem}
.ok{background:#0f2a1d;color:var(--ok);padding:10px;border-radius:8px;margin-bottom:10px;font-size:.9rem}
.hint{font-size:.8rem;color:var(--muted);margin-top:12px;line-height:1.5}
</style>
</head>
<body>
<div class="card">
  <div class="tabs">
    <a href="?tab=login" class="<?= $tab==='login'?'on':'' ?>">Đăng nhập</a>
    <a href="?tab=register" class="<?= $tab==='register'?'on':'' ?>">Đăng ký reseller</a>
  </div>
  <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($okMsg): ?><div class="ok"><?= htmlspecialchars($okMsg) ?></div><?php endif; ?>

  <?php if ($tab === 'register'): ?>
  <form method="post">
    <?= csrfField() ?>
    <input type="hidden" name="form" value="register">
    <label>Username (tự đặt)</label>
    <input name="username" required pattern="[a-zA-Z0-9_]{3,32}" placeholder="vd: noa_shop">
    <label>Mật khẩu (tự đặt)</label>
    <input type="password" name="password" required minlength="6">
    <label>Token đăng ký</label>
    <input name="reg_token" required placeholder="RESELLER" autocomplete="off">
    <button type="submit">Tạo tài khoản reseller</button>
  </form>
  <p class="hint">Token cứng: <b>RESELLER</b> — chỉ dùng lúc đăng ký, không dùng khi tạo key.</p>
  <?php else: ?>
  <form method="post">
    <?= csrfField() ?>
    <input type="hidden" name="form" value="login">
    <label>Username</label>
    <input name="username" required placeholder="admin hoặc reseller">
    <label>Mật khẩu</label>
    <input type="password" name="password" required>
    <button type="submit">Đăng nhập</button>
  </form>
  <p class="hint">Admin: username <b>admin</b> + mật khẩu config.<br>Reseller: tài khoản đã đăng ký.</p>
  <?php endif; ?>
</div>
</body>
</html>
