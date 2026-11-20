<?php
/**
 * api_download.php (Chimera v17) — Cấp link tải lẻ từng Mod theo yêu cầu (KHÔNG giới hạn số skin/ngày cho người dùng thật,
 * chỉ chống bot cào sỉ bằng rate-limit burst 40 req/phút + xác thực chữ ký HMAC & APK cert SHA-256).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/chimera_compiler.php';
require_once __DIR__ . '/shield_guard.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$APP_SECRET = 'FAKEPING_BY_MDUCDZ';
$ALLOWED_APK_SIGS = [
    'db0f267fa6b0eebd5f0c8b91670d7b9dba527e0f33bd64edc031b7bf05c97e71', // Release chimera_release.jks
    'a40da80a59d170caa950cf15c18c454d47a39b26989d8b640ecd745ba71bf5dc', // Debug
    '81209eeaa0b20b9cb614231ab50cd095e3c1ba5e85c04fd160aff6c9008c24f8',
];

$ip = getClientIp();
if (!checkRateLimit('api_dl_' . $ip, 40, 60)) {
    http_response_code(429);
    echo json_encode(['error' => 'rate_limited']);
    exit;
}

$input = array_merge($_GET, $_POST, json_decode(file_get_contents('php://input') ?: '{}', true) ?: []);
$modId  = trim((string)($input['mod_id'] ?? ''));
$key    = trim((string)($input['key'] ?? ''));
$hwid   = trim((string)($input['hwid'] ?? ''));
$ts     = (int)($input['ts'] ?? 0);
$nonce  = trim((string)($input['nonce'] ?? ''));
$sig    = strtolower(trim((string)($input['sig'] ?? '')));
$apkSig = strtolower(trim((string)($input['apk_sig'] ?? '')));
$shield = (int)($input['shield'] ?? 0);

if (ChimeraServerShield::isBanned($ip, $hwid) || $shield !== 0) {
    ChimeraServerShield::rejectCracker('dl_shield_blocked', $ip, $hwid, $key, true);
}
if ($modId === '' || $ts === 0 || $nonce === '' || $sig === '') {
    ChimeraServerShield::rejectCracker('dl_missing_sig', $ip, $hwid, $key, false);
}
if (!ChimeraServerShield::verifyAndConsumeNonce($nonce, $ts)) {
    ChimeraServerShield::rejectCracker('dl_replay_nonce', $ip, $hwid, $key, false);
}
$expected = hash_hmac('sha256', "dl|{$modId}|{$key}|{$hwid}|{$ts}|{$nonce}", $APP_SECRET);
if (!hash_equals($expected, $sig)) {
    ChimeraServerShield::rejectCracker('dl_bad_hmac', $ip, $hwid, $key, false);
}
if ($apkSig !== '' && !in_array($apkSig, $ALLOWED_APK_SIGS, true)) {
    ChimeraServerShield::rejectCracker('dl_bad_apk_sig', $ip, $hwid, $key, true);
}

// Tra cứu link mod từ data.json / database
$dataFile = __DIR__ . '/../API_FF_DATA/data.json';
$foundUrl = '';
if (file_exists($dataFile)) {
    $list = json_decode(file_get_contents($dataFile), true);
    if (is_array($list)) {
        foreach ($list as $it) {
            if ((string)($it['id'] ?? '') === $modId) {
                $foundUrl = (string)($it['download_url'] ?? ($it['url'] ?? ''));
                break;
            }
        }
    }
}
if ($foundUrl === '') {
    http_response_code(404);
    echo json_encode(['error' => 'mod_not_found']);
    exit;
}
$t = time();
echo json_encode([
    'status' => 'ok',
    'mod_id' => $modId,
    'url'    => $foundUrl,
    't'      => $t,
    's'      => hash_hmac('sha256', $modId . '|' . $foundUrl . '|' . $t, $APP_SECRET)
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
