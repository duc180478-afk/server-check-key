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
<meta name="theme-color" content="#F6F4EE">
<title>Panel Login — KEY.SYS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,wght@0,400;0,500;0,600&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{--paper:#F6F4EE;--card:#FCFBF7;--ink:#191B17;--ink-soft:#4C4A42;--grid:#DEDAD0;--accent:#2B4FD8;--accent-ink:#F6F4EE;--amber:#C4780A;--line:#191B17;color-scheme:light;}
@media(prefers-color-scheme:dark){
  :root{--paper:#15160F;--card:#1C1D15;--ink:#EDEAE0;--ink-soft:#A6A295;--grid:#2B2C22;--accent:#7D95F5;--accent-ink:#15160F;--amber:#E0A03E;--line:#EDEAE0;color-scheme:dark;}
}
*{box-sizing:border-box;}
body{margin:0;background:var(--paper);color:var(--ink);font-family:"Newsreader",serif;
  background-image:linear-gradient(var(--grid) 1px,transparent 1px),linear-gradient(90deg,var(--grid) 1px,transparent 1px);
  background-size:28px 28px;}
.bar{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:20px 6vw;border-bottom:1.3px solid var(--line);flex-wrap:wrap;}
.wm{font-family:"IBM Plex Mono",monospace;font-size:13px;letter-spacing:.04em;}
.wm b{color:var(--accent);}
.who{font-family:"IBM Plex Mono",monospace;font-size:11px;color:var(--ink-soft);}

.login-wrap{min-height:calc(100vh - 90px);display:flex;align-items:center;justify-content:center;padding:34px 16px;}
.login-card{width:100%;max-width:400px;position:relative;z-index:1;background:var(--card);border:1.3px solid var(--line);box-shadow:7px 7px 0 var(--line);margin-top:18px;transform:translate(-2px,-2px);transition:transform .18s ease,box-shadow .18s ease;}
.login-card:hover{transform:translate(0,0);box-shadow:4px 4px 0 var(--line);}
.login-card::before{content:"";position:absolute;inset:0;z-index:2;pointer-events:none;
  background-image:linear-gradient(110deg,transparent 47%,color-mix(in srgb,var(--accent) 16%,transparent) 49%,rgba(255,255,255,.35) 51%,transparent 53%);
  background-size:400% 100%;background-repeat:no-repeat;background-position:100% 0;opacity:0;}
.login-card:hover::before{opacity:1;animation:cardGlint .9s ease-out both;}
@keyframes cardGlint{from{background-position:100% 0;opacity:1;}to{background-position:0% 0;opacity:0;}}
.tabs{display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:16px 22px;border-bottom:1.3px solid var(--line);position:relative;z-index:1;}
.tab{font-family:"IBM Plex Mono",monospace;font-size:11.5px;text-align:center;padding:10px;text-decoration:none;color:var(--ink);border:1.3px solid var(--line);transition:background .18s,color .18s,border-color .18s;}
.tab.on{background:var(--accent);color:var(--accent-ink);border-color:var(--line);}
.tab:not(.on):hover{border-color:var(--accent);color:var(--accent);}
.cbody{padding:20px 22px;position:relative;z-index:1;}
.f{display:flex;flex-direction:column;gap:6px;margin-bottom:12px;min-width:0;}
label{font-family:"IBM Plex Mono",monospace;font-size:10px;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.06em;}
input{width:100%;border:1.3px solid var(--line);background:var(--paper);color:var(--ink);padding:11px 12px;font-family:"IBM Plex Mono",monospace;font-size:13px;}
input:focus{outline:2px solid var(--accent);outline-offset:1px;}
.btn{width:100%;border:1.3px solid var(--line);background:var(--accent);color:var(--accent-ink);padding:13px 16px;font-family:"IBM Plex Mono",monospace;font-size:12.5px;cursor:pointer;transition:transform .12s,letter-spacing .2s,filter .2s;}
.btn:hover{letter-spacing:.05em;filter:brightness(1.06);}
.btn:active{transform:translate(2px,2px);}
.err{border:1.3px solid var(--amber);color:var(--amber);padding:10px 12px;font-family:"IBM Plex Mono",monospace;font-size:11.5px;margin-bottom:14px;background:var(--card);}
.ok{border:1.3px solid var(--accent);color:var(--accent);padding:10px 12px;font-family:"IBM Plex Mono",monospace;font-size:11.5px;margin-bottom:14px;background:var(--card);}
.hint{font-family:"IBM Plex Mono",monospace;font-size:10.5px;color:var(--ink-soft);margin:14px 0 0;line-height:1.7;}
.hint b{color:var(--ink);}
@media(max-width:560px){.bar{padding:14px 20px;}}
</style>
</head>
<body>
<header class="bar">
  <span class="wm"><b>KEY</b>.ADMIN — MDUCDZ</span>
  <span class="who">khu vực quản trị · yêu cầu đăng nhập</span>
</header>

<div class="login-wrap">
  <div class="login-card">
    <div class="tabs">
      <a href="?tab=login" class="tab <?= $tab==='login'?'on':'' ?>">Đăng nhập</a>
      <a href="?tab=register" class="tab <?= $tab==='register'?'on':'' ?>">Đăng ký reseller</a>
    </div>
    <div class="cbody">
      <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($okMsg): ?><div class="ok"><?= htmlspecialchars($okMsg) ?></div><?php endif; ?>

      <?php if ($tab === 'register'): ?>
      <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="form" value="register">
        <div class="f"><label>Username (tự đặt)</label><input name="username" required pattern="[a-zA-Z0-9_]{3,32}" placeholder="username"></div>
        <div class="f"><label>Mật khẩu (tự đặt)</label><input type="password" name="password" required minlength="6"></div>
        <div class="f"><label>Token đăng ký</label><input name="reg_token" required placeholder="XXXXXXXX" autocomplete="off"></div>
        <button class="btn" type="submit">Tạo tài khoản reseller</button>
      </form>
      <p class="hint">Token: <b>XXXXXXXX</b> — chỉ dùng lúc đăng ký, không dùng khi tạo key.</p>
      <?php else: ?>
      <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="form" value="login">
        <div class="f"><label>Username</label><input name="username" required placeholder="username" autofocus></div>
        <div class="f"><label>Mật khẩu</label><input type="password" name="password" required></div>
        <button class="btn" type="submit">Đăng nhập</button>
      </form>
      <p class="hint">Admin: username <b>mducdz</b> + mật khẩu config.<br>Reseller: tài khoản đã đăng ký.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
