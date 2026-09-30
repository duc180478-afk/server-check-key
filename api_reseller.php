<?php
/**
 * API reseller — KHÔNG dùng token API.
 * Xác thực bằng username + password (reseller đã đăng ký).
 *
 * POST JSON hoặc form:
 *   user, pass  (bắt buộc)
 *   action = create | list   (mặc định create)
 *
 * create:
 *   app, days, limit, note
 *   key_mode = random | custom
 *   prefix  = prefix hoặc đúng key nếu custom
 *   count   = 1-50 (chỉ random)
 *
 * list:
 *   chỉ trả keys do reseller này tạo (created_by = user)
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/admin/admin_auth.php';
require_once __DIR__ . '/admin/key_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status'=>'error','message'=>'Chỉ nhận POST']);
    exit;
}

$raw = file_get_contents('php://input');
$json = (is_string($raw) && str_starts_with(trim($raw), '{')) ? (json_decode($raw, true) ?: []) : [];
$g = function($k, $d='') use ($json) {
    if (isset($_POST[$k])) return $_POST[$k];
    if (isset($json[$k])) return $json[$k];
    return $d;
};

$user = strtolower(trim((string)$g('user', $g('username', ''))));
$pass = (string)$g('pass', $g('password', ''));
if ($user === '' || $pass === '') {
    http_response_code(401);
    echo json_encode(['status'=>'error','message'=>'Thiếu user/pass']);
    exit;
}

$auth = adminTryLogin($user, $pass);
if (!$auth['ok'] || $auth['role'] !== 'reseller') {
    // Admin cũng cho phép nếu muốn dùng API; user yêu cầu post chỉ key reseller
    if (!$auth['ok']) {
        http_response_code(401);
        echo json_encode(['status'=>'error','message'=>'Sai user/pass']);
        exit;
    }
}

$ip = function_exists('getClientIp') ? getClientIp() : ($_SERVER['REMOTE_ADDR'] ?? '');
if (function_exists('checkRateLimit') && !checkRateLimit('reseller_post_' . $ip, 40, 60)) {
    http_response_code(429);
    echo json_encode(['status'=>'error','message'=>'Rate limit']);
    exit;
}

$action = strtolower(trim((string)$g('action', 'create')));
$username = $auth['username'];
$role = $auth['role'];

if ($action === 'list') {
    // Chỉ key của reseller này (admin list all nếu role admin)
    $rows = listKeysForUser($username, $role === 'admin' ? 'admin' : 'reseller', 200);
    $out = [];
    foreach ($rows as $r) {
        if ($role !== 'admin' && ($r['created_by'] ?? '') !== $username) continue;
        $out[] = [
            'key' => $r['key'],
            'app' => $r['app'] ?? '',
            'expires_at' => $r['expires_at'] ?? null,
            'hwid_limit' => (int)($r['hwid_limit'] ?? 1),
            'status' => $r['status'] ?? '',
            'created_by' => $r['created_by'] ?? '',
        ];
    }
    echo json_encode(['status'=>'success','count'=>count($out),'keys'=>$out], JSON_UNESCAPED_UNICODE);
    exit;
}

// create — chỉ reseller (hoặc admin)
$app = strtolower(trim((string)$g('app', 'fakelag')));
$mode = (strtolower((string)$g('key_mode', 'random')) === 'custom') ? 'custom' : 'random';
$prefix = trim((string)$g('prefix', $g('key', 'FLFF')));
$daysIn = $g('days', '');
$days = ($daysIn === '' || $daysIn === null) ? null : (int)$daysIn;
if ($days !== null && $days <= 0) $days = null;
$limit = max(1, min(9999, (int)$g('limit', 1)));
$note = trim((string)$g('note', ''));
$count = ($mode === 'random') ? max(1, min(50, (int)$g('count', 1))) : 1;
if ($role === 'reseller') $note = trim($note . ' [by:' . $username . ']');

try {
    $keys = [];
    for ($i = 0; $i < $count; $i++) {
        $keys[] = createKeyFlexible($prefix, $days, $limit, $note, $app, $mode, $username);
    }
    echo json_encode([
        'status' => 'success',
        'keys' => $keys,
        'key' => $keys[0] ?? null,
        'created_by' => $username,
        'mode' => $mode,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
