<?php
/**
 * api_mods.php — Phục vụ danh sách Mod đã mã hoá Chimera-VM (`data.chmr`)
 * - Ưu tiên đọc bản mới nhất từ bảng `chimera_mod_vault` trong Database (`db.php`)
 *   hoặc từ `/var/www/html/data/data.chmr` (thư mục `data/` có quyền ghi `www-data`).
 * - Hỗ trợ cả bản Release (có Key) và bản Debug (bỏ qua Key nhưng vẫn xác thực chữ ký HMAC-SHA256 C++).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/chimera_compiler.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$APP_SECRET = 'FAKEPING_BY_MDUCDZ';

function loadLatestChimeraEnvelopeBin(): string {
    $writableChmrFile = __DIR__ . '/data/data.chmr';
    $seedChmrFile     = __DIR__ . '/data.chmr';

    // 1. Ưu tiên đọc từ Database (PostgreSQL / SQLite trong /var/www/html/data/keys.db)
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

    // 2. Đọc từ thư mục /var/www/html/data/data.chmr (có quyền ghi www-data)
    if (file_exists($writableChmrFile)) {
        $bin = @file_get_contents($writableChmrFile);
        if (is_string($bin) && strlen($bin) > 24) {
            return $bin;
        }
    }

    // 3. Khởi tạo lần đầu từ file gốc /var/www/html/data.chmr
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

// Nếu App gọi fallback tải trực tiếp gói nhị phân (?raw_chmr=1)
if (isset($_GET['raw_chmr']) && $_GET['raw_chmr'] === '1') {
    $bin = loadLatestChimeraEnvelopeBin();
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . strlen($bin));
    echo $bin;
    exit;
}

$ip = getClientIp();
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

// Kiểm tra chữ ký HMAC-SHA256 nếu client gửi đầy đủ ts, nonce, sig
if ($ts !== 0 && $nonce !== '' && $sig !== '') {
    if (abs(time() - $ts) > 300) {
        http_response_code(403);
        echo json_encode(['error' => 'timestamp_expired']);
        exit;
    }
    $canon = "mods|{$key}|{$hwid}|{$ts}|{$nonce}";
    $expected = hash_hmac('sha256', $canon, $APP_SECRET);
    if (!hash_equals($expected, $sig)) {
        http_response_code(403);
        echo json_encode(['error' => 'invalid_signature']);
        exit;
    }
    // Nếu có truyền Key (bản Release), kiểm tra Key hợp lệ trong DB
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
