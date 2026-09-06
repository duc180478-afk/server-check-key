<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Chống CSRF
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    header('Location: index.php?msg=' . urlencode('Phiên không hợp lệ (CSRF)!'));
    exit;
}

 $action = $_POST['action'] ?? '';
 $msg = '';

switch ($action) {
    case 'create_single':
        $prefix = trim($_POST['prefix'] ?? 'KEY');
        $daysRaw = trim($_POST['days'] ?? '');
        $days = ($daysRaw === '') ? null : intval($daysRaw);
        if ($days !== null && $days <= 0) {
            $msg = 'Số ngày phải lớn hơn 0 (bỏ trống = vĩnh viễn).';
            break;
        }
        $limit = max(1, intval($_POST['hwid_limit'] ?? 1));
        $note = trim($_POST['note'] ?? '');
        $key = createKey($prefix, $days, $limit, $note);
        $msg = "Đã tạo key: $key";
        break;

    case 'create_batch':
        $prefix = trim($_POST['prefix'] ?? 'KEY');
        $count = max(1, min(100, intval($_POST['count'] ?? 1))); // giới hạn tối đa 100 key/lần
        $daysRaw = trim($_POST['days'] ?? '');
        $days = ($daysRaw === '') ? null : intval($daysRaw);
        if ($days !== null && $days <= 0) {
            $msg = 'Số ngày phải lớn hơn 0 (bỏ trống = vĩnh viễn).';
            break;
        }
        $limit = max(1, intval($_POST['hwid_limit'] ?? 1));
        $note = trim($_POST['note'] ?? '');
        $keys = createKeyBatch($prefix, $count, $days, $limit, $note);
        $msg = "Đã tạo " . count($keys) . " key.";
        break;

    case 'delete':
        $id = intval($_POST['id'] ?? 0);
        deleteKey($id);
        $msg = "Đã xóa key ID $id";
        break;

    case 'extend':
        $id = intval($_POST['id'] ?? 0);
        $days = intval($_POST['days'] ?? 0);
        $keyData = getKeyById($id);
        if ($keyData && $days > 0) {
            // Gia hạn cộng dồn: cộng vào hạn cũ nếu key chưa hết hạn
            $currentExpire = $keyData['expires_at'] ? strtotime($keyData['expires_at']) : 0;
            $baseTime = ($currentExpire > time()) ? $currentExpire : time();
            $newExpire = date('Y-m-d H:i:s', $baseTime + $days * 86400);
            updateKey($id, ['expires_at' => $newExpire, 'status' => 'active']);
            $msg = "Đã gia hạn key {$keyData['key']} thêm $days ngày. Hạn mới: $newExpire";
        } else {
            $msg = 'Số ngày không hợp lệ hoặc key không tồn tại.';
        }
        break;

    case 'toggle_status':
        $id = intval($_POST['id'] ?? 0);
        $keyData = getKeyById($id);
        if ($keyData) {
            $newStatus = $keyData['status'] === 'active' ? 'disabled' : 'active';
            updateKey($id, ['status' => $newStatus]);
            $msg = "Đã chuyển trạng thái key {$keyData['key']} thành $newStatus.";
        }
        break;
}

header('Location: index.php?msg=' . urlencode($msg));
exit;