<?php
// admin/config.php — quản trị cấu hình getkey (app, link tải, Link4m, Telegram).
// Dùng auth của folder admin. CHỈ ADMIN — reseller bị đá về index.php.
// Quy tắc: app_key + prefix là identity (client hardcode / trong key text) → khoá.

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/../keygen_functions.php';
adminRequireLogin();
if (adminIsReseller()) {
    header('Location: index.php?msg=' . urlencode('Reseller không có quyền cấu hình'));
    exit;
}
adminUsersInit();

 $flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $flash = 'Phiên không hợp lệ — thử lại.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save_app') {
            $flash = saveKeygenApp($_POST)
                ? 'Đã lưu — áp dụng ngay cho trang getkey, ngày cập nhật trên thẻ đã tự nhảy.'
                : 'Không lưu được: cần đủ Tên hiển thị (app mới cần thêm Prefix) và Key chỉ gồm a-z 0-9 gạch-dưới.';
        } elseif ($action === 'delete_app') {
            deleteKeygenApp((string)($_POST['app_key'] ?? ''));
            $flash = 'Đã gỡ ứng dụng khỏi trang getkey.';
        } elseif ($action === 'save_settings') {
            $adminUrl = trim((string)($_POST['tg_admin_url'] ?? ''));
            $groupUrl = trim((string)($_POST['tg_group_url'] ?? ''));
            if ($adminUrl === '') deleteKeygenSetting('tg_admin_url'); else setKeygenSetting('tg_admin_url', $adminUrl);
            if ($groupUrl === '') deleteKeygenSetting('tg_group_url'); else setKeygenSetting('tg_group_url', $groupUrl);
            $flash = 'Đã lưu liên kết Telegram.';
        }
        header('Location: config.php?ok=' . rawurlencode($flash));
        exit;
    }
}
if (isset($_GET['ok'])) $flash = (string)$_GET['ok'];

 $apps = listKeygenAppsAll();
 $tgAdmin = getKeygenSetting('tg_admin_url', 'https://t.me/your_admin_here');
 $tgGroup = getKeygenSetting('tg_group_url', 'https://t.me/your_channel_here');

