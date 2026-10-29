<?php
/**
 * admin/mods.php — Quản lý danh sách Mod Skin FF
 * Thêm / Sửa / Xoá các gói mod (Name, Pic, Download, Package, Category)
 */

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/admin_auth.php';
adminRequireLogin();

@date_default_timezone_set('Asia/Ho_Chi_Minh');

$pdo = Database::getConnection();
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

// Tạo bảng nếu chưa có
if ($driver === 'pgsql') {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mod_skins (
            id SERIAL PRIMARY KEY,
            \"Name\" TEXT NOT NULL,
            \"Pic\" TEXT NOT NULL DEFAULT '',
            \"Download\" TEXT NOT NULL DEFAULT '',
            \"Package\" TEXT NOT NULL DEFAULT 'ffmax',
            \"Category\" TEXT NOT NULL DEFAULT 'trang-phuc',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
} else {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mod_skins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            Name TEXT NOT NULL,
            Pic TEXT NOT NULL DEFAULT '',
            Download TEXT NOT NULL DEFAULT '',
            Package TEXT NOT NULL DEFAULT 'ffmax',
            Category TEXT NOT NULL DEFAULT 'trang-phuc',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

$flash = '';
$editRow = null;

// ===== Xử lý POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $flash = 'CSRF token không hợp lệ.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add' || $action === 'edit') {
            $id       = (int)($_POST['id'] ?? 0);
            $name     = trim($_POST['Name'] ?? '');
            $pic      = trim($_POST['Pic'] ?? '');
            $download = trim($_POST['Download'] ?? '');
            $package  = in_array($_POST['Package'] ?? '', ['ffmax', 'ffth'], true) ? $_POST['Package'] : 'ffmax';
            $category = in_array($_POST['Category'] ?? '', ['trang-phuc', 'skin-sung', 'nhan-vat', 'fix-lag'], true)
                        ? $_POST['Category'] : 'trang-phuc';

            if ($name === '' || $download === '') {
                $flash = 'Name và Download bắt buộc.';
            } else {
                if ($action === 'add') {
                    if ($driver === 'pgsql') {
                        $stmt = $pdo->prepare('INSERT INTO mod_skins ("Name", "Pic", "Download", "Package", "Category") VALUES (?,?,?,?,?)');
                    } else {
                        $stmt = $pdo->prepare('INSERT INTO mod_skins (Name, Pic, Download, Package, Category) VALUES (?,?,?,?,?)');
                    }
                    $stmt->execute([$name, $pic, $download, $package, $category]);
                    $flash = 'Đã thêm mod thành công.';
                } else {
                    if ($driver === 'pgsql') {
                        $stmt = $pdo->prepare('UPDATE mod_skins SET "Name"=?, "Pic"=?, "Download"=?, "Package"=?, "Category"=? WHERE id=?');
                    } else {
                        $stmt = $pdo->prepare('UPDATE mod_skins SET Name=?, Pic=?, Download=?, Package=?, Category=? WHERE id=?');
                    }
                    $stmt->execute([$name, $pic, $download, $package, $category, $id]);
                    $flash = 'Đã cập nhật mod #' . $id;
                }
            }
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $pdo->prepare('DELETE FROM mod_skins WHERE id = ?')->execute([$id]);
                $flash = 'Đã xoá mod #' . $id;
            }
        }

        if ($action === 'edit_form') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $st = $pdo->prepare('SELECT * FROM mod_skins WHERE id = ?');
                $st->execute([$id]);
                $editRow = $st->fetch(PDO::FETCH_ASSOC);
            }
        }
    }
}

