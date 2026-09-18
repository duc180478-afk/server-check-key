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
 $returnApp = $_POST['return_app'] ?? 'all';
if (!isset(SYSTEM_APPS[$returnApp]) && $returnApp !== 'all') {
    $returnApp = 'all';
}
 $returnUrl = 'index.php?msg=' . urlencode($msg) . ($returnApp !== 'all' ? '&app=' . urlencode($returnApp) : '');

switch ($action) {
    case 'create_single':
        $app = $_POST['app'] ?? 'other';
        if (!isset(SYSTEM_APPS[$app])) $app = 'other';
        // Prefix TÙY Ý theo admin nhập (mặc định gợi ý theo app)
        $prefix = strtoupper(trim($_POST['prefix'] ?? ''));
        if ($prefix === '') $prefix = SYSTEM_APPS[$app]['default_prefix'];
        $daysRaw = trim($_POST['days'] ?? '');
        $days = ($daysRaw === '') ? null : intval($daysRaw);
        if ($days !== null && $days <= 0) {
            $msg = 'Số ngày phải lớn hơn 0 (bỏ trống = vĩnh viễn).';
            header('Location: index.php?msg=' . urlencode($msg) . ($returnApp !== 'all' ? '&app=' . urlencode($returnApp) : ''));
            exit;
        }
        $limit = max(1, intval($_POST['hwid_limit'] ?? 1));
        $note = trim($_POST['note'] ?? '');
        $key = createKey($prefix, $days, $limit, $note, $app);
        $msg = "Đã tạo key [" . SYSTEM_APPS[$app]['label'] . "]: $key";
        header('Location: index.php?msg=' . urlencode($msg) . ($returnApp !== 'all' ? '&app=' . urlencode($returnApp) : ''));
        exit;

    case 'create_batch':
        $app = $_POST['app'] ?? 'other';
        if (!isset(SYSTEM_APPS[$app])) $app = 'other';
        $prefix = strtoupper(trim($_POST['prefix'] ?? ''));
        if ($prefix === '') $prefix = SYSTEM_APPS[$app]['default_prefix'];
        $count = max(1, min(100, intval($_POST['count'] ?? 1)));
        $daysRaw = trim($_POST['days'] ?? '');
        $days = ($daysRaw === '') ? null : intval($daysRaw);
        if ($days !== null && $days <= 0) {
            $msg = 'Số ngày phải lớn hơn 0 (bỏ trống = vĩnh viễn).';
            header('Location: index.php?msg=' . urlencode($msg) . ($returnApp !== 'all' ? '&app=' . urlencode($returnApp) : ''));
            exit;
        }
        $limit = max(1, intval($_POST['hwid_limit'] ?? 1));
        $note = trim($_POST['note'] ?? '');
        $keys = createKeyBatch($prefix, $count, $days, $limit, $note, $app);
        $msg = "Đã tạo " . count($keys) . " key [" . SYSTEM_APPS[$app]['label'] . "] (prefix $prefix).";
        header('Location: index.php?msg=' . urlencode($msg) . ($returnApp !== 'all' ? '&app=' . urlencode($returnApp) : ''));
        exit;

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

header('Location: index.php?msg=' . urlencode($msg) . ($returnApp !== 'all' ? '&app=' . urlencode($returnApp) : ''));
exit;