<?php
/**
 * api_mods.php — Phục vụ trực tiếp từ file nhị phân đã mã hoá `data.chmr` trên Server
 * Trên Server tuyệt đối KHÔNG lưu trữ data.json dạng rõ (plaintext)!
 *
 * Khi App Android gửi request đã ký HMAC-SHA256 ("mods|key|hwid|ts|nonce"):
 *   1. Kiểm tra Key hợp lệ, còn hạn và khớp HWID.
 *   2. Nếu chỉ dùng `data.chmr` (quản trị qua `admin/mods.php`), Server đọc thẳng khung nhị phân
 *      `[8B Dynamic Opcode Map][16B Server Key Share][Payload .chmr]` từ `data.chmr`,
 *      ký `HMAC-SHA256` và trả về ngay trong < 2ms!
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

$rawBody = file_get_contents('php://input');
$jsonIn = json_decode($rawBody, true);
$formIn = [];
if (!is_array($jsonIn) && is_string($rawBody) && $rawBody !== '') {
    parse_str($rawBody, $formIn);
}
if (!is_array($jsonIn)) $jsonIn = [];
if (!is_array($formIn)) $formIn = [];
$input = array_merge($_GET, $_POST, $formIn, $jsonIn);

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
$ff_ok = in_array($app_from_db, ['', 'other', 'fakelag', 'locmod', 'modskin'], true);
if (!$ff_ok) {
    http_response_code(403);
    echo json_encode(['error' => 'key_app_mismatch']);
    exit;
}
if (!empty($kd['bound_hwid']) && $kd['bound_hwid'] !== $hwid) {
    http_response_code(403);
    echo json_encode(['error' => 'hwid_mismatch']);
    exit;
}

// Ưu tiên đọc gói nhị phân đã mã hoá Chimera-VM `data.chmr` từ Database (chống mất dữ liệu khi Render ngủ/sập container),
// nếu Database chưa có thì đọc từ file `/data.chmr` trên đĩa và đồng bộ ngược vào Database.
$dataChmrFile = __DIR__ . '/data.chmr';
$chimeraEnvelopeBin = null;
try {
    $pdo = Database::getConnection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS chimera_mod_vault (id INTEGER PRIMARY KEY CHECK (id = 1), chmr_b64 TEXT NOT NULL, updated_at INTEGER NOT NULL)");
    $rowB64 = $pdo->query("SELECT chmr_b64 FROM chimera_mod_vault WHERE id = 1")->fetchColumn();
    if (is_string($rowB64) && strlen($rowB64) > 32) {
        $decBin = base64_decode($rowB64, true);
        if ($decBin !== false && strlen($decBin) > 24) {
            $chimeraEnvelopeBin = $decBin;
        }
    }
} catch (Throwable $e) {
    // Fallback sang file data.chmr nếu DB lỗi
}

if ($chimeraEnvelopeBin === null) {
    if (file_exists($dataChmrFile)) {
        $chimeraEnvelopeBin = file_get_contents($dataChmrFile);
    } else {
        $chimeraEnvelopeBin = ChimeraServerCompiler::compileStringToEnvelope("[]");
        @file_put_contents($dataChmrFile, $chimeraEnvelopeBin, LOCK_EX);
    }
    try {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO chimera_mod_vault (id, chmr_b64, updated_at) VALUES (1, ?, ?) ON CONFLICT(id) DO UPDATE SET chmr_b64 = excluded.chmr_b64, updated_at = excluded.updated_at");
        $stmt->execute([base64_encode($chimeraEnvelopeBin), time()]);
    } catch (Throwable $e) {}
}

$chmrB64 = base64_encode($chimeraEnvelopeBin);
$t = time();
$s = hash_hmac('sha256', $chmrB64 . '|chimera_v1|' . $t, $APP_SECRET);

echo json_encode([
    'mode' => 'chimera_v1',
    'chmr' => $chmrB64,
    't'    => $t,
    's'    => $s,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);