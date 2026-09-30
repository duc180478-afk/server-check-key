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
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Key Panel</title>
<style>
:root{--bg:#0b0f14;--p:#121a24;--line:#243044;--t:#e8eef7;--m:#8b9bb4;--a:#3b82f6;--ok:#34d399;--bad:#f87171}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:var(--bg);color:var(--t)}
.top{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid var(--line);background:#0e1520;flex-wrap:wrap;gap:8px}
.brand{font-weight:800}.brand span{color:var(--a)}
.wrap{max-width:1000px;margin:0 auto;padding:18px 14px;display:grid;gap:14px}
.card{background:var(--p);border:1px solid var(--line);border-radius:14px;padding:16px}
h2{font-size:.95rem;margin-bottom:12px}
label{display:block;font-size:.72rem;color:var(--m);margin:8px 0 4px;text-transform:uppercase}
input,select{width:100%;padding:10px;border-radius:8px;border:1px solid var(--line);background:#0a1018;color:var(--t)}
.row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
@media(max-width:640px){.row{grid-template-columns:1fr}}
button{margin-top:12px;padding:11px 14px;border:0;border-radius:8px;background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;font-weight:700;cursor:pointer;width:100%}
.msg{padding:10px;background:#0f2a1d;color:var(--ok);border-radius:8px}
.keys-out{background:#0a1018;border:1px dashed var(--a);border-radius:8px;padding:12px;font-family:monospace;word-break:break-all;margin-top:8px}
table{width:100%;border-collapse:collapse;font-size:.82rem}
th,td{padding:8px;border-bottom:1px solid var(--line);text-align:left}
th{color:var(--m);font-size:.7rem}
.mono{font-family:monospace;font-size:.78rem}
.hint{font-size:.8rem;color:var(--m);line-height:1.5;margin-top:8px}
.mode{display:flex;gap:12px;margin:8px 0;font-size:.9rem}
</style>
</head>
<body>
<header class="top">
  <div class="brand">MDU<span>CDZ</span> Panel</div>
  <div style="color:var(--m);font-size:.9rem"><?= htmlspecialchars($name) ?> · <?= $isReseller?'RESELLER':'ADMIN' ?> · <a href="logout.php" style="color:var(--a)">Logout</a></div>
</header>
<div class="wrap">
<?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($flash): ?>
<div class="card"><h2>Key vừa tạo</h2>
<div class="keys-out" id="fk"><?php foreach($flash as $k) echo htmlspecialchars($k)."\n"; ?></div>
<button type="button" onclick="navigator.clipboard.writeText(document.getElementById('fk').innerText)">Copy</button>
</div>
<?php endif; ?>

<div class="card">
<h2>Tạo key</h2>
<form method="post" action="actions.php">
<?= csrfField() ?>
<input type="hidden" name="action" value="create_single">
<label>App</label>
<select name="app">
<?php foreach ($apps as $k=>$a): ?>
<option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars($a['label']??$k) ?></option>
<?php endforeach; ?>
</select>
<div class="mode">
  <label style="text-transform:none;display:flex;gap:6px;align-items:center"><input type="radio" name="key_mode" value="random" checked onchange="m()"> Prefix + random</label>
  <label style="text-transform:none;display:flex;gap:6px;align-items:center"><input type="radio" name="key_mode" value="custom" onchange="m()"> Chỉ đúng chuỗi (custom)</label>
</div>
<label id="pl">Prefix</label>
<input name="prefix" id="pi" value="FLFF" required>
<div class="row">
<div><label>Số ngày (trống=∞)</label><input type="number" name="days" min="1"></div>
<div><label>HWID limit</label><input type="number" name="hwid_limit" value="1" min="1" max="9999"></div>
</div>
<label>Ghi chú</label><input name="note">
<button type="submit">Tạo key</button>
</form>
<p class="hint" id="hint">Random: FLFF-xxxxxxxxxxxx · Custom: key đúng như ô nhập (vd NOA-VIP-1)</p>
</div>

<div class="card">
<h2>API POST (không token)</h2>
<p class="hint">
<code>POST /api_reseller.php</code><br>
Tạo: <code>user, pass, action=create, prefix, key_mode=random|custom, app, days, limit</code><br>
List key của reseller: <code>user, pass, action=list</code><br>
Chỉ trả / gắn key của đúng reseller đó (created_by).
</p>
</div>

<div class="card">
<h2><?= $isReseller ? 'Key của bạn' : 'Tất cả key' ?> (<?= count($keys) ?>)</h2>
<div style="overflow:auto">
<table>
<tr><th>Key</th><th>App</th><th>By</th><th>Limit</th><th>Hết hạn</th><th>Status</th>
<th></th></tr>
<?php foreach ($keys as $r): ?>
<tr>
<td class="mono"><?= htmlspecialchars($r['key']) ?></td>
<td><?= htmlspecialchars($r['app']??'') ?></td>
<td><?= htmlspecialchars($r['created_by']??'') ?></td>
<td><?= (int)($r['hwid_limit']??1) ?></td>
<td><?= fmtExp($r['expires_at'] ?? null) ?></td>
<td><?= htmlspecialchars($r['status']??'') ?></td>
<td>
<form method="post" action="actions.php" style="display:inline" onsubmit="return confirm('Xóa key này?')">
<?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
<button type="submit" style="width:auto;padding:4px 8px;background:#7f1d1d">Xóa</button>
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
