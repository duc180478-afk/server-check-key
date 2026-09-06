<?php
require_once __DIR__ . '/keygen_functions.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lấy Key Miễn Phí - <?= KEYGEN_GAME_LABEL ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Arial, sans-serif; }
body { background:#eef0f3; min-height:100vh; }
.navbar { background:#1a8cff; height:58px; display:flex; align-items:center; justify-content:flex-end; padding:0 18px; position:relative; }
.hamburger { background:transparent; border:1px solid rgba(255,255,255,.5); border-radius:6px; width:44px; height:40px; cursor:pointer; display:flex; flex-direction:column; justify-content:center; gap:6px; align-items:center; }
.hamburger span { display:block; width:24px; height:2.5px; background:#fff; border-radius:2px; }
.nav-menu { display:none; position:absolute; top:62px; right:14px; background:#fff; border-radius:8px; box-shadow:0 4px 16px rgba(0,0,0,.15); overflow:hidden; z-index:99; min-width:180px; }
.nav-menu.open { display:block; }
.nav-menu a { display:block; padding:13px 18px; color:#333; text-decoration:none; font-size:15px; }
.nav-menu a:hover { background:#f0f6ff; }
.container { max-width:660px; margin:26px auto; padding:0 16px; }
.welcome-box { background:#e4e6eb; border-radius:10px; padding:18px 22px; display:flex; justify-content:space-between; align-items:center; margin-bottom:22px; }
.welcome-box h2 { color:#3a3f47; font-size:24px; font-weight:600; }
.close-btn { background:none; border:none; font-size:26px; color:#555; cursor:pointer; line-height:1; }
.license-card { background:#fdfdfd; border:1px solid #d8dce1; border-radius:10px; overflow:hidden; }
.license-card h1 { padding:18px 22px; color:#1b2a4a; font-size:24px; font-weight:600; border-bottom:1px solid #e6e9ec; }
.card-body { padding:24px 22px; }
.input-group { display:flex; align-items:stretch; margin-bottom:16px; border:1px solid #cfd4da; border-radius:8px; overflow:hidden; background:#fff; }
.icon-box { width:52px; min-width:52px; background:#f1f2f4; display:flex; align-items:center; justify-content:center; font-size:21px; border-right:1px solid #dfe3e7; }
.input-group .value { flex:1; padding:14px 15px; font-size:17px; color:#222; display:flex; align-items:center; background:#fff; min-width:0; word-break:break-word; }
.input-group .value.select-look::after { content:'▾'; margin-left:auto; color:#666; padding-left:10px; }
.unit { display:flex; align-items:center; padding:0 16px; color:#333; font-size:17px; background:#fafbfc; border-left:1px solid #dfe3e7; }
.btn-row { display:flex; justify-content:flex-end; margin-top:22px; }
.generate-btn { background:#1a8cff; color:#fff; border:none; padding:13px 30px; border-radius:8px; font-size:18px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:9px; transition:background .2s; }
.generate-btn:hover { background:#0f76e0; }
.generate-btn:disabled { background:#9cc7f5; cursor:not-allowed; }
.result-area { margin-top:20px; }
.result-box { border-radius:10px; padding:20px; text-align:center; }
.result-box.success { background:#fff; border:2px solid #1a8cff; }
.result-box.error { background:#fff5f5; border:1px solid #f5c2c2; color:#c0392b; }
.rb-title { font-size:20px; font-weight:700; color:#1b2a4a; margin-bottom:8px; }
.rb-text { color:#555; font-size:15px; margin-bottom:16px; }
.rb-note { color:#888; font-size:13px; margin-top:14px; }
.unlock-btn { display:block; background:#1a8cff; color:#fff; padding:16px; border-radius:8px; text-decoration:none; font-size:18px; font-weight:700; }
.unlock-btn:hover { background:#0f76e0; }
.footer { text-align:center; color:#9aa1a9; font-size:13px; margin-top:26px; }
</style>
</head>
<body>

<nav class="navbar">
    <button class="hamburger" onclick="toggleMenu()" aria-label="Menu"><span></span><span></span><span></span></button>
    <div class="nav-menu" id="navMenu">
        <a href="getkey.php">🔑 Lấy Key</a>
    </div>
</nav>

<div class="container">

    <div class="welcome-box" id="welcomeBox">
        <h2>Welcome Friend</h2>
        <button class="close-btn" onclick="document.getElementById('welcomeBox').style.display='none'">&times;</button>
    </div>

    <div class="license-card">
        <h1>Create License - Việt Nam</h1>
        <div class="card-body">
            <form id="licenseForm">
                <div class="input-group">
                    <div class="icon-box">🎮</div>
                    <div class="value select-look"><?= htmlspecialchars(KEYGEN_GAME_LABEL) ?></div>
                </div>

                <div class="input-group">
                    <div class="icon-box">📱</div>
                    <div class="value">1</div>
                    <div class="unit">device</div>
                </div>

                <div class="input-group">
                    <div class="icon-box">📅</div>
                    <div class="value">1 Days</div>
                </div>

                <div class="input-group">
                    <div class="icon-box">💎</div>
                    <div class="value">FREE</div>
                </div>

                <div class="btn-row">
                    <button type="submit" class="generate-btn" id="generateBtn">⇥ Generate</button>
                </div>
            </form>
        </div>
    </div>

    <div class="result-area" id="resultArea"></div>

    <p class="footer">🔒 Key được tạo tự động bởi hệ thống</p>
</div>

<script>
const form = document.getElementById('licenseForm');
const btn = document.getElementById('generateBtn');
const resultArea = document.getElementById('resultArea');
const btnOriginal = btn.innerHTML;

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    btn.disabled = true;
    btn.innerHTML = '⏳ Đang xử lý...';
    resultArea.innerHTML = '';
    try {
        const res = await fetch('getkey_action.php', { method: 'POST' });
        const data = await res.json();
        if (data.status === 'success') {
            resultArea.innerHTML =
                '<div class="result-box success">' +
                    '<p class="rb-title">✅ Sẵn sàng!</p>' +
                    '<p class="rb-text">Bấm nút bên dưới để vượt link rút gọn.<br>Sau khi hoàn thành, key sẽ hiện ra ngay.</p>' +
                    '<a class="unlock-btn" href="' + data.unlockUrl + '" target="_blank" rel="noopener noreferrer">🔓 Vượt Link Để Lấy Key</a>' +
                    '<p class="rb-note">⚠️ Tắt trình chặn quảng cáo (nếu có) để vượt link thành công.<br>📊 Còn ' + (data.remaining ?? '?') + ' lượt lấy key trong giờ này.</p>' +
                '</div>';
        } else {
            resultArea.innerHTML = '<div class="result-box error">❌ ' + (data.message || 'Có lỗi xảy ra, thử lại!') + '</div>';
        }
    } catch (err) {
        resultArea.innerHTML = '<div class="result-box error">❌ Lỗi kết nối đến server, vui lòng thử lại!</div>';
    }
    btn.disabled = false;
    btn.innerHTML = btnOriginal;
});

function toggleMenu() {
    document.getElementById('navMenu').classList.toggle('open');
}
</script>
</body>
</html>