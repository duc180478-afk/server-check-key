<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/key_helpers.php';
adminRequireLogin();
adminUsersInit();
@date_default_timezone_set('Asia/Ho_Chi_Minh');
function fmtExp($s) {
    if ($s === null || $s === '') return '∞';
    @date_default_timezone_set('Asia/Ho_Chi_Minh');
    $ts = strtotime($s);
    if ($ts === false) return htmlspecialchars((string)$s);
    $left = $ts - time();
    $label = date('d/m/Y H:i', $ts);
    if ($left <= 0) return $label . ' (hết hạn)';
    $h = (int)floor($left / 3600);
    $m = (int)floor(($left % 3600) / 60);
    return $label . " (còn {$h}h{$m}m)";
}

 $isReseller = adminIsReseller();
 $name = adminUsername();
 $msg = $_GET['msg'] ?? '';
 $flash = $_SESSION['flash_keys'] ?? [];
unset($_SESSION['flash_keys']);

 $keys = listKeysForUser($name, $isReseller ? 'reseller' : 'admin', 100);
 $apps = defined('SYSTEM_APPS') ? SYSTEM_APPS : [
    'fakelag' => ['label'=>'FakeLag','default_prefix'=>'FLFF'],
    'proxy' => ['label'=>'Proxy','default_prefix'=>'PRX'],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#F6F4EE">
<title>Key Panel — KEY.SYS</title>
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
code{font-family:"IBM Plex Mono",monospace;font-size:.95em;}

.bar{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:20px 6vw;border-bottom:1.3px solid var(--line);flex-wrap:wrap;position:relative;}
.wm{font-family:"IBM Plex Mono",monospace;font-size:13px;letter-spacing:.04em;}
.wm b{color:var(--accent);}
.bar-right{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.who{font-family:"IBM Plex Mono",monospace;font-size:11px;color:var(--ink-soft);}
.pill{font-family:"IBM Plex Mono",monospace;font-size:9.5px;border:1px solid var(--accent);color:var(--accent);padding:2px 8px;letter-spacing:.05em;}
.pill.reseller{border-color:var(--amber);color:var(--amber);}
.tbtn{font-family:"IBM Plex Mono",monospace;font-size:11px;color:var(--ink);text-decoration:none;border:1.3px solid var(--line);background:var(--card);padding:7px 11px;transition:border-color .18s,color .18s,transform .12s;}
.tbtn:hover{border-color:var(--accent);color:var(--accent);}
.tbtn:active{transform:translate(1px,1px);}

.wrap{max-width:980px;margin:0 auto;padding:24px 6vw 110px;}

.card{position:relative;z-index:1;background:var(--card);border:1.3px solid var(--line);box-shadow:7px 7px 0 var(--line);margin-top:18px;transform:translate(-2px,-2px);transition:transform .18s ease,box-shadow .18s ease;}
.card:hover{transform:translate(0,0);box-shadow:4px 4px 0 var(--line);}
.card::before{content:"";position:absolute;inset:0;z-index:2;pointer-events:none;
  background-image:linear-gradient(110deg,transparent 47%,color-mix(in srgb,var(--accent) 16%,transparent) 49%,rgba(255,255,255,.35) 51%,transparent 53%);
  background-size:400% 100%;background-repeat:no-repeat;background-position:100% 0;opacity:0;}
.card:hover::before{opacity:1;animation:cardGlint .9s ease-out both;}
@keyframes cardGlint{from{background-position:100% 0;opacity:1;}to{background-position:0% 0;opacity:0;}}
.chead{position:relative;z-index:1;display:flex;justify-content:space-between;align-items:center;gap:10px;padding:16px 22px;border-bottom:1.3px solid var(--line);flex-wrap:wrap;}
.chead h2{font-size:19px;font-weight:500;margin:0;}
.cbody{position:relative;z-index:1;padding:18px 22px;}
.badge{font-family:"IBM Plex Mono",monospace;font-size:10.5px;border:1px solid var(--amber);color:var(--amber);padding:3px 8px;transform:rotate(-4deg);}

.f{display:flex;flex-direction:column;gap:6px;margin-bottom:12px;min-width:0;}
label{font-family:"IBM Plex Mono",monospace;font-size:10px;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.06em;}
input,select{width:100%;border:1.3px solid var(--line);background:var(--paper);color:var(--ink);padding:10px 12px;font-family:"IBM Plex Mono",monospace;font-size:13px;}
input:focus,select:focus{outline:2px solid var(--accent);outline-offset:1px;}
input[type=radio]{width:auto;padding:0;margin:0;}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
@media(max-width:640px){.row{grid-template-columns:1fr;}}
.mode{display:flex;gap:16px;margin:2px 0 12px;flex-wrap:wrap;}
.mode-o{display:flex;gap:7px;align-items:center;font-family:"IBM Plex Mono",monospace;font-size:11.5px;cursor:pointer;text-transform:none;letter-spacing:0;color:var(--ink);}

.btn{border:1.3px solid var(--line);background:var(--accent);color:var(--accent-ink);padding:12px 20px;font-family:"IBM Plex Mono",monospace;font-size:12.5px;cursor:pointer;transition:transform .12s,letter-spacing .2s,filter .2s;}
.btn:hover{letter-spacing:.05em;filter:brightness(1.06);}
.btn:active{transform:translate(2px,2px);}
.btn.full{width:100%;}
.btn.ghost{background:var(--paper);color:var(--ink);}
.btn.ghost:hover{border-color:var(--accent);color:var(--accent);}
.btn.warn{background:var(--paper);color:var(--amber);border-color:var(--amber);}
.btn.sm{padding:5px 10px;font-size:10.5px;letter-spacing:0;}
.btnrow{display:flex;gap:10px;margin-top:14px;flex-wrap:wrap;}

.flash{border:1.3px solid var(--accent);color:var(--accent);padding:10px 14px;font-family:"IBM Plex Mono",monospace;font-size:11.5px;margin-top:16px;background:var(--card);box-shadow:4px 4px 0 var(--line);}
.keys-out{border:1.3px dashed var(--accent);padding:14px;font-family:"IBM Plex Mono",monospace;font-size:12.5px;word-break:break-all;background:var(--paper);white-space:pre-wrap;}
.hint{font-family:"IBM Plex Mono",monospace;font-size:10.5px;color:var(--ink-soft);line-height:1.7;margin:12px 0 0;}

.tbl-wrap{overflow:auto;}
table{width:100%;border-collapse:collapse;font-family:"IBM Plex Mono",monospace;font-size:11.5px;}
th{text-align:left;color:var(--ink-soft);font-size:9.5px;text-transform:uppercase;letter-spacing:.06em;padding:8px 10px;border-bottom:1.3px solid var(--line);}
td{padding:9px 10px;border-bottom:1px solid var(--grid);white-space:nowrap;}
tr:hover td{background:color-mix(in srgb,var(--grid) 45%,transparent);}
.st{display:inline-block;font-size:9.5px;border:1px solid;padding:1px 7px;}
.st.active{border-color:var(--accent);color:var(--accent);}
.st.expired{border-color:var(--amber);color:var(--amber);}
.st.disabled,.st.revoked{border-color:var(--ink-soft);color:var(--ink-soft);}

@media(max-width:560px){
  .bar{padding:14px 20px;}
  .wrap{padding:18px 17px 90px;}
  .chead,.cbody{padding-left:16px;padding-right:16px;}
}
</style>
</head>
<body>
<header class="bar">
  <span class="wm"><b>KEY</b>.ADMIN — MDUCDZ</span>
  <div class="bar-right">
    <span class="who"><?= htmlspecialchars($name) ?></span>
    <span class="pill <?= $isReseller?'reseller':'' ?>"><?= $isReseller?'RESELLER':'ADMIN' ?></span>
    <?php if (!$isReseller): ?><a class="tbtn" href="config.php">Cấu hình getkey</a><?php endif; ?>
    <a class="tbtn" href="logout.php">Logout</a>
  </div>
</header>

<div class="wrap">
<?php if ($msg): ?><div class="flash"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($flash): ?>
<div class="card">
  <div class="chead"><h2>Key vừa tạo</h2><span class="badge">MỚI</span></div>
  <div class="cbody">
    <div class="keys-out" id="fk"><?php foreach($flash as $k) echo htmlspecialchars($k)."\n"; ?></div>
    <div class="btnrow"><button class="btn ghost sm" type="button" onclick="navigator.clipboard.writeText(document.getElementById('fk').innerText)">Copy</button></div>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="chead"><h2>Tạo key</h2><span class="badge">KEYGEN</span></div>
  <div class="cbody">
    <form method="post" action="actions.php">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="create_single">
      <div class="f"><label>App</label>
        <select name="app">
        <?php foreach ($apps as $k=>$a): ?>
          <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($a['label']??$k) ?></option>
        <?php endforeach; ?>
        </select>
      </div>
      <div class="mode">
        <label class="mode-o"><input type="radio" name="key_mode" value="random" checked onchange="m()"> Prefix + random</label>
        <label class="mode-o"><input type="radio" name="key_mode" value="custom" onchange="m()"> Chỉ đúng chuỗi (custom)</label>
      </div>
      <div class="f"><label id="pl">Prefix</label><input name="prefix" id="pi" value="FLFF" required></div>
      <div class="row">
        <div class="f"><label>Số ngày (trống = ∞)</label><input type="number" name="days" min="1"></div>
        <div class="f"><label>HWID limit</label><input type="number" name="hwid_limit" value="1" min="1" max="9999"></div>
      </div>
      <div class="f"><label>Ghi chú</label><input name="note"></div>
      <div class="btnrow"><button class="btn" type="submit">Tạo key</button></div>
    </form>
    <p class="hint" id="hint">Random: FLFF-xxxxxxxxxxxx · Custom: key đúng như ô nhập (vd NOA-VIP-1)</p>
  </div>
</div>

<div class="card">
  <div class="chead"><h2>API POST (không token)</h2></div>
  <div class="cbody">
    <p class="hint">
      <code>POST /api_reseller.php</code><br>
      Tạo: <code>user, pass, action=create, prefix, key_mode=random|custom, app, days, limit</code><br>
      List key của reseller: <code>user, pass, action=list</code><br>
      Chỉ trả / gắn key của đúng reseller đó (created_by).
    </p>
  </div>
</div>

<div class="card">
  <div class="chead"><h2><?= $isReseller ? 'Key của bạn' : 'Tất cả key' ?> (<?= count($keys) ?>)</h2>
    <span class="pill <?= $isReseller?'reseller':'' ?>"><?= $isReseller?'RESELLER VIEW':'ADMIN VIEW' ?></span></div>
  <div class="cbody tbl-wrap" style="padding:8px 10px 14px;">
    <table>
      <tr><th>Key</th><th>App</th><th>By</th><th>Limit</th><th>Hết hạn</th><th>Status</th><th></th></tr>
      <?php foreach ($keys as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['key']) ?></td>
        <td><?= htmlspecialchars($r['app']??'') ?></td>
        <td><?= htmlspecialchars($r['created_by']??'') ?></td>
        <td><?= (int)($r['hwid_limit']??1) ?></td>
        <td><?= fmtExp($r['expires_at'] ?? null) ?></td>
        <td><span class="st <?= htmlspecialchars($r['status']??'') ?>"><?= htmlspecialchars($r['status']??'') ?></span></td>
        <td>
          <form method="post" action="actions.php" style="display:inline" onsubmit="return confirm('Xóa key này?')">
            <?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="btn warn sm" type="submit">Xóa</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
</div>

<script>
function m(){
  const c=document.querySelector('[name=key_mode]:checked').value==='custom';
  document.getElementById('pl').textContent=c?'Key (đúng chuỗi)':'Prefix';
  document.getElementById('hint').textContent=c?'Custom: không thêm random':'Random: prefix + 12 ký tự';
}
</script>
</body>
</html>
