<?php
/**
 * API tạo key bằng POST — cho reseller / panel ngoài
 *
 * POST (form hoặc JSON):
 *   token   = RESSELLER_API_TOKEN (bắt buộc)
 *   app     = fakelag | proxy | ...
 *   days    = số ngày (0 hoặc bỏ = vĩnh viễn)
 *   limit   = số thiết bị HWID (mặc định 1)
 *   prefix  = tùy chọn (mặc định theo app)
 *   note    = ghi chú
 *   count   = số key (1–50, mặc định 1)
 *
 * Trả JSON:
 *   { "status":"success", "keys":["FLFF-xxx", ...], "app":"fakelag", ... }
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// ★ Đổi token này trước khi giao Noa (hoặc set env RESSELLER_API_TOKEN trên Render)
define('RESELLER_API_TOKEN', getenv('RESELLER_API_TOKEN') ?: 'NOA_TOKEN_DOI_NGAY_123');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Chỉ nhận POST']);
    exit;
}

// Đọc JSON body nếu có
$raw = file_get_contents('php://input');
$json = [];
if ($raw && str_starts_with(trim($raw), '{')) {
    $json = json_decode($raw, true) ?: [];
}

$get = function ($k, $default = '') use ($json) {
    if (isset($_POST[$k])) return $_POST[$k];
    if (isset($json[$k])) return $json[$k];
    return $default;
};

$token = trim((string)$get('token', ''));
// Bearer header
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if ($token === '' && preg_match('/Bearer\s+(\S+)/i', $auth, $m)) {
    $token = $m[1];
}

if ($token === '' || !hash_equals(RESELLER_API_TOKEN, $token)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Token không hợp lệ']);
    exit;
}

$ip = function_exists('getClientIp') ? getClientIp() : ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
if (function_exists('checkRateLimit') && !checkRateLimit('reseller_api_' . $ip, 30, 60)) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Quá nhiều request']);
    exit;
}

$app = strtolower(trim((string)$get('app', 'fakelag')));
if (!defined('SYSTEM_APPS') || !isset(SYSTEM_APPS[$app])) {
    // fallback cho phép fakelag/proxy
    $allowed = ['fakelag', 'proxy', 'other'];
    if (!in_array($app, $allowed, true)) {
        echo json_encode(['status' => 'error', 'message' => 'App không hợp lệ']);
        exit;
    }
}

$daysIn = $get('days', '');
$days = ($daysIn === '' || $daysIn === null) ? null : (int)$daysIn;
if ($days !== null && $days <= 0) $days = null;

$limit = max(1, min(9999, (int)$get('limit', 1)));
$count = max(1, min(50, (int)$get('count', 1)));
$note  = trim((string)$get('note', 'reseller-api'));
$prefix = trim((string)$get('prefix', ''));

if ($prefix === '') {
    if (defined('SYSTEM_APPS') && isset(SYSTEM_APPS[$app]['default_prefix'])) {
        $prefix = SYSTEM_APPS[$app]['default_prefix'];
    } else {
        $prefix = strtoupper(substr($app, 0, 4));
    }
}
$prefix = preg_replace('/[^A-Za-z0-9]/', '', $prefix);
if ($prefix === '') $prefix = 'KEY';

try {
    $keys = [];
    if ($count === 1) {
        $keys[] = createKey($prefix, $days, $limit, $note . ' [api]', $app);
    } else {
        $keys = createKeyBatch($prefix, $count, $days, $limit, $note . ' [api]', $app);
    }
    echo json_encode([
        'status'  => 'success',
        'app'     => $app,
        'days'    => $days,
        'limit'   => $limit,
        'count'   => count($keys),
        'keys'    => $keys,
        'key'     => $keys[0] ?? null,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Lỗi tạo key']);
}
