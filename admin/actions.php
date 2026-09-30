<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/key_helpers.php';
adminRequireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    header('Location: index.php?msg=' . urlencode('Invalid'));
    exit;
}

$action = $_POST['action'] ?? '';
if (adminIsReseller() && in_array($action, ['extend','toggle_status'], true)) {
    header('Location: index.php?msg=' . urlencode('Reseller không có quyền'));
    exit;
}

$msg = '';
$createdKeys = [];

if ($action === 'create_single' || $action === 'create_batch') {
    $app = $_POST['app'] ?? 'fakelag';
    $mode = ($_POST['key_mode'] ?? 'random') === 'custom' ? 'custom' : 'random';
    $prefix = trim($_POST['prefix'] ?? 'FLFF');
    $daysIn = trim($_POST['days'] ?? '');
    $days = ($daysIn === '') ? null : max(1, (int)$daysIn);
    $limit = max(1, min(9999, (int)($_POST['hwid_limit'] ?? 1)));
    $note = trim($_POST['note'] ?? '');
    $count = ($action === 'create_batch' && $mode === 'random') ? max(1, min(50, (int)($_POST['count'] ?? 1))) : 1;
    $by = adminUsername();
    if (adminIsReseller()) $note = trim($note . ' [by:' . $by . ']');

    try {
        for ($i = 0; $i < $count; $i++) {
            $createdKeys[] = createKeyFlexible($prefix, $days, $limit, $note, $app, $mode, $by);
        }
        $_SESSION['flash_keys'] = $createdKeys;
        $msg = 'Đã tạo ' . count($createdKeys) . ' key';
    } catch (Throwable $e) {
        $msg = $e->getMessage();
    }
} elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $row = getKeyById($id);
        if (!$row) {
            $msg = 'Key không tồn tại';
        } elseif (adminIsReseller() && ($row['created_by'] ?? '') !== adminUsername()) {
            $msg = 'Chỉ xóa key của bạn';
        } else {
            Database::getConnection()->prepare('DELETE FROM keys WHERE id=?')->execute([$id]);
            $msg = 'Đã xóa #' . $id;
        }
    }
} elseif ($action === 'toggle_status') {
    $id = (int)($_POST['id'] ?? 0);
    $row = getKeyById($id);
    if ($row) {
        $next = ($row['status'] === 'active') ? 'disabled' : 'active';
        updateKey($id, ['status' => $next]);
        $msg = "#$id → $next";
    }
} elseif ($action === 'extend') {
    $id = (int)($_POST['id'] ?? 0);
    $add = max(1, (int)($_POST['days'] ?? 7));
    $row = getKeyById($id);
    if ($row) {
        $base = !empty($row['expires_at']) ? strtotime($row['expires_at']) : time();
        if ($base < time()) $base = time();
        updateKey($id, ['expires_at' => date('Y-m-d H:i:s', $base + $add * 86400), 'status' => 'active']);
        $msg = "Gia hạn #$id +{$add}d";
    }
}

header('Location: index.php?msg=' . urlencode($msg));
exit;
