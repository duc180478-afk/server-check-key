<?php
/**
 * api_mods.php
 * Trả về danh sách Mod Skin đã mã hoá AES-256-CBC + HMAC-SHA256
 *
 * Client gửi (POST hoặc GET):
 *   key, hwid, app=modskin, ts, nonce, sig
 *
 * Canonical chữ ký request:
 *   mods|{key}|{hwid}|{ts}|{nonce}
 *
 * Response (luôn mã hoá):
 *   { "v": "<iv hex>", "d": "<base64 ciphertext>", "t": <unix>, "s": "<hmac>" }
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$APP_SECRET = 'FAKEPING_BY_MDUCDZ';

$ip = getClientIp();
if (!checkRateLimit('api_mods_' . $ip, 40, 60)) {
    http_response_code(429);
    echo json_encode(['error' => 'rate_limited']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];
$input = array_merge($_GET, $_POST, $input);

$key   = trim((string)($input['key']   ?? ''));
$hwid  = trim((string)($input['hwid']  ?? ''));
$app   = strtolower(trim((string)($input['app'] ?? 'modskin')));
$ts    = (int)($input['ts']   ?? 0);
$nonce = trim((string)($input['nonce'] ?? ''));
$sig   = strtolower(trim((string)($input['sig'] ?? '')));

if ($app !== 'modskin') {
    http_response_code(403);
    echo json_encode(['error' => 'invalid_app']);
    exit;
}

if ($key === '' || $hwid === '' || $ts === 0 || $nonce === '' || $sig === '') {
    http_response_code(403);
    echo json_encode(['error' => 'missing_params']);
    exit;
}

if (abs(time() - $ts) > 180) {
    http_response_code(403);
    echo json_encode(['error' => 'timestamp_expired']);
    exit;
}

if (function_exists('consumeNonce') && !consumeNonce($nonce)) {
    http_response_code(403);
    echo json_encode(['error' => 'replay_blocked']);
    exit;
}

$canon = "mods|{$key}|{$hwid}|{$ts}|{$nonce}";
$expected = hash_hmac('sha256', $canon, $APP_SECRET);
if (!hash_equals($expected, $sig)) {
    http_response_code(403);
    echo json_encode(['error' => 'invalid_signature']);
    exit;
}

$kd = getKeyByKey($key);
if (!$kd || strtolower($kd['status'] ?? '') !== 'active') {
    http_response_code(403);
    echo json_encode(['error' => 'key_invalid']);
    exit;
}
if (!empty($kd['expires_at']) && strtotime($kd['expires_at']) < time()) {
    http_response_code(403);
    echo json_encode(['error' => 'key_expired']);
    exit;
}

$app_from_db = strtolower(trim($kd['app'] ?? ''));
$ff_ok = function_exists('is_ff_family')
    ? (is_ff_family($app_from_db) && is_ff_family('modskin'))
    : in_array($app_from_db, ['fakelag', 'locmod', 'modskin'], true);
if (!$ff_ok && $app_from_db !== 'modskin') {
    http_response_code(403);
    echo json_encode(['error' => 'key_app_mismatch']);
    exit;
}
if (!empty($kd['bound_hwid']) && $kd['bound_hwid'] !== $hwid) {
    http_response_code(403);
    echo json_encode(['error' => 'hwid_mismatch']);
    exit;
}

$pdo = Database::getConnection();
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

if ($driver === 'pgsql') {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mod_skins (
            id SERIAL PRIMARY KEY,
            \"Name\" TEXT NOT NULL,
            \"Pic\" TEXT NOT NULL DEFAULT '',
            \"Download\" TEXT NOT NULL DEFAULT '',
            \"Package\" TEXT NOT NULL DEFAULT 'ffmax',
            \"Category\" TEXT NOT NULL DEFAULT 'trang-phuc',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
} else {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mod_skins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            Name TEXT NOT NULL,
            Pic TEXT NOT NULL DEFAULT '',
            Download TEXT NOT NULL DEFAULT '',
            Package TEXT NOT NULL DEFAULT 'ffmax',
            Category TEXT NOT NULL DEFAULT 'trang-phuc',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

if ($driver === 'pgsql') {
    $stmt = $pdo->query('SELECT "Name", "Pic", "Download", "Package", "Category" FROM mod_skins ORDER BY id DESC');
} else {
    $stmt = $pdo->query('SELECT Name, Pic, Download, Package, Category FROM mod_skins ORDER BY id DESC');
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$mods = [];
foreach ($rows as $r) {
    $pkg = strtolower(trim($r['Package'] ?? $r['package'] ?? 'ffmax'));
    if (!in_array($pkg, ['ffmax', 'ffth'], true)) $pkg = 'ffmax';
    $cat = strtolower(trim($r['Category'] ?? $r['category'] ?? 'trang-phuc'));
    if (!in_array($cat, ['trang-phuc', 'skin-sung', 'nhan-vat', 'fix-lag'], true)) $cat = 'trang-phuc';
    $mods[] = [
        'Name'     => (string)($r['Name'] ?? $r['name'] ?? ''),
        'Pic'      => (string)($r['Pic'] ?? $r['pic'] ?? ''),
        'Download' => (string)($r['Download'] ?? $r['download'] ?? ''),
        'Package'  => $pkg,
        'Category' => $cat,
    ];
}

$jsonPlaintext = json_encode($mods, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$aesKey = hash('sha256', $APP_SECRET, true);
$iv = random_bytes(16);
$cipherRaw = openssl_encrypt($jsonPlaintext, 'AES-256-CBC', $aesKey, OPENSSL_RAW_DATA, $iv);
if ($cipherRaw === false) {
    http_response_code(500);
    echo json_encode(['error' => 'encrypt_failed']);
    exit;
}

$d = base64_encode($cipherRaw);
$v = bin2hex($iv);
$t = time();
$s = hash_hmac('sha256', $d . '|' . $v . '|' . $t, $APP_SECRET);

echo json_encode([
    'v' => $v,
    'd' => $d,
    't' => $t,
    's' => $s,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);