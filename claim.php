<?php
// claim.php - Cấp key sau khi vượt link. Chỉ nhận người có "vé" hợp lệ.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/keygen_functions.php';

session_start();

 $ip = getClientIp();

 $blockedMsg = '';
 $keyText = null;
 $keyInfo = null;

// ==== KIỂM TRA VÉ MỘT LẦN ====
 $ticket = $_SESSION['claim_ticket'] ?? null;
unset($_SESSION['claim_ticket']); // vé tự hủy ngay, không tái sử dụng

if ($ticket === null) {
    $blockedMsg = 'Vui lòng bấm Generate trên trang lấy key trước khi lấy key!';
} elseif (!is_array($ticket) || ($ticket['ip'] ?? '') !== $ip) {
    $blockedMsg = 'Vé không hợp lệ. Vui lòng bấm Generate lại!';
} elseif ((time() - (int)($ticket['created'] ?? 0)) < KEYGEN_MIN_CLAIM_SECONDS) {
    // Đến quá nhanh => nghi bypass tool
    $blockedMsg = 'Phát hiện truy cập bất thường! Hãy vượt link theo cách thông thường.';
} elseif ((time() - (int)($ticket['created'] ?? 0)) > KEYGEN_TICKET_TTL_SECONDS) {
    // Generate xong quá lâu mới đến => vé hết hạn
    $blockedMsg = 'Vé đã hết hạn. Vui lòng quay lại bấm Generate!';
}

if ($blockedMsg === '') {
    if (!checkRateLimit('claim_' . $ip, 10, 60)) {
        $blockedMsg = 'Quá nhiều yêu cầu. Vui lòng đợi vài phút rồi thử lại!';
    } else {
        cleanupOldKeyPages();
        if (countRecentKeysByIp($ip) >= KEYGEN_MAX_PER_HOUR) {
            $blockedMsg = 'Bạn đã hết lượt lấy key trong giờ này. Vui lòng thử lại sau!';
        }
    }
}

if ($blockedMsg === '') {
    try {
        // ✅ Tạo key NGAY LÚC NÀY (user đã vượt link xong)
        $keyText = createKey(KEYGEN_GAME_PREFIX, KEYGEN_DAYS, 1, 'Free key - claim');
        logClaim($keyText, $ip);
        $keyInfo = getKeyByKey($keyText);
    } catch (Exception $e) {
        $blockedMsg = 'Lỗi tạo key. Vui lòng thử lại!';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $keyText ? 'Key Của Bạn' : 'Có Lỗi Xảy Ra' ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Arial, sans-serif; }
body { background:#eef0f3; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
.card { background:#fff; border-radius:14px; box-shadow:0 6px 24px rgba(0,0,0,.1); max-width:480px; width:100%; overflow:hidden; }
.card-head { background:#1a8cff; color:#fff; padding:20px; text-align:center; font-size:20px; font-weight:700; }
.card-body { padding:26px 22px; }
.key-box { background:#f4f6f8; border:2px dashed #1a8cff; border-radius:10px; padding:20px 14px; font-size:22px; font-weight:700; letter-spacing:1px; color:#1b2a4a; word-break:break-all; margin-bottom:18px; user-select:all; text-align:center; }
.info { text-align:left; background:#fafbfc; border:1px solid #e4e8ec; border-radius:8px; padding:14px 16px; font-size:15px; color:#444; margin-bottom:18px; line-height:1.9; }
.copy-btn { display:block; width:100%; background:#1a8cff; color:#fff; border:none; padding:15px; border-radius:8px; font-size:17px; font-weight:700; cursor:pointer; margin-bottom:12px; }
.copy-btn:hover { background:#0f76e0; }
.back-link { display:block; color:#1a8cff; text-decoration:none; font-size:15px; font-weight:600; padding:10px; text-align:center; }
.error-icon { font-size:52px; margin-bottom:12px; text-align:center; }
.err-text { color:#c0392b; font-weight:700; font-size:17px; margin-bottom:18px; text-align:center; }
.game-name { color:#1a8cff; font-weight:700; }
</style>
</head>
<body>
<div class="card">
<?php if ($blockedMsg !== ''): ?>
    <div class="card-head">⚠️ Không Lấy Được Key</div>
    <div class="card-body">
        <div class="error-icon">😕</div>
        <p class="err-text"><?= htmlspecialchars($blockedMsg) ?></p>
        <a class="back-link" href="getkey.php">🔑 Quay lại trang lấy key</a>
    </div>
<?php else: ?>
    <div class="card-head">🎉 Key Của Bạn</div>
    <div class="card-body">
        <div class="key-box" id="keyText"><?= htmlspecialchars($keyText) ?></div>

        <?php if ($keyInfo): ?>
            <div class="info">
                <div>🎮 <b>Game:</b> <span class="game-name"><?= htmlspecialchars(KEYGEN_GAME_LABEL) ?></span></div>
                <div>📅 <b>Hết hạn:</b> <?= htmlspecialchars($keyInfo['expires_at'] ?: 'Vĩnh viễn') ?></div>
                <div>⏳ <b>Còn lại:</b> <?= htmlspecialchars(getTimeRemaining($keyInfo['expires_at'])) ?></div>
                <div>🔖 <b>Trạng thái:</b> <?= $keyInfo['status'] === 'active' ? '✅ Hoạt động' : '⛔' ?></div>
            </div>
        <?php endif; ?>

        <button class="copy-btn" id="copyBtn" onclick="copyKey()">📋 Sao Chép Key</button>
        <a class="back-link" href="getkey.php">🔑 Lấy Key Khác</a>
    </div>
<?php endif; ?>
</div>

<script>
function copyKey() {
    const key = document.getElementById('keyText').innerText.trim();
    const btn = document.getElementById('copyBtn');
    const done = () => { btn.innerHTML = '✅ Đã sao chép!'; setTimeout(() => btn.innerHTML = '📋 Sao Chép Key', 2500); };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(key).then(done).catch(() => fallbackCopy(key, done));
    } else {
        fallbackCopy(key, done);
    }
}
function fallbackCopy(key, done) {
    const ta = document.createElement('textarea');
    ta.value = key;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    done();
}
</script>
</body>
</html>