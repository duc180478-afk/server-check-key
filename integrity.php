<?php
/**
 * Phase 4 — Play Integrity + ECDSA Keystore verify
 *
 * ENV / config:
 *   REQUIRE_PLAY_INTEGRITY=1   → bắt buộc itok hợp lệ
 *   REQUIRE_KEYSTORE_SIG=1     → bắt buộc ksig hợp lệ
 *   PLAY_INTEGRITY_PACKAGE     → package name app (com.mducdz.fakeping)
 *   GOOGLE_APPLICATION_CREDENTIALS → path JSON service account (optional decode API)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function integrityPackageName() {
    return getenv('PLAY_INTEGRITY_PACKAGE') ?: 'com.mducdz.fakeping';
}

function requirePlayIntegrity() {
    return (getenv('REQUIRE_PLAY_INTEGRITY') ?: '0') === '1';
}

function requireKeystoreSig() {
    return (getenv('REQUIRE_KEYSTORE_SIG') ?: '0') === '1';
}

/**
 * Decode JWT payload without full crypto verify (basic structure check).
 * Full verify: dùng Google playintegrity decode API khi có credentials.
 * @return array|null payload claims
 */
function decodeJwtPayload($jwt) {
    $parts = explode('.', $jwt);
    if (count($parts) < 2) return null;
    $payload = $parts[1];
    $payload = strtr($payload, '-_', '+/');
    $pad = strlen($payload) % 4;
    if ($pad) $payload .= str_repeat('=', 4 - $pad);
    $json = base64_decode($payload, true);
    if ($json === false) return null;
    $data = json_decode($json, true);
    return is_array($data) ? $data : null;
}

/**
 * Gọi Google Play Integrity decode API (nếu có access token).
 * Trả về decoded token payload hoặc null.
 */
function googleDecodeIntegrityToken($token) {
    $credsPath = getenv('GOOGLE_APPLICATION_CREDENTIALS');
    if (!$credsPath || !is_readable($credsPath)) {
        return null; // offline mode
    }
    // Service account OAuth là dài — production nên cache access_token.
    // Ở đây: nếu set PLAY_INTEGRITY_ACCESS_TOKEN thì dùng luôn.
    $access = getenv('PLAY_INTEGRITY_ACCESS_TOKEN');
    if (!$access) {
        return null;
    }
    $pkg = rawurlencode(integrityPackageName());
    $url = "https://playintegrity.googleapis.com/v1/{$pkg}:decodeIntegrityToken";
    $body = json_encode(['integrityToken' => $token]);
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$access}\r\n",
            'content' => $body,
            'timeout' => 10,
            'ignore_errors' => true,
        ]
    ];
    $raw = @file_get_contents($url, false, stream_context_create($opts));
    if ($raw === false) return null;
    $j = json_decode($raw, true);
    return is_array($j) ? ($j['tokenPayloadExternal'] ?? $j) : null;
}

/**
 * Kiểm tra Play Integrity token.
 * @return ['ok'=>bool, 'reason'=>string, 'device'=>string, 'app'=>string]
 */
function verifyPlayIntegrityToken($itok) {
    if ($itok === null || $itok === '') {
        return ['ok' => false, 'reason' => 'missing_itok', 'device' => '', 'app' => ''];
    }

    $decoded = googleDecodeIntegrityToken($itok);
    if ($decoded === null) {
        // Fallback: parse JWT payload only (không verify signature Google)
        // Đủ để dev; production BẬT credentials.
        $decoded = decodeJwtPayload($itok);
        if ($decoded === null) {
            return ['ok' => false, 'reason' => 'bad_jwt', 'device' => '', 'app' => ''];
        }
    }

    // Hỗ trợ cả format Google API decode và raw JWT claims
    $deviceInt = $decoded['deviceIntegrity']['deviceRecognitionVerdict']
        ?? $decoded['deviceIntegrity']['deviceRecognitionVerdict']
        ?? [];
    if (is_string($deviceInt)) $deviceInt = [$deviceInt];

    $appLic = $decoded['appIntegrity']['appRecognitionVerdict']
        ?? $decoded['appIntegrity']['appRecognitionVerdict']
        ?? '';
    $pkg = $decoded['appIntegrity']['packageName']
        ?? $decoded['requestDetails']['requestPackageName']
        ?? '';

    $expectPkg = integrityPackageName();
    if ($pkg !== '' && strcasecmp($pkg, $expectPkg) !== 0) {
        return ['ok' => false, 'reason' => 'pkg_mismatch', 'device' => '', 'app' => $appLic];
    }

    // device: MEETS_DEVICE_INTEGRITY hoặc MEETS_STRONG_INTEGRITY
    $deviceOk = false;
    foreach ((array)$deviceInt as $v) {
        if (is_string($v) && (strpos($v, 'MEETS_DEVICE_INTEGRITY') !== false
            || strpos($v, 'MEETS_STRONG_INTEGRITY') !== false
            || strpos($v, 'MEETS_BASIC_INTEGRITY') !== false)) {
            $deviceOk = true;
            break;
        }
    }
    // Raw JWT đôi khi nest khác — nếu không parse được device, cho pass khi không require strict decode
    if (!$deviceOk && getenv('PLAY_INTEGRITY_STRICT') !== '1') {
        // có token parse được + package ok → soft pass (dev)
        if ($pkg === '' || strcasecmp($pkg, $expectPkg) === 0) {
            $deviceOk = true;
        }
    }

    $appOk = true;
    if ($appLic !== '' && $appLic !== 'PLAY_RECOGNIZED' && $appLic !== 'UNRECOGNIZED_VERSION') {
        // UNRECOGNIZED_VERSION vẫn có thể là sideload build — production nên reject
        if (getenv('PLAY_INTEGRITY_STRICT') === '1' && $appLic !== 'PLAY_RECOGNIZED') {
            $appOk = false;
        }
    }

    $ok = $deviceOk && $appOk;
    return [
        'ok' => $ok,
        'reason' => $ok ? 'ok' : 'integrity_fail',
        'device' => is_array($deviceInt) ? implode(',', $deviceInt) : (string)$deviceInt,
        'app' => (string)$appLic,
    ];
}