function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#F6F4EE">
<title>Cấu hình getkey — KEY.SYS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,wght@0,400;0,500;0,600&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{--paper:#F6F4EE;--card:#FCFBF7;--ink:#191B17;--ink-soft:#4C4A42;--grid:#DEDAD0;--accent:#2B4FD8;--accent-ink:#F6F4EE;--amber:#C4780A;--line:#191B17;color-scheme:light;}
@media(prefers-color-scheme:dark){
  :root{--paper:#15160F;--card:#1C1D15;--ink:#EDEAE0;--ink-soft:#A6A295;--grid:#2B2C22;--accent:#7D95F5;--accent-ink:#15160F;--amber:#E0A03E;--line:#EDEAE0;color-scheme:dark;}
}
*{box-sizing:border-box;}
body{margin:0;background:var(--paper);color:var(--ink);font-family:"Newsreader",serif;
  background-image:linear-gradient(var(--grid) 1px,transparent 1px),linear-gradient(90deg,var(--grid) 1px,transparent 1px);
  background-size:28px 28px;}
.bar{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:20px 6vw;border-bottom:1.3px solid var(--line);flex-wrap:wrap;}
.wm{font-family:"IBM Plex Mono",monospace;font-size:13px;letter-spacing:.04em;}
.wm b{color:var(--accent);}
.bar-right{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.who{font-family:"IBM Plex Mono",monospace;font-size:11px;color:var(--ink-soft);}
.pill{font-family:"IBM Plex Mono",monospace;font-size:9.5px;border:1px solid var(--accent);color:var(--accent);padding:2px 8px;letter-spacing:.05em;}
.tbtn{font-family:"IBM Plex Mono",monospace;font-size:11px;color:var(--ink);text-decoration:none;border:1.3px solid var(--line);background:var(--card);padding:7px 11px;transition:border-color .18s,color .18s,transform .12s;}
.tbtn:hover{border-color:var(--accent);color:var(--accent);}
.tbtn:active{transform:translate(1px,1px);}

.wrap{max-width:980px;margin:0 auto;padding:24px 6vw 110px;}
h2.page{font-size:22px;font-weight:500;margin:30px 0 6px;}
.hint{font-family:"IBM Plex Mono",monospace;font-size:10.5px;color:var(--ink-soft);line-height:1.7;margin:0 0 6px;}

.card{position:relative;z-index:1;background:var(--card);border:1.3px solid var(--line);box-shadow:7px 7px 0 var(--line);margin-top:18px;transform:translate(-2px,-2px);transition:transform .18s ease,box-shadow .18s ease;}
.card:hover{transform:translate(0,0);box-shadow:4px 4px 0 var(--line);}
.card::before{content:"";position:absolute;inset:0;z-index:2;pointer-events:none;
  background-image:linear-gradient(110deg,transparent 47%,color-mix(in srgb,var(--accent) 16%,transparent) 49%,rgba(255,255,255,.35) 51%,transparent 53%);
  background-size:400% 100%;background-repeat:no-repeat;background-position:100% 0;opacity:0;}
.card:hover::before{opacity:1;animation:cardGlint .9s ease-out both;}
@keyframes cardGlint{from{background-position:100% 0;opacity:1;}to{background-position:0% 0;opacity:0;}}
.chead{position:relative;z-index:1;display:flex;justify-content:space-between;align-items:baseline;gap:10px;padding:16px 22px;border-bottom:1.3px solid var(--line);flex-wrap:wrap;}
.chead h2{font-size:19px;font-weight:500;margin:0;}
.ckey{font-family:"IBM Plex Mono",monospace;font-size:12px;color:var(--accent);}
.cupd{font-family:"IBM Plex Mono",monospace;font-size:10.5px;color:var(--ink-soft);}
.cbody{position:relative;z-index:1;padding:18px 22px;}

.f{display:flex;flex-direction:column;gap:6px;margin-bottom:12px;min-width:0;}
label{font-family:"IBM Plex Mono",monospace;font-size:10px;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.06em;}
input{width:100%;border:1.3px solid var(--line);background:var(--paper);color:var(--ink);padding:10px 12px;font-family:"IBM Plex Mono",monospace;font-size:13px;}
input:focus{outline:2px solid var(--accent);outline-offset:1px;}
input.locked{opacity:.55;cursor:not-allowed;background:var(--grid);}
.locktag{font-family:"IBM Plex Mono",monospace;font-size:8.5px;color:var(--amber);border:1px solid var(--amber);padding:1px 5px;white-space:nowrap;text-transform:none;letter-spacing:0;}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
@media(max-width:640px){.row{grid-template-columns:1fr;}}
.check{display:flex;align-items:center;gap:8px;font-family:"IBM Plex Mono",monospace;font-size:11.5px;padding-top:4px;}
.check input{width:auto;padding:0;margin:0;}

.btn{border:1.3px solid var(--line);background:var(--accent);color:var(--accent-ink);padding:12px 20px;font-family:"IBM Plex Mono",monospace;font-size:12.5px;cursor:pointer;transition:transform .12s,letter-spacing .2s,filter .2s;}
.btn:hover{letter-spacing:.05em;filter:brightness(1.06);}
.btn:active{transform:translate(2px,2px);}
.btn.warn{background:var(--paper);color:var(--amber);border-color:var(--amber);}
.btnrow{display:flex;gap:10px;margin-top:14px;flex-wrap:wrap;}

.flash{border:1.3px solid var(--accent);color:var(--accent);padding:10px 14px;font-family:"IBM Plex Mono",monospace;font-size:11.5px;margin-top:16px;background:var(--card);box-shadow:4px 4px 0 var(--line);}
.warnbox{font-family:"IBM Plex Mono",monospace;font-size:10.5px;color:var(--amber);border:1px dashed var(--amber);padding:10px 12px;margin-bottom:12px;line-height:1.6;}

@media(max-width:560px){
  .bar{padding:14px 20px;}
  .wrap{padding:18px 17px 90px;}
  .chead,.cbody{padding-left:16px;padding-right:16px;}
}
</style>
</head>
<body>
<header class="bar">
  <span class="wm"><b>KEY</b>.ADMIN — CẤU HÌNH GETKEY</span>
  <div class="bar-right">
    <span class="who"><?= esc(adminUsername()) ?></span>
    <span class="pill">ADMIN</span>
    <a class="tbtn" href="index.php">← Panel</a>
    <a class="tbtn" href="logout.php">Logout</a>
  </div>
</header>

<div class="wrap">
<?php if ($flash !== ''): ?><div class="flash"><?= esc($flash) ?></div><?php endif; ?>

<h2 class="page">Ứng dụng</h2>
<p class="hint">Bấm Lưu là áp dụng ngay cho trang getkey, không cần sửa code. Ngày "Cập nhật" trên thẻ download tự nhảy theo lần lưu cuối.</p>
<p class="hint">Ô đánh dấu <span class="locktag">KHOÁ</span> là identity mà client app hardcode — sửa vào là key cũ chết, nên đã bị khoá cố định.</p>

<?php foreach ($apps as $a): ?>
<form class="card" method="post">
  <?= csrfField() ?>
  <input type="hidden" name="action" value="save_app">
  <input type="hidden" name="app_key" value="<?= esc($a['app_key']) ?>">
  <div class="chead">
    <span class="ckey"><?= esc($a['app_key']) ?></span>
    <span class="cupd"><?= ((int)$a['updated_at'] > 0) ? 'Lưu cuối: ' . date('d/m/Y H:i', (int)$a['updated_at']) : 'Chưa từng lưu qua quản trị' ?></span>
  </div>
  <div class="cbody">
    <div class="f"><label>Tên hiển thị trên web</label><input type="text" name="label" value="<?= esc($a['label']) ?>" required></div>
    <div class="row">
      <div class="f"><label>Prefix <span class="locktag">KHOÁ</span> — nằm trong key text</label><input type="text" class="locked" value="<?= esc($a['prefix']) ?>" disabled tabindex="-1"></div>
      <div class="f"><label>Version</label><input type="text" name="version" value="<?= esc($a['version']) ?>"></div>
    </div>
    <div class="f"><label>Link4m (link vượt link)</label><input type="text" name="shortlink" value="<?= esc($a['shortlink']) ?>"></div>
    <div class="f"><label>Link tải app (bấm vào thẻ download)</label><input type="text" name="download_url" value="<?= esc($a['download_url']) ?>" placeholder="https://..."></div>
    <div class="row">
      <div class="f"><label>Ảnh app — URL (trống = chữ cái đầu)</label><input type="text" name="icon" value="<?= esc($a['icon']) ?>" placeholder="https://... hoặc /assets/icon.png"></div>
      <div class="f"><label>Thứ tự hiển thị</label><input type="text" name="sort_order" value="<?= (int)$a['sort_order'] ?>"></div>
    </div>
    <div class="row">
      <div class="f"><label>Dung lượng</label><input type="text" name="size" value="<?= esc($a['size']) ?>"></div>
      <div class="f"><label>Nền tảng</label><input type="text" name="platform" value="<?= esc($a['platform']) ?>"></div>
    </div>
    <div class="f"><label>Mô tả ngắn</label><input type="text" name="description" value="<?= esc($a['description']) ?>"></div>
    <div class="check"><input type="checkbox" name="enabled" value="1" <?= $a['enabled'] ? 'checked' : '' ?>><span>Hiện trên trang getkey</span></div>
    <div class="btnrow">
      <button class="btn" type="submit" name="action2" value="1">Lưu</button>
      <button class="btn warn" type="submit" name="action" value="delete_app" onclick="return confirm('Gỡ ứng dụng khỏi trang getkey? Key đã sinh vẫn hoạt động bình thường.');">Gỡ khỏi web</button>
    </div>
  </div>
</form>
<?php endforeach; ?>

<form class="card" method="post">
  <?= csrfField() ?>
  <input type="hidden" name="action" value="save_app">
  <div class="chead"><span class="ckey">+ THÊM ỨNG DỤNG MỚI</span></div>
  <div class="cbody">
    <div class="warnbox">⚠ App mới phải có trong SYSTEM_APPS (functions.php) VÀ client hardcode đúng identifier — không thì api_check từ chối key của app này. Phần này chỉ chuẩn bị sẵn thẻ marketing trước khi client sẵn sàng.</div>
    <div class="f"><label>Identifier (a-z 0-9 _, khoá vĩnh viễn sau khi thêm)</label><input type="text" name="app_key" required pattern="[a-zA-Z0-9_-]{1,40}" placeholder="vd: locmod"></div>
    <div class="row">
      <div class="f"><label>Tên hiển thị</label><input type="text" name="label" required placeholder="Loc Mod Proxy"></div>
      <div class="f"><label>Prefix (khoá vĩnh viễn)</label><input type="text" name="prefix" required placeholder="LOCMOD"></div>
    </div>
    <div class="f"><label>Link tải app</label><input type="text" name="download_url" placeholder="https://..."></div>
    <div class="btnrow"><button class="btn" type="submit">Thêm ứng dụng</button></div>
  </div>
</form>

<h2 class="page">Liên kết Telegram</h2>
<p class="hint">Bỏ trống và Lưu nếu muốn quay về giá trị mặc định trong code.</p>
<form class="card" method="post">
  <?= csrfField() ?>
  <input type="hidden" name="action" value="save_settings">
  <div class="cbody">
    <div class="f"><label>Telegram Admin (https://t.me/...)</label><input type="text" name="tg_admin_url" value="<?= esc($tgAdmin) ?>"></div>
    <div class="f"><label>Group Share Mod (https://t.me/...)</label><input type="text" name="tg_group_url" value="<?= esc($tgGroup) ?>"></div>
    <div class="btnrow"><button class="btn" type="submit">Lưu</button></div>
  </div>
</form>
</div>
</body>
</html>
