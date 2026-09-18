<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
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

        // Sai >= 5 lần -> khóa 5 phút
        if ($attempts >= 5 && (time() - $lastAttempt) < 300) {
            $wait = 300 - (time() - $lastAttempt);
            $error = "Sai quá nhiều lần! Thử lại sau $wait giây.";
        } elseif (password_verify($_POST['password'] ?? '', ADMIN_PASSWORD_HASH)) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            unset($_SESSION['login_attempts'], $_SESSION['last_attempt']);
            header('Location: index.php');
            exit;
        } else {
            $_SESSION['login_attempts'] = $attempts + 1;
            $_SESSION['last_attempt'] = time();
            $error = 'Sai mật khẩu!';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Đăng nhập Admin</title>
</head>
<body>
    <h2>Đăng nhập Admin</h2>
    <?php if (isset($error)) echo "<p style='color:red'>" . htmlspecialchars($error) . "</p>"; ?>
    <form method="post">
        <?= csrfField() ?>
        <input type="password" name="password" placeholder="Mật khẩu" required>
        <button type="submit">Đăng nhập</button>
    </form>
</body>
</html>