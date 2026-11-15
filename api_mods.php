```php
<?php
/**
 * api_mods.php — Phiên bản nâng cấp Chimera-VM Server-Side Obfuscation
 * Lấy danh sách Mod Skin từ bảng quản trị (admin/mods.php) + data.json trên Server,
 * sau đó biên dịch động sang định dạng nhị phân đa hình Chimera-VM (.chmr):
 *   [8-byte Dynamic Opcode Map] + [16-byte Server Key Share] + [Payload .chmr]
 * và ký xác thực HMAC-SHA256 trước khi trả về cho App Android giải mã trong RAM C++.
 *
 * Client gửi (POST hoặc GET):
 *   key, hwid, app=modskin, ts, nonce, sig
 * Canonical chữ ký request:
 *   mods|{key}|{hwid}|{ts}|{nonce}
 *
 * Response:
 *   {
 *     "mode": "chimera_v1",
 *     "chmr": "<base64([8B Opcode Map][16B Server Key Share][Payload .chmr])>",
 *     "t": <unix_timestamp>,
 *     "s": "<HMAC-SHA256(chmr + '|chimera_v1|' + t, APP_SECRET)>"
 *   }
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/chimera_compiler.php';

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
            \"Description\" TEXT NOT NULL DEFAULT '',
            \"Pic\" TEXT NOT NULL DEFAULT '',
            \"Download\" TEXT NOT NULL DEFAULT '',
            \"Package\" TEXT NOT NULL DEFAULT 'com.dts.freefiremax',
            \"Category\" TEXT NOT NULL DEFAULT 'character',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
} else {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mod_skins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            Name TEXT NOT NULL,
            Description TEXT NOT NULL DEFAULT '',
            Pic TEXT NOT NULL DEFAULT '',
            Download TEXT NOT NULL DEFAULT '',
            Package TEXT NOT NULL DEFAULT 'com.dts.freefiremax',
            Category TEXT NOT NULL DEFAULT 'character',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

// 1. Lấy các bản mod thêm/sửa từ trang quản trị (admin/mods.php) trong DB
if ($driver === 'pgsql') {
    $stmt = $pdo->query('SELECT * FROM mod_skins ORDER BY id DESC');
} else {
    $stmt = $pdo->query('SELECT * FROM mod_skins ORDER BY id DESC');
}
$rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

$mods = [];
foreach ($rows as $r) {
    $pkgRaw = strtolower(trim($r['Package'] ?? $r['package'] ?? 'com.dts.freefiremax'));
    if ($pkgRaw === 'ffth' || $pkgRaw === 'com.dts.freefireth') {
        $gameType = 'com.dts.freefireth';
    } else {
        $gameType = 'com.dts.freefiremax';
    }

    $catRaw = strtolower(trim($r['Category'] ?? $r['category'] ?? 'character'));
    if ($catRaw === 'skin-sung' || $catRaw === 'gun') {
        $skinType = 'gun';
    } elseif ($catRaw === 'toy' || $catRaw === 'dinh-vi') {
        $skinType = 'toy';
    } elseif ($catRaw === 'fix-lag' || $catRaw === 'fixlag') {
        $skinType = 'fixlag';
    } else {
        $skinType = 'character';
    }

    $mods[] = [
        'id'          => (string)($r['id'] ?? ''),
        'title'       => (string)($r['Name'] ?? $r['name'] ?? ''),
        'description' => (string)($r['Description'] ?? $r['description'] ?? 'Mod Skin VIP'),
        'game_type'   => $gameType,
        'skin_type'   => $skinType,
        'file_url'    => (string)($r['Download'] ?? $r['download'] ?? ''),
        'image_url'   => (string)($r['Pic'] ?? $r['pic'] ?? ''),
    ];
}

// 2. Kết hợp với file data.json trên Server (chứa các bản mod gốc và các bản mod quản trị)
$dataJsonFile = __DIR__ . '/data.json';
if (file_exists($dataJsonFile)) {
    $jsonItems = json_decode(file_get_contents($dataJsonFile), true);
    if (is_array($jsonItems)) {
        // Nếu data.json có khóa "items" hoặc là mảng trực tiếp
        $list = isset($jsonItems['items']) && is_array($jsonItems['items']) ? $jsonItems['items'] : $jsonItems;
        foreach ($list as $item) {
            if (!is_array($item)) continue;
            $mods[] = [
                'id'          => (string)($item['id'] ?? ''),
                'title'       => (string)($item['title'] ?? $item['Name'] ?? ''),
                'description' => (string)($item['description'] ?? 'Mod Skin'),
                'game_type'   => (string)($item['game_type'] ?? 'com.dts.freefireth'),
                'skin_type'   => (string)($item['skin_type'] ?? 'character'),
                'file_url'    => (string)($item['file_url'] ?? $item['Download'] ?? ''),
                'image_url'   => (string)($item['image_url'] ?? $item['Pic'] ?? ''),
            ];
        }
    }
}

$jsonPlaintext = json_encode($mods, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// 3. Biên dịch sang khung nhị phân đa hình Chimera-VM (.chmr):
//    [8-byte Dynamic Opcode Map] + [16-byte Server Key Share] + [Payload .chmr]
$chimeraEnvelopeBin = ChimeraServerCompiler::compileStringToEnvelope($jsonPlaintext);
$chmrB64 = base64_encode($chimeraEnvelopeBin);
$t = time();
$s = hash_hmac('sha256', $chmrB64 . '|chimera_v1|' . $t, $APP_SECRET);

echo json_encode([
    'mode' => 'chimera_v1',
    'chmr' => $chmrB64,
    't'    => $t,
    's'    => $s,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
```