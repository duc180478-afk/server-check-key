<?php
// api_check.php — Phase 0+1: HMAC BẮT BUỘC, không còn đường vòng không-sig

require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

// ★ APP_SECRET — PHẢI GIỐNG Secrets.kt → APP_SECRET_OBF (salt 0x77)
// CẢNH BÁO: secret tĩnh + XOR đơn byte chỉ chặn script-kiddie.
// Người dump APK 1 lần lấy được secret → forge HMAC. Phase 4 (Keystore) mới thật sự bịt.
$APP_SECRET = 'FAKEPING_BY_MDUCDZ';

$ip = getClientIp();
if (!checkRateLimit('api_' . $ip, 30, 60)) {
    http_response_code(429);
    echo json_encode(['status' => 'error', 'message' => 'Quá nhiều yêu cầu. Vui lòng thử lại sau.']);
    exit;
}

// FIX: client mới gửi qua POST body (application/x-www-form-urlencoded).
// Đọc $_GET trước đây khiến mọi field luôn rỗng với client đã ký — đọc $_POST trước, $_GET làm fallback.
$key        = trim($_POST['key']    ?? $_GET['key']    ?? '');
$hwid       = trim($_POST['hwid']   ?? $_GET['hwid']   ?? '');
$app_client = strtolower(trim($_POST['app'] ?? $_GET['app'] ?? ''));
$ts         = trim($_POST['ts']     ?? $_GET['ts']     ?? '');
$nonce      = trim($_POST['nonce']  ?? $_GET['nonce']  ?? '');
$reqSig     = trim($_POST['sig']    ?? $_GET['sig']    ?? '');
$oldSession = trim($_POST['sid']    ?? $_GET['sid']    ?? '');
$apkSig     = strtolower(trim($_POST['apk_sig'] ?? $_GET['apk_sig'] ?? ''));

// ★ Cert APK allowlist (SHA-256 hex, không dấu ':')
// AndroidIDE: keytool -list -v -keystore release-key.jks -alias fakeping_release
// Rỗng = chưa bật (dev). Thêm hash release (và debug nếu cần) rồi deploy.
$ALLOWED_APK_SIGS = [
    '81209eeaa0b20b9cb614231ab50cd095e3c1ba5e85c04fd160aff6c9008c24f8',
'81209eeaa0b20b9cb614231ab50cd095e3c1ba5e85c04fd160aff6c9008c24f8',
'a40da80a59d170caa950cf15c18c454d47a39b26989d8b640ecd745ba71bf5dc', // debug app-debug.apk
    // 'release_sha256_here',
];

// ─── Phase 1: HMAC BẮT BUỘC ───────────────────────────────────────
// Không còn fallback "không có sig thì vẫn cho qua".
// Client cũ chưa ký sẽ fail → phải đẩy bản client mới trước hoặc đồng thời.
if ($reqSig === '' || $ts === '' || $nonce === '') {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Request thiếu chữ ký.',
        'p' => null,
        's' => null
    ]);
    exit;
}

$tsInt = (int)$ts;
if (abs(time() - $tsInt) > 120) {
    echo json_encode(['status' => 'error', 'message' => 'Request hết hạn.', 'p' => null, 's' => null]);
    exit;
}
if (!consumeNonce($nonce)) {
    echo json_encode(['status' => 'error', 'message' => 'Replay bị chặn.', 'p' => null, 's' => null]);
    exit;
}
$canon  = $key . '|' . $hwid . '|' . $app_client . '|' . $ts . '|' . $nonce;
$expect = hash_hmac('sha256', $canon, $APP_SECRET);
if (!hash_equals($expect, strtolower($reqSig))) {
    echo json_encode(['status' => 'error', 'message' => 'Chữ ký không hợp lệ.', 'p' => null, 's' => null]);
    exit;
}

// ─── APK signature (chống re-sign trên máy sạch) ─────────────────
if (count($ALLOWED_APK_SIGS) > 0) {
    if ($apkSig === '' || !in_array($apkSig, array_map('strtolower', $ALLOWED_APK_SIGS), true)) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'APK không hợp lệ.',
            'p' => null,
            's' => null
        ]);
        exit;
    }
}

if (empty($key)) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Thiếu Key!',
        'p' => null,
        's' => null
    ]);
    exit;
}

$result = validateKey($key, $hwid, ['old_session' => $oldSession]);

$kd = getKeyByKey($key);
@date_default_timezone_set('Asia/Ho_Chi_Minh');
if (is_array($result) && $kd) {
    $exp = $kd['expires_at'] ?? null;
    $result['expires_at'] = $exp;
    $result['expires_at_vn'] = $exp ? date('d/m/Y H:i', strtotime($exp)) : 'Vĩnh viễn';
}
$result['app'] = $kd ? getAppLabel($kd['app'] ?? '') : 'Unknown';

// APP FILTER
$app_from_db = strtolower(trim($kd['app'] ?? ''));
$is_key_valid = false;
if (is_array($result) && isset($result['status']) && strtolower($result['status']) === 'success') {
    $is_key_valid = true;
}
if (isset($result['valid']) && $result['valid'] === true) {
    $is_key_valid = true;
}
if ($is_key_valid && $kd && $app_from_db && $app_client) {
    if ($app_from_db !== $app_client) {
        $result = [
            'status'  => 'error',
            'message' => 'Key này chỉ dùng cho app ' . getAppLabel($app_from_db),
            'valid'   => false
        ];
    }
}

// HMAC response payload
$ok = 0;
if (is_array($result)) {
    if (isset($result['status']) && strtolower($result['status']) === 'success') $ok = 1;
    if (isset($result['valid'])  && $result['valid'] === true) $ok = 1;
    if (isset($result['ok'])     && (int)$result['ok'] === 1) $ok = 1;
} elseif (is_bool($result)) {
    $ok = $result ? 1 : 0;
}

$sid = $ok ? ($result['session'] ?? '') : '';
$gk  = '';
if ($ok && $sid !== '') {
    $gk = substr(hash_hmac('sha256', 'gk|' . $sid, $APP_SECRET), 0, 32);
}
$payloadArr = [
    'ok'  => $ok,
    'exp' => time() + 300,
    't'   => substr($key, 0, 4),
    'sid' => $sid,
    'gk'  => $gk,
];
$payload = json_encode($payloadArr, JSON_UNESCAPED_SLASHES);
$sig     = hash_hmac('sha256', $payload, $APP_SECRET);

$result['p'] = $payload;
$result['s'] = $sig;

echo json_encode($result);
