<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/admin_auth.php';

adminRequireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    header('Location: index.php?msg=' . urlencode('CSRF invalid'));
    exit;
}

$action = $_POST['action'] ?? '';
$role = adminRole();
$resellerBlocked = ['delete', 'extend', 'toggle_status'];
if ($role === 'reseller' && in_array($action, $resellerBlocked, true)) {
    header('Location: index.php?msg=' . urlencode('Reseller không có quyền.'));
    exit;
}

$msg = '';
$createdKeys = [];

switch ($action) {
    case 'create_single':
    case 'create_batch':
        $app = $_POST['app'] ?? 'fakelag';
        if (defined('SYSTEM_APPS') && !isset(SYSTEM_APPS[$app])) $app = 'fakelag';
        $prefix = preg_replace('/[^A-Za-z0-9]/', '', $_POST['prefix'] ?? 'FLFF');
        if ($prefix === '') $prefix = 'KEY';
        $daysIn = trim($_POST['days'] ?? '');
        $days = ($daysIn === '') ? null : max(1, (int)$daysIn);
        $limit = max(1, min(9999, (int)($_POST['hwid_limit'] ?? 1)));
        $note = trim($_POST['note'] ?? '');
        $count = ($action === 'create_batch') ? max(1, min(50, (int)($_POST['count'] ?? 1))) : 1;
        if (adminIsReseller()) {
            $note = trim($note . ' [by:' . ($_SESSION['admin_username'] ?? 'reseller') . ']');
        }
        try {
            if ($count === 1) {
                $createdKeys[] = createKey($prefix, $days, $limit, $note, $app);
            } else {
                $createdKeys = createKeyBatch($prefix, $count, $days, $limit, $note, $app);
            }
            $_SESSION['flash_keys'] = $createdKeys;
            $msg = 'Đã tạo ' . count($createdKeys) . ' key.';
        } catch (Throwable $e) {
            $msg = 'Lỗi tạo key.';
        }
        break;

    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo = Database::getConnection();
            $pdo->prepare('DELETE FROM keys WHERE id = ?')->execute([$id]);
            $msg = 'Đã xóa key #' . $id;
        }
        break;

    case 'toggle_status':
        $id = (int)($_POST['id'] ?? 0);
        $row = getKeyById($id);
        if ($row) {
            $next = ($row['status'] === 'active') ? 'disabled' : 'active';
            updateKey($id, ['status' => $next]);
            $msg = "Key #{$id} → {$next}";
        }
        break;

    case 'extend':
        $id = (int)($_POST['id'] ?? 0);
        $add = max(1, (int)($_POST['days'] ?? 1));
        $row = getKeyById($id);
        if ($row) {
            $base = !empty($row['expires_at']) ? strtotime($row['expires_at']) : time();
            if ($base < time()) $base = time();
            $newExp = date('Y-m-d H:i:s', $base + $add * 86400);
            updateKey($id, ['expires_at' => $newExp, 'status' => 'active']);
            $msg = "Gia hạn #{$id} +{$add} ngày";
        }
        break;
}

header('Location: index.php?msg=' . urlencode($msg));
exit;