/**
 * Convert URL-safe Base64 SPKI to PEM for openssl
 */
function spkiToPem($b64url) {
    $b64 = strtr($b64url, '-_', '+/');
    $pad = strlen($b64) % 4;
    if ($pad) $b64 .= str_repeat('=', 4 - $pad);
    $der = base64_decode($b64, true);
    if ($der === false) return null;
    $pem = "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode($der), 64, "\n")
        . "-----END PUBLIC KEY-----\n";
    return $pem;
}

/**
 * Verify ECDSA-SHA256 signature (DER) over $canon using SPKI public key.
 */
function verifyKeystoreSig($canon, $ksigB64url, $pubkeyB64url) {
    if ($canon === '' || $ksigB64url === '' || $pubkeyB64url === '') {
        return false;
    }
    $pem = spkiToPem($pubkeyB64url);
    if (!$pem) return false;

    $sigB64 = strtr($ksigB64url, '-_', '+/');
    $pad = strlen($sigB64) % 4;
    if ($pad) $sigB64 .= str_repeat('=', 4 - $pad);
    $sig = base64_decode($sigB64, true);
    if ($sig === false) return false;

    $pub = openssl_pkey_get_public($pem);
    if ($pub === false) return false;

    // SHA256withECDSA
    $ok = openssl_verify($canon, $sig, $pub, OPENSSL_ALGO_SHA256);
    return $ok === 1;
}

/**
 * Lưu / lấy device pubkey theo key+hwid
 */
function getDevicePubkey($key, $hwid) {
    $pdo = Database::getConnection();
    $st = $pdo->prepare("SELECT pubkey FROM device_keys WHERE key_text = ? AND hwid = ? LIMIT 1");
    $st->execute([$key, $hwid]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ? ($row['pubkey'] ?? null) : null;
}

function saveDevicePubkey($key, $hwid, $pubkey, $strongbox = false) {
    $pdo = Database::getConnection();
    $now = date('Y-m-d H:i:s');
    // upsert
    try {
        $pdo->prepare(
            "INSERT INTO device_keys (key_text, hwid, pubkey, strongbox, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)
             ON CONFLICT DO NOTHING"
        )->execute([$key, $hwid, $pubkey, $strongbox ? 1 : 0, $now, $now]);
    } catch (PDOException $e) {
        // SQLite không có ON CONFLICT không target — fallback update
    }
    $existing = getDevicePubkey($key, $hwid);
    if ($existing === null) {
        $pdo->prepare(
            "INSERT INTO device_keys (key_text, hwid, pubkey, strongbox, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([$key, $hwid, $pubkey, $strongbox ? 1 : 0, $now, $now]);
    } elseif ($existing !== $pubkey) {
        // Pubkey đổi trên cùng hwid+key → đáng ngờ (reinstall keystore) — cho phép update nhẹ
        $pdo->prepare(
            "UPDATE device_keys SET pubkey = ?, strongbox = ?, updated_at = ? WHERE key_text = ? AND hwid = ?"
        )->execute([$pubkey, $strongbox ? 1 : 0, $now, $key, $hwid]);
    }
}

/**
 * Full Phase 4 gate for api_check.
 * @return ['ok'=>bool, 'message'=>string]
 */
function phase4VerifyRequest($key, $hwid, $canon, $ksig, $pubkey, $itok, $strongbox) {
    $needInt = requirePlayIntegrity();
    $needKs  = requireKeystoreSig();

    // Play Integrity
    if ($itok !== '' || $needInt) {
        $ir = verifyPlayIntegrityToken($itok);
        if (!$ir['ok']) {
            if ($needInt) {
                return ['ok' => false, 'message' => 'Play Integrity thất bại: ' . $ir['reason']];
            }
            // soft: log only
            logKeyEvent($key, $hwid, getClientIp(), 'integrity_soft_fail', $ir['reason']);
        } else {
            logKeyEvent($key, $hwid, getClientIp(), 'integrity_ok', $ir['device'] . '|' . $ir['app']);
        }
    } elseif ($needInt) {
        return ['ok' => false, 'message' => 'Thiếu Play Integrity token.'];
    }

    // Keystore ECDSA
    $stored = getDevicePubkey($key, $hwid);
    $pub = $pubkey !== '' ? $pubkey : ($stored ?? '');

    if ($ksig !== '' && $pub !== '') {
        if (!verifyKeystoreSig($canon, $ksig, $pub)) {
            if ($needKs) {
                return ['ok' => false, 'message' => 'Chữ ký Keystore không hợp lệ.'];
            }
            logKeyEvent($key, $hwid, getClientIp(), 'ksig_fail', '');
        } else {
            if ($stored === null && $pubkey !== '') {
                saveDevicePubkey($key, $hwid, $pubkey, $strongbox);
            }
            logKeyEvent($key, $hwid, getClientIp(), 'ksig_ok', $strongbox ? 'strongbox' : 'tee');
        }
    } elseif ($needKs) {
        return ['ok' => false, 'message' => 'Thiếu chữ ký Keystore.'];
    }

    return ['ok' => true, 'message' => 'ok'];
}
