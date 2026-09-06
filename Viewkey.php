<?php
// viewkey.php - Trang hiển thị key (truy cập qua token bí mật sau khi vượt link)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/keygen_functions.php';

 $token = $_GET['token'] ?? '';
 $page = getKeyPageByToken($token);

 $keyText = $page['key_text'] ?? null;
 $keyInfo = $keyText ? getKeyByKey($keyText) : null;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Key Của Bạn</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Arial, sans-serif; }
body { background:#eef0f3; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
.card { background:#fff; border-radius:14px; box-shadow:0 6px 24px rgba(0,0,0,.1); max-width:480px; width:100%; overflow:hidden; }
.card-head { background:#1a8cff; color:#fff; padding:20px; text-align:center; font-size:20px; font-weight:700; }
.card-body { padding:26px 22px; text-align:center; }
.key-box { background:#f4f6f8; border:2px dashed #1a8cff; border-radius:10px; padding:20px 14px; font-size:22px; font-weight:700; letter-spacing:1px; color:#1b2a4a; word-break:break-all; margin-bottom:18px; user-select:all; }
.info { text-align:left; background:#fafbfc; border:1px solid #e4e8ec; border-radius:8px; padding:14px 16px; font-size:15px; color:#444; margin-bottom:18px; line-height:1.9; }
.copy-btn { display:block; width:100%; background:#1a8cff; color:#fff; border:none; padding:15px; border-radius:8px; font-size:17px; font-weight:700; cursor:pointer; margin-bottom:12px; }
.copy-btn:hover { background:#0f76e0; }
.back-link { display:block; color:#1a8cff; text-decoration:none; font-size:15px; font-weight:600; padding:10px; }
.error-icon { font-size:52px; margin-bottom:12px; }
.err-text { color:#c0392b; font-weight:700; font-size:18px; margin-bottom:8px; }
.err-sub { color:#777; font-size:14px; margin-bottom:18px; }
.warn { color:#e67e22; font-size:13px; margin-top:10px; }
</style>
</head>
<body>
<div class="card">
<?php if (!$page || !$keyText): ?>
    <div class="card-head">⚠️ Link Không Hợp Lệ</div>
    <div class="card-body">
        <div class="error-icon">🔒</div>
        <p class="err-text">Link không hợp lệ hoặc đã hết hạn!</p>
        <p class="err-sub">Trang chứa key chỉ tồn tại <?= KEYGEN_PAGE_TTL_HOURS ?> giờ. Hãy quay lại tạo key mới.</p>
        <a class="back-link" href="getkey.php">🔑 Tạo Key Mới</a>
    </div>
<?php else: ?>
    <div class="card-head">🎉 Key Của Bạn</div>
    <div class="card-body">
        <div class="key-box" id="keyText"><?= htmlspecialchars($keyText) ?></div>

        <?php if ($keyInfo): ?>
            <div class="info">
                <div>📦 <b>Game:</b> <?= htmlspecialchars($keyInfo['prefix']) ?></div>
                <div>📅 <b>Hết hạn:</b> <?= htmlspecialchars($keyInfo['expires_at'] ?: 'Vĩnh viễn') ?></div>
                <div>⏳ <b>Còn lại:</b> <?= htmlspecialchars(getTimeRemaining($keyInfo['expires_at'])) ?></div>
                <div>🔖 <b>Trạng thái:</b> <?= $keyInfo['status'] === 'active' ? '✅ Hoạt động' : '⛔ ' . htmlspecialchars($keyInfo['status']) ?></div>
            </div>
            <?php if ($keyInfo['status'] !== 'active'): ?>
                <p class="warn">⚠️ Key này đã bị vô hiệu hóa hoặc hết hạn!</p>
            <?php endif; ?>
        <?php else: ?>
            <p class="warn">⚠️ Không tìm thấy thông tin key (có thể key đã bị dọn dẹp khỏi hệ thống).</p>
        <?php endif; ?>

        <button class="copy-btn" id="copyBtn" onclick="copyKey()">📋 Sao Chép Key</button>
        <a class="back-link" href="getkey.php">🔑 Tạo Key Khác</a>
    </div>
<?php endif; ?>
</div>

<script>
function copyKey() {
    const key = document.getElementById('keyText').innerText.trim();
    const btn = document.getElementById('copyBtn');
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(key).then(() => {
            btn.innerHTML = '✅ Đã sao chép!';
            setTimeout(() => btn.innerHTML = '📋 Sao Chép Key', 2500);
        }).catch(() => fallbackCopy(key, btn));
    } else {
        fallbackCopy(key, btn);
    }
}
function fallbackCopy(key, btn) {
    const ta = document.createElement('textarea');
    ta.value = key;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    btn.innerHTML = '✅ Đã sao chép!';
    setTimeout(() => btn.innerHTML = '📋 Sao Chép Key', 2500);
}
</script>
</body>
</html>