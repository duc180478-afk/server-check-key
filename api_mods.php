<?php
/**
 * api_mods.php (Chimera v16 Hardened)
 * - Khóa hoàn toàn ?raw_chmr=1 khi bật CHIMERA_STRICT_SHIELD.
 * - Bắt buộc chữ ký HMAC-SHA256 C++ + Timestamp <= 120s + Nonce dùng 1 lần (chống Replay).
 * - Chặn key=DEBUG_BYPASS trên production, chặn IP/HWID bị ban và trả về "Crack cái con cặc".
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/chimera_compiler.php';
require_once __DIR__ . '/shield_guard.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Chimera-Shield');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$APP_SECRET = 'FAKEPING_BY_MDUCDZ';
$ip = getClientIp();

function loadLatestChimeraEnvelopeBin(): string {
    $writableChmrFile = __DIR__ . '/data/data.chmr';
    $seedChmrFile     = __DIR__ . '/data.chmr';

    try {
        $pdo = Database::getConnection();
        $pdo->exec("CREATE TABLE IF NOT EXISTS chimera_mod_vault (id INTEGER PRIMARY KEY CHECK (id = 1), chmr_b64 TEXT NOT NULL, updated_at INTEGER NOT NULL)");
        $rowB64 = $pdo->query("SELECT chmr_b64 FROM chimera_mod_vault WHERE id = 1")->fetchColumn();
        if (is_string($rowB64) && strlen($rowB64) > 32) {
            $decBin = base64_decode($rowB64, true);
            if ($decBin !== false && strlen($decBin) > 24) {
                return $decBin;
            }
        }
    } catch (Throwable $e) {}

    if (file_exists($writableChmrFile)) {
        $bin = @file_get_contents($writableChmrFile);
        if (is_string($bin) && strlen($bin) > 24) {
            return $bin;
        }
    }

    if (file_exists($seedChmrFile)) {
        $bin = @file_get_contents($seedChmrFile);
        if (is_string($bin) && strlen($bin) > 24) {
            if (!is_dir(__DIR__ . '/data')) {
                @mkdir(__DIR__ . '/data', 0755, true);
            }
            @file_put_contents($writableChmrFile, $bin, LOCK_EX);
            try {
                $pdo = Database::getConnection();
                $pdo->exec("DELETE FROM chimera_mod_vault WHERE id = 1");
                $stmt = $pdo->prepare("INSERT INTO chimera_mod_vault (id, chmr_b64, updated_at) VALUES (1, ?, ?)");
                $stmt->execute([base64_encode($bin), time()]);
            } catch (Throwable $e) {}
            return $bin;
        }
    }

    return ChimeraServerCompiler::compileStringToEnvelope("[]");
}

// Khóa cứng ?raw_chmr=1 để kẻ crack không thể curl trực tiếp file data.chmr
if (isset($_GET['raw_chmr']) && $_GET['raw_chmr'] === '1') {
    if (ChimeraServerShield::getFlag('strict_shield', defined('CHIMERA_STRICT_SHIELD') ? CHIMERA_STRICT_SHIELD : true)) {
        ChimeraServerShield::rejectCracker('raw_chmr_blocked', $ip, '', '', true);
    }
    $bin = loadLatestChimeraEnvelopeBin();
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . strlen($bin));
    echo $bin;
    exit;
}

if (!checkRateLimit('api_mods_' . $ip, 60, 60)) {
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

$key    = trim((string)($input['key']   ?? ''));
$hwid   = trim((string)($input['hwid']  ?? ''));
$app    = strtolower(trim((string)($input['app'] ?? 'modskin')));
$ts     = (int)($input['ts']   ?? 0);
$nonce  = trim((string)($input['nonce'] ?? ''));
$sig    = strtolower(trim((string)($input['sig'] ?? '')));
$shield = (int)($input['shield'] ?? ($_SERVER['HTTP_X_CHIMERA_SHIELD'] ?? 0));

if (ChimeraServerShield::isBanned($ip, $hwid)) {
    ChimeraServerShield::rejectCracker('banned_cracker', $ip, $hwid, $key, false);
}

$strict = ChimeraServerShield::getFlag('strict_shield', defined('CHIMERA_STRICT_SHIELD') ? CHIMERA_STRICT_SHIELD : true);
$allowDebug = ChimeraServerShield::getFlag('allow_debug_bypass', defined('CHIMERA_ALLOW_DEBUG_BYPASS') ? CHIMERA_ALLOW_DEBUG_BYPASS : false);

$apkSig = strtolower(trim((string)($input['apk_sig'] ?? '')));
$ALLOWED_APK_SIGS = [
    'db0f267fa6b0eebd5f0c8b91670d7b9dba527e0f33bd64edc031b7bf05c97e71',
    'a40da80a59d170caa950cf15c18c454d47a39b26989d8b640ecd745ba71bf5dc',
    '81209eeaa0b20b9cb614231ab50cd095e3c1ba5e85c04fd160aff6c9008c24f8',
];
if ($strict && $apkSig !== '' && !in_array($apkSig, $ALLOWED_APK_SIGS, true)) {
    ChimeraServerShield::rejectCracker('invalid_apk_cert', $ip, $hwid, $key, true);
}

if ($shield !== 0) {
    ChimeraServerShield::rejectCracker('client_shield_tripped', $ip, $hwid, $key, true);
}

if ($app !== 'modskin') {
    ChimeraServerShield::rejectCracker('invalid_app', $ip, $hwid, $key, false);
}

// Silent Frida Trap: schedule delayed ban (180s) & serve decoy troll catalog with HTTP 200
$trapFlag = (int)($input['trap'] ?? 0);
if ($trapFlag !== 0) {
    ChimeraServerShield::scheduleDelayedBan($ip, $hwid, $key, 'frida_silent_trap', 180);
    $decoyJson = json_encode([
        ['id' => 'd1', 'title' => 'ố ồ xem kid đang lmj kia', 'description' => 'Cố lên sắp dump được rồi đấy kid', 'game_type' => 'com.dts.freefiremax', 'skin_type' => 'character', 'file_url' => 'https://example.invalid/xem-ai-dang-sau-kia', 'image_url' => ''],
        ['id' => 'd2', 'title' => 'xem ai đằng sau kìa', 'description' => 'Đang soi cái gì thế bạn nhỏ', 'game_type' => 'com.dts.freefireth', 'skin_type' => 'character', 'file_url' => 'https://example.invalid/co-len-kid', 'image_url' => ''],
        ['id' => 'd3', 'title' => 'tưởng dễ ăn à haha', 'description' => 'Xem ai đứng đằng sau kìa', 'game_type' => 'com.garena.ffvn', 'skin_type' => 'character', 'file_url' => 'https://example.invalid/tuong-de-an-a', 'image_url' => ''],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $decoyBin = ChimeraServerCompiler::compileStringToEnvelope($decoyJson);
    $decoyB64 = base64_encode($decoyBin);
    $tNow = time();
    $sNow = hash_hmac('sha256', $decoyB64 . '|chimera_v1|' . $tNow, $APP_SECRET);
    echo json_encode(['mode' => 'chimera_v1', 'chmr' => $decoyB64, 't' => $tNow, 's' => $sNow], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Bắt buộc chữ ký HMAC + Nonce 1 lần khi bật Strict Shield
if ($strict && ($ts === 0 || $nonce === '' || $sig === '')) {
    ChimeraServerShield::rejectCracker('missing_signature', $ip, $hwid, $key, false);
}

if ($ts !== 0 && $nonce !== '' && $sig !== '') {
    if (!ChimeraServerShield::verifyAndConsumeNonce($nonce, $ts)) {
        ChimeraServerShield::rejectCracker('replay_or_expired_nonce', $ip, $hwid, $key, false);
    }
    $canon = "mods|{$key}|{$hwid}|{$ts}|{$nonce}";
    $expected = hash_hmac('sha256', $canon, $APP_SECRET);
    if (!hash_equals($expected, $sig)) {
        ChimeraServerShield::rejectCracker('invalid_hmac_signature', $ip, $hwid, $key, false);
    }
    // Xác thực khóa phiên động (sid + sproof) từ api_check.php nếu client gửi kèm
    $sidIn    = trim((string)($input['sid'] ?? ''));
    $sproofIn = strtolower(trim((string)($input['sproof'] ?? '')));
    if ($sidIn !== '' && $sproofIn !== '') {
        $expectedGk = substr(hash_hmac('sha256', 'gk|' . $sidIn, $APP_SECRET), 0, 32);
        $expectedProof = hash_hmac('sha256', $sidIn . '|' . $nonce . '|' . $ts, $expectedGk);
        if (!hash_equals($expectedProof, $sproofIn)) {
            ChimeraServerShield::rejectCracker('invalid_session_proof', $ip, $hwid, $key, true);
        }
    }
    if ($key === 'DEBUG_BYPASS' && !$allowDebug) {
        ChimeraServerShield::rejectCracker('debug_bypass_rejected', $ip, $hwid, $key, true);
    }
    if ($key !== '' && $key !== 'DEBUG_BYPASS') {
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
    }
}

$chimeraEnvelopeBin = loadLatestChimeraEnvelopeBin();
$chmrB64 = base64_encode($chimeraEnvelopeBin);
$t = time();
$s = hash_hmac('sha256', $chmrB64 . '|chimera_v1|' . $t, $APP_SECRET);

echo json_encode([
    'mode' => 'chimera_v1',
    'chmr' => $chmrB64,
    't'    => $t,
    's'    => $s,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