// Lấy danh sách
if ($driver === 'pgsql') {
    $mods = $pdo->query('SELECT * FROM mod_skins ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
} else {
    $mods = $pdo->query('SELECT * FROM mod_skins ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
}

$isEdit = is_array($editRow);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mod Skin Manager — KEY.SYS</title>
<link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,wght@0,400;0,500;0,600&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{--paper:#F6F4EE;--card:#FCFBF7;--ink:#191B17;--ink-soft:#4C4A42;--grid:#DEDAD0;--accent:#2B4FD8;--accent-ink:#F6F4EE;--amber:#C4780A;--line:#191B17;--danger:#c0392b;color-scheme:light;}
@media(prefers-color-scheme:dark){
  :root{--paper:#15160F;--card:#1C1D15;--ink:#EDEAE0;--ink-soft:#A6A295;--grid:#2B2C22;--accent:#7D95F5;--accent-ink:#15160F;--amber:#E0A03E;--line:#EDEAE0;--danger:#e74c3c;color-scheme:dark;}
}
*{box-sizing:border-box;}
body{margin:0;background:var(--paper);color:var(--ink);font-family:"Newsreader",serif;
  background-image:linear-gradient(var(--grid) 1px,transparent 1px),linear-gradient(90deg,var(--grid) 1px,transparent 1px);
  background-size:28px 28px;}
code{font-family:"IBM Plex Mono",monospace;font-size:.95em;}
.bar{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:20px 6vw;border-bottom:1.3px solid var(--line);flex-wrap:wrap;}
.bar a{color:var(--ink);text-decoration:none;font-family:"IBM Plex Mono",monospace;font-size:12px;}
.bar a:hover{color:var(--accent);}
.wrap{max-width:1100px;margin:0 auto;padding:28px 6vw 60px;}
.card{background:var(--card);border:1.3px solid var(--line);margin-bottom:24px;position:relative;overflow:hidden;}
.chead{padding:16px 22px;border-bottom:1.3px solid var(--line);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;}
.chead h2{font-size:19px;font-weight:500;margin:0;}
.cbody{padding:18px 22px;}
.f{display:flex;flex-direction:column;gap:6px;margin-bottom:12px;}
label{font-family:"IBM Plex Mono",monospace;font-size:10px;color:var(--ink-soft);text-transform:uppercase;letter-spacing:.06em;}
input,select,textarea{width:100%;border:1.3px solid var(--line);background:var(--paper);color:var(--ink);padding:10px 12px;font-family:"IBM Plex Mono",monospace;font-size:13px;}
input:focus,select:focus,textarea:focus{outline:2px solid var(--accent);outline-offset:1px;}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
@media(max-width:640px){.row{grid-template-columns:1fr;}}
.btn{border:1.3px solid var(--line);background:var(--accent);color:var(--accent-ink);padding:10px 18px;font-family:"IBM Plex Mono",monospace;font-size:12.5px;cursor:pointer;}
.btn:hover{filter:brightness(1.06);}
.btn.ghost{background:var(--paper);color:var(--ink);}
.btn.danger{background:var(--paper);color:var(--danger);border-color:var(--danger);}
.btn.sm{padding:5px 10px;font-size:11px;}
.flash{padding:12px 16px;border:1.3px solid var(--accent);background:color-mix(in srgb,var(--accent) 12%,var(--paper));margin-bottom:18px;font-family:"IBM Plex Mono",monospace;font-size:12.5px;}
table{width:100%;border-collapse:collapse;font-size:13px;}
th,td{border-bottom:1px solid var(--grid);padding:10px 8px;text-align:left;vertical-align:top;}
th{font-family:"IBM Plex Mono",monospace;font-size:10px;text-transform:uppercase;color:var(--ink-soft);letter-spacing:.04em;}
.thumb{width:48px;height:48px;object-fit:cover;border:1px solid var(--grid);border-radius:4px;}
.truncate{max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block;}
.actions{display:flex;gap:6px;flex-wrap:wrap;}
.badge{font-family:"IBM Plex Mono",monospace;font-size:10px;padding:2px 7px;border:1px solid var(--amber);color:var(--amber);}
</style>
</head>
<body>
<div class="bar">
  <div>
    <strong style="font-size:18px;">KEY.SYS</strong>
    <span style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:var(--ink-soft);margin-left:10px;">Mod Skin Manager</span>
  </div>
  <div style="display:flex;gap:16px;flex-wrap:wrap;">
    <a href="index.php">← Key Panel</a>
    <a href="config.php">Config</a>
    <a href="logout.php">Logout</a>
  </div>
</div>

<div class="wrap">
  <?php if ($flash): ?>
    <div class="flash"><?= htmlspecialchars($flash) ?></div>
  <?php endif; ?>

  <div class="card">
    <div class="chead">
      <h2><?= $isEdit ? 'Sửa Mod #' . (int)$editRow['id'] : 'Thêm Mod mới' ?></h2>
      <?php if ($isEdit): ?>
        <a href="mods.php" class="btn ghost sm">Huỷ sửa</a>
      <?php endif; ?>
    </div>
    <div class="cbody">
      <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="<?= $isEdit ? 'edit' : 'add' ?>">
        <?php if ($isEdit): ?>
          <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>">
        <?php endif; ?>

        <div class="f">
          <label>Name (tên hiển thị trên App)</label>
          <input name="Name" required placeholder="Set Thỏ Neon Huyền Thoại"
                 value="<?= htmlspecialchars($isEdit ? ($editRow['Name'] ?? $editRow['name'] ?? '') : '') ?>">
        </div>

        <div class="f">
          <label>Pic (URL ảnh minh họa)</label>
          <input name="Pic" placeholder="https://..."
                 value="<?= htmlspecialchars($isEdit ? ($editRow['Pic'] ?? $editRow['pic'] ?? '') : '') ?>">
        </div>

        <div class="f">
          <label>Download (URL MediaFire / file .zip)</label>
          <input name="Download" required placeholder="https://www.mediafire.com/file/xxxx/mod.zip/file"
                 value="<?= htmlspecialchars($isEdit ? ($editRow['Download'] ?? $editRow['download'] ?? '') : '') ?>">
        </div>

        <div class="row">
          <div class="f">
            <label>Package</label>
            <?php $pkg = $isEdit ? ($editRow['Package'] ?? $editRow['package'] ?? 'ffmax') : 'ffmax'; ?>
            <select name="Package">
              <option value="ffmax" <?= $pkg === 'ffmax' ? 'selected' : '' ?>>ffmax — FF Max</option>
              <option value="ffth"  <?= $pkg === 'ffth'  ? 'selected' : '' ?>>ffth — FF Thường</option>
            </select>
          </div>
          <div class="f">
            <label>Category</label>
            <?php $cat = $isEdit ? ($editRow['Category'] ?? $editRow['category'] ?? 'trang-phuc') : 'trang-phuc'; ?>
            <select name="Category">
              <option value="trang-phuc" <?= $cat === 'trang-phuc' ? 'selected' : '' ?>>Trang phục</option>
              <option value="skin-sung"  <?= $cat === 'skin-sung'  ? 'selected' : '' ?>>Skin súng</option>
              <option value="nhan-vat"   <?= $cat === 'nhan-vat'   ? 'selected' : '' ?>>Nhân vật</option>
              <option value="fix-lag"    <?= $cat === 'fix-lag'    ? 'selected' : '' ?>>Fix lag</option>
            </select>
          </div>
        </div>

        <button type="submit" class="btn"><?= $isEdit ? 'Lưu thay đổi' : '+ Thêm Mod' ?></button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="chead">
      <h2>Danh sách Mod (<?= count($mods) ?>)</h2>
      <span class="badge">api_mods.php</span>
    </div>
    <div class="cbody" style="overflow-x:auto;">
      <?php if (empty($mods)): ?>
        <p style="color:var(--ink-soft);font-family:'IBM Plex Mono',monospace;font-size:13px;">Chưa có mod nào. Hãy thêm ở form phía trên.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Ảnh</th>
              <th>Name</th>
              <th>Package</th>
              <th>Category</th>
              <th>Download</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($mods as $m):
            $id  = (int)$m['id'];
            $nm  = $m['Name'] ?? $m['name'] ?? '';
            $pic = $m['Pic'] ?? $m['pic'] ?? '';
            $dl  = $m['Download'] ?? $m['download'] ?? '';
            $pkg = $m['Package'] ?? $m['package'] ?? '';
            $cat = $m['Category'] ?? $m['category'] ?? '';
          ?>
            <tr>
              <td><?= $id ?></td>
              <td>
                <?php if ($pic): ?>
                  <img class="thumb" src="<?= htmlspecialchars($pic) ?>" alt="" onerror="this.style.display='none'">
                <?php else: ?>
                  <span style="color:var(--ink-soft);">—</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($nm) ?></td>
              <td><code><?= htmlspecialchars($pkg) ?></code></td>
              <td><code><?= htmlspecialchars($cat) ?></code></td>
              <td><span class="truncate" title="<?= htmlspecialchars($dl) ?>"><?= htmlspecialchars($dl) ?></span></td>
              <td>
                <div class="actions">
                  <form method="post" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="edit_form">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button type="submit" class="btn ghost sm">Sửa</button>
                  </form>
                  <form method="post" style="display:inline;" onsubmit="return confirm('Xoá mod #<?= $id ?>?');">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button type="submit" class="btn danger sm">Xoá</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
