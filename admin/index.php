<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/admin_auth.php';
adminRequireLogin();

$isReseller = adminIsReseller();
$adminName = $_SESSION['admin_username'] ?? 'admin';
$msg = $_GET['msg'] ?? '';
$flashKeys = $_SESSION['flash_keys'] ?? [];
unset($_SESSION['flash_keys']);

$filterApp = $_GET['app'] ?? 'all';
$pdo = Database::getConnection();
if ($filterApp !== 'all' && $filterApp !== '') {
    $st = $pdo->prepare('SELECT * FROM keys WHERE app = ? ORDER BY id DESC LIMIT 100');
    $st->execute([$filterApp]);
} else {
    $st = $pdo->query('SELECT * FROM keys ORDER BY id DESC LIMIT 100');
}
$keys = $st->fetchAll(PDO::FETCH_ASSOC);

$apps = defined('SYSTEM_APPS') ? SYSTEM_APPS : [
    'fakelag' => ['label' => 'FakeLag', 'default_prefix' => 'FLFF'],
    'proxy'   => ['label' => 'Proxy', 'default_prefix' => 'PRX'],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>MDUCDZ Admin</title>
<style>
:root {
  --bg:#0b0f14; --panel:#121a24; --panel2:#0e1520; --line:#243044;
  --text:#e8eef7; --muted:#8b9bb4; --accent:#3b82f6; --ok:#34d399; --warn:#fbbf24; --bad:#f87171;
}
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:Inter,system-ui,sans-serif; background:var(--bg); color:var(--text); min-height:100vh; }
a { color:var(--accent); text-decoration:none; }
.top { display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid var(--line); background:var(--panel2); position:sticky; top:0; z-index:10; }
.brand { font-weight:800; font-size:1.15rem; }
.brand span { color:var(--accent); }
.who { color:var(--muted); font-size:.9rem; }
.who b { color:var(--text); }
.badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:.75rem; font-weight:700; }
.badge.admin { background:#1e3a5f; color:#93c5fd; }
.badge.reseller { background:#14532d; color:#86efac; }
.wrap { max-width:1100px; margin:0 auto; padding:20px 16px 40px; display:grid; gap:18px; }
.grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
@media (max-width:860px){ .grid { grid-template-columns:1fr; } }
.card { background:var(--panel); border:1px solid var(--line); border-radius:14px; padding:18px; }
.card h2 { font-size:1rem; margin-bottom:14px; }
label { display:block; font-size:.75rem; color:var(--muted); margin:10px 0 5px; text-transform:uppercase; letter-spacing:.03em; }
input, select { width:100%; padding:10px 12px; border-radius:8px; border:1px solid var(--line); background:var(--panel2); color:var(--text); }
.row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
button, .btn { display:inline-block; border:0; border-radius:8px; padding:11px 16px; font-weight:700; cursor:pointer; background:linear-gradient(135deg,#3b82f6,#2563eb); color:#fff; font-size:.92rem; }
button.secondary { background:#1e293b; border:1px solid var(--line); }
button.danger { background:#7f1d1d; }
.msg { padding:12px 14px; border-radius:10px; background:#0f2a1d; color:var(--ok); border:1px solid #14532d; }
.keys-out { margin-top:12px; background:#0a1018; border:1px dashed var(--accent); border-radius:10px; padding:12px; font-family:ui-monospace,monospace; word-break:break-all; }
table { width:100%; border-collapse:collapse; font-size:.85rem; }
th, td { padding:10px 8px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top; }
th { color:var(--muted); font-weight:600; font-size:.75rem; text-transform:uppercase; }
.mono { font-family:ui-monospace,monospace; font-size:.8rem; }
.st-active { color:var(--ok); }
.st-disabled, .st-expired { color:var(--bad); }
.actions form { display:inline; }
.actions button { padding:6px 10px; font-size:.75rem; margin:2px; }
.filters { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
.filters a { padding:6px 12px; border-radius:999px; border:1px solid var(--line); color:var(--muted); font-size:.85rem; }
.filters a.on { background:var(--accent); color:#fff; border-color:transparent; }
.api-box { font-size:.85rem; color:var(--muted); line-height:1.55; }
.api-box code { color:#93c5fd; background:#0a1018; padding:2px 6px; border-radius:4px; }
</style>
</head>
<body>
<header class="top">
  <div class="brand">MDU<span>CDZ</span> Key Panel</div>
  <div class="who">
    Xin chào <b><?= htmlspecialchars($adminName) ?></b>
    <span class="badge <?= $isReseller ? 'reseller' : 'admin' ?>"><?= $isReseller ? 'RESELLER' : 'ADMIN' ?></span>
    · <a href="logout.php">Đăng xuất</a>
  </div>
</header>

<div class="wrap">
  <?php if ($msg): ?><div class="msg"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if ($flashKeys): ?>
    <div class="card">
      <h2>Key vừa tạo</h2>
      <div class="keys-out" id="flashKeys"><?php foreach ($flashKeys as $k) echo htmlspecialchars($k) . "\n"; ?></div>
      <button type="button" class="secondary" style="margin-top:10px" onclick="navigator.clipboard.writeText(document.getElementById('flashKeys').innerText)">Copy tất cả</button>
    </div>
  <?php endif; ?>

  <div class="grid">
    <div class="card">
      <h2>Tạo key (POST)</h2>
      <form method="post" action="actions.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create_single">
        <label>App</label>
        <select name="app" id="appSel" onchange="syncPrefix()">
          <?php foreach ($apps as $k => $a): ?>
            <option value="<?= htmlspecialchars($k) ?>" data-prefix="<?= htmlspecialchars($a['default_prefix'] ?? strtoupper(substr($k,0,4))) ?>"><?= htmlspecialchars($a['label'] ?? $k) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="row">
          <div>
            <label>Prefix</label>
            <input name="prefix" id="prefixIn" value="FLFF" maxlength="20">
          </div>
          <div>
            <label>Số ngày (trống = vĩnh viễn)</label>
            <input type="number" name="days" min="1" placeholder="vd 7">
          </div>
        </div>
        <div class="row">
          <div>
            <label>Giới hạn HWID</label>
            <input type="number" name="hwid_limit" value="1" min="1" max="9999">
          </div>
          <div>
            <label>Ghi chú</label>
            <input name="note" placeholder="optional">
          </div>
        </div>
        <button type="submit" style="margin-top:16px;width:100%">Tạo 1 key</button>
      </form>
    </div>

    <div class="card">
      <h2>Tạo nhiều key</h2>
      <form method="post" action="actions.php">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create_batch">
        <label>App</label>
        <select name="app" onchange="this.form.prefix.value=this.options[this.selectedIndex].dataset.prefix||'KEY'">
          <?php foreach ($apps as $k => $a): ?>
            <option value="<?= htmlspecialchars($k) ?>" data-prefix="<?= htmlspecialchars($a['default_prefix'] ?? 'KEY') ?>"><?= htmlspecialchars($a['label'] ?? $k) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="row">
          <div><label>Prefix</label><input name="prefix" value="FLFF"></div>
          <div><label>Số lượng (1–50)</label><input type="number" name="count" value="5" min="1" max="50"></div>
        </div>
        <div class="row">
          <div><label>Số ngày</label><input type="number" name="days" min="1" placeholder="trống = ∞"></div>
          <div><label>HWID limit</label><input type="number" name="hwid_limit" value="1" min="1" max="9999"></div>
        </div>
        <label>Ghi chú</label>
        <input name="note">
        <button type="submit" style="margin-top:16px;width:100%">Tạo packet</button>
      </form>
    </div>
  </div>

  <div class="card">
    <h2>API POST (panel ngoài / Noa)</h2>
    <div class="api-box">
      <div><code>POST /api_create_key.php</code></div>
      <div>token · app · days · limit · count</div>
      <div>Ví dụ: <code>token=NOA_TOKEN_DOI_NGAY_123&amp;app=fakelag&amp;days=7&amp;limit=1</code></div>
      <div>Không cần sig/HMAC như app login.</div>
    </div>
  </div>

  <div class="card">
    <h2>Danh sách key (100 mới nhất)</h2>
    <div class="filters">
      <a href="?app=all" class="<?= $filterApp==='all'?'on':'' ?>">All</a>
      <?php foreach ($apps as $k => $a): ?>
        <a href="?app=<?= urlencode($k) ?>" class="<?= $filterApp===$k?'on':'' ?>"><?= htmlspecialchars($a['label'] ?? $k) ?></a>
      <?php endforeach; ?>
    </div>
    <div style="overflow:auto">
      <table>
        <thead>
          <tr>
            <th>ID</th><th>Key</th><th>App</th><th>HWID lim</th><th>Bound</th><th>Hết hạn</th><th>Status</th>
            <?php if (!$isReseller): ?><th>Thao tác</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($keys as $row): ?>
          <tr>
            <td><?= (int)$row['id'] ?></td>
            <td class="mono"><?= htmlspecialchars($row['key']) ?></td>
            <td><?= htmlspecialchars($row['app'] ?? '') ?></td>
            <td><?= (int)($row['hwid_limit'] ?? 1) ?></td>
            <td class="mono"><?= htmlspecialchars(substr($row['bound_hwid'] ?? '', 0, 12)) ?></td>
            <td><?= htmlspecialchars($row['expires_at'] ?? '∞') ?></td>
            <td class="st-<?= htmlspecialchars($row['status'] ?? '') ?>"><?= htmlspecialchars($row['status'] ?? '') ?></td>
            <?php if (!$isReseller): ?>
            <td class="actions">
              <form class="actions form" method="post" action="actions.php" onsubmit="return confirm('Xóa key?')">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <button type="submit" class="danger">Xóa</button>
              </form>
              <form class="actions form" method="post" action="actions.php">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <button type="submit" class="secondary">On/Off</button>
              </form>
              <form class="actions form" method="post" action="actions.php">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="extend">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <input type="hidden" name="days" value="7">
                <button type="submit" class="secondary">+7d</button>
              </form>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        <?php if (!$keys): ?><tr><td colspan="8" style="color:var(--muted)">Chưa có key</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script>
function syncPrefix(){
  const s=document.getElementById('appSel');
  const o=s.options[s.selectedIndex];
  document.getElementById('prefixIn').value=o.dataset.prefix||'KEY';
}
</script>
</body>
</html>
