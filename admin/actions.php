<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_single':
            $prefix = $_POST['prefix'] ?? 'KEY';
            $days = $_POST['days'] ? intval($_POST['days']) : null;
            $limit = intval($_POST['hwid_limit'] ?? 1);
            $note = $_POST['note'] ?? '';
            $key = createKey($prefix, $days, $limit, $note);
            $msg = "Đã tạo key: $key";
            break;
            
        case 'create_batch':
            $prefix = $_POST['prefix'] ?? 'KEY';
            $count = intval($_POST['count'] ?? 1);
            $days = $_POST['days'] ? intval($_POST['days']) : null;
            $limit = intval($_POST['hwid_limit'] ?? 1);
            $note = $_POST['note'] ?? '';
            $keys = createKeyBatch($prefix, $count, $days, $limit, $note);
            $msg = "Đã tạo " . count($keys) . " key.";
            break;
            
        case 'delete':
            $id = intval($_POST['id']);
            deleteKey($id);
            $msg = "Đã xóa key ID $id";
            break;
            
        case 'extend':
            $id = intval($_POST['id']);
            $days = intval($_POST['days']);
            $keyData = getKeyById($id);
            if ($keyData) {
                $newExpire = date('Y-m-d H:i:s', time() + $days * 86400);
                updateKey($id, ['expires_at' => $newExpire, 'status' => 'active']);
                $msg = "Đã gia hạn key {$keyData['key']} thêm $days ngày.";
            }
            break;
            
        case 'toggle_status':
            $id = intval($_POST['id']);
            $keyData = getKeyById($id);
            if ($keyData) {
                $newStatus = $keyData['status'] === 'active' ? 'disabled' : 'active';
                updateKey($id, ['status' => $newStatus]);
                $msg = "Đã chuyển trạng thái key {$keyData['key']} thành $newStatus.";
            }
            break;
    }
    
    header('Location: index.php?msg=' . urlencode($msg ?? ''));
    exit;
}