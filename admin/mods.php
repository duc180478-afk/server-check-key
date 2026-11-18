<?php
/**
 * admin/mods.php — Trang Quản Trị Mod Skin làm việc trực tiếp trên file ĐÃ MÃ HOÁ `data.chmr`
 * Tuyệt đối KHÔNG lưu file data.json dạng rõ (plaintext) trên Server hay GitHub repo!
 *
 * Cơ chế hoạt động:
 *   1. Khi Admin mở trang này: Server đọc file nhị phân `../data.chmr` và giải mã ngay trên RAM
 *      bằng `ChimeraServerCompiler::decryptEnvelopeToString()`.
 *   2. Khi Admin Thêm / Sửa / Xoá bất kỳ bản Mod hoặc URL MediaFire nào:
 *      Server lập tức biên dịch lại toàn bộ danh sách bằng `ChimeraServerCompiler::compileStringToEnvelope()`
 *      (sinh bảng 8 V-Opcode đa hình mới + 16-byte Server Key Share mới) và ghi đè vào `../data.chmr`!
 */

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../chimera_compiler.php';
require_once __DIR__ . '/admin_auth.php';
adminRequireLogin();

@date_default_timezone_set('Asia/Ho_Chi_Minh');

$dataChmrPath = __DIR__ . '/../data.chmr';
$legacyJsonPath = __DIR__ . '/../data.json';

function loadServerEncryptedChmr(string $chmrPath, string $legacyJsonPath): array {
    if (file_exists($chmrPath)) {
        $envBin = file_get_contents($chmrPath);
        if ($envBin !== false && strlen($envBin) > 24) {
            $plainJson = ChimeraServerCompiler::decryptEnvelopeToString($envBin);
            $arr = json_decode($plainJson, true);
            if (is_array($arr)) {
                return isset($arr['items']) && is_array($arr['items']) ? $arr['items'] : $arr;
            }
        }
    }
    // Tự động chuyển đổi từ data.json sang data.chmr và xoá data.json nếu lần đầu nâng cấp
    if (file_exists($legacyJsonPath)) {
        $raw = file_get_contents($legacyJsonPath);
        $arr = json_decode($raw, true);
        if (is_array($arr)) {
            $items = isset($arr['items']) && is_array($arr['items']) ? $arr['items'] : $arr;
            saveServerEncryptedChmr($chmrPath, $items);
            @unlink($legacyJsonPath);
            return $items;
        }
    }
    return [];
}

function saveServerEncryptedChmr(string $chmrPath, array $items): bool {
    $compactJson = json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $envelopeBin = ChimeraServerCompiler::compileStringToEnvelope($compactJson);
    return file_put_contents($chmrPath, $envelopeBin, LOCK_EX) !== false;
}

$flash = '';
$editItem = null;
$items = loadServerEncryptedChmr($dataChmrPath, $legacyJsonPath);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $flash = 'CSRF token không hợp lệ.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'add' || $action === 'edit') {
            $id          = trim($_POST['id'] ?? '');
            $title       = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? 'Mod Skin VIP');
            $image_url   = trim($_POST['image_url'] ?? '');
            $file_url    = trim($_POST['file_url'] ?? '');
            $game_type   = in_array($_POST['game_type'] ?? '', ['com.dts.freefireth', 'com.dts.freefiremax'], true)
                           ? $_POST['game_type'] : 'com.dts.freefiremax';
            $skin_type   = in_array($_POST['skin_type'] ?? '', ['character', 'gun', 'toy', 'fixlag'], true)
                           ? $_POST['skin_type'] : 'character';

            if ($title === '' || $file_url === '') {
                $flash = 'Tên Mod và Link tải (file_url) là bắt buộc.';
            } else {
                if ($action === 'add') {
                    $maxId = 100;
                    foreach ($items as $it) {
                        $num = (int)($it['id'] ?? 0);
                        if ($num > $maxId) $maxId = $num;
                    }
                    $newId = (string)($maxId + 1);
                    array_unshift($items, [
                        'id'          => $newId,
                        'title'       => $title,
                        'description' => $description,
                        'game_type'   => $game_type,
                        'skin_type'   => $skin_type,
                        'file_url'    => $file_url,
                        'image_url'   => $image_url,
                    ]);
                    saveServerEncryptedChmr($dataChmrPath, $items);
                    $flash = "Đã thêm Mod #{$newId} và mã hoá lại file data.chmr bằng Chimera-VM!";
                } else {
                    foreach ($items as &$it) {
                        if ((string)($it['id'] ?? '') === $id) {
                            $it['title']       = $title;
                            $it['description'] = $description;
                            $it['game_type']   = $game_type;
                            $it['skin_type']   = $skin_type;
                            $it['file_url']    = $file_url;
                            $it['image_url']   = $image_url;
                            break;
                        }
                    }
                    unset($it);
                    saveServerEncryptedChmr($dataChmrPath, $items);
                    $flash = "Đã cập nhật Mod #{$id} và mã hoá lại file data.chmr bằng Chimera-VM!";
                }
            }
        }

        if ($action === 'delete') {
            $id = trim($_POST['id'] ?? '');
            $items = array_values(array_filter($items, function ($it) use ($id) {
                return (string)($it['id'] ?? '') !== $id;
            }));
            saveServerEncryptedChmr($dataChmrPath, $items);
            $flash = "Đã xoá Mod #{$id} và mã hoá lại file data.chmr!";
        }

        if ($action === 'reencrypt') {
            saveServerEncryptedChmr($dataChmrPath, $items);
            $flash = "Đã đảo lại bảng Opcode Map & Server Key Share mới cho file data.chmr!";
        }

        if ($action === 'edit_form') {
            $id = trim($_POST['id'] ?? '');
            foreach ($items as $it) {
                if ((string)($it['id'] ?? '') === $id) {
                    $editItem = $it;
                    break;
                }
            }
        }
    }
}

$isEdit = is_array($editItem);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Quản Trị data.chmr (Chimera-VM Encrypted) — KEY.SYS</title>
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
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
@media(max-width:640px){.row{grid-template-columns:1fr;}}
.btn{border:1.3px solid var(--line);background:var(--accent);color:var(--accent-ink);padding:10px 18px;font-family:"IBM Plex Mono",monospace;font-size:12.5px;cursor:pointer;}
.btn.ghost{background:var(--paper);color:var(--ink);}
.btn.danger{background:var(--paper);color:var(--danger);border-color:var(--danger);}
.btn.sm{padding:5px 10px;font-size:11px;}
.flash{padding:12px 16px;border:1.3px solid var(--accent);margin-bottom:18px;font-family:"IBM Plex Mono",monospace;font-size:12.5px;}
table{width:100%;border-collapse:collapse;font-size:13px;}
th,td{border-bottom:1px solid var(--grid);padding:10px 8px;text-align:left;vertical-align:top;}
th{font-family:"IBM Plex Mono",monospace;font-size:10px;text-transform:uppercase;color:var(--ink-soft);}
.thumb{width:48px;height:48px;object-fit:cover;border:1px solid var(--grid);border-radius:4px;}
.truncate{max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:inline-block;}
.actions{display:flex;gap:6px;flex-wrap:wrap;}
.badge{font-family:"IBM Plex Mono",monospace;font-size:10px;padding:2px 7px;border:1px solid var(--amber);color:var(--amber);}
</style>
</head>
<body>
<div class="bar">
  <div>
    <strong style="font-size:18px;">KEY.SYS</strong>
    <span style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:var(--ink-soft);margin-left:10px;">Quản Trị data.chmr · Lưu Trữ Mã Hoá Chimera-VM Trên Server</span>
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
      <h2><?= $isEdit ? 'Sửa Mod #' . htmlspecialchars($editItem['id']) : 'Thêm Mod mới vào file mã hoá data.chmr' ?></h2>
      <form method="post" style="display:inline;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="reencrypt">
        <button type="submit" class="btn ghost sm">Đảo lại Opcode Map & Key Share (.chmr)</button>
      </form>
    </div>
    <div class="cbody">
      <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="<?= $isEdit ? 'edit' : 'add' ?>">
        <?php if ($isEdit): ?>
          <input type="hidden" name="id" value="<?= htmlspecialchars($editItem['id']) ?>">
        <?php endif; ?>

        <div class="row">
          <div class="f">
            <label>Title (Tên hiển thị trên App)</label>
            <input name="title" required placeholder="Skin Bá Sàn FF / AK Rồng Xanh"
                   value="<?= htmlspecialchars($isEdit ? ($editItem['title'] ?? '') : '') ?>">
          </div>
          <div class="f">
            <label>Description (Mô tả nhân vật / vũ khí)</label>
            <input name="description" required placeholder="Nhân Vật Alok Thức Tỉnh"
                   value="<?= htmlspecialchars($isEdit ? ($editItem['description'] ?? '') : '') ?>">
          </div>
        </div>

        <div class="f">
          <label>file_url (Link tải MediaFire .zip)</label>
          <input name="file_url" required placeholder="https://www.mediafire.com/file/xxxx/mod.zip/file"
                 value="<?= htmlspecialchars($isEdit ? ($editItem['file_url'] ?? '') : '') ?>">
        </div>

        <div class="f">
          <label>image_url (Link ảnh preview)</label>
          <input name="image_url" placeholder="https://iili.io/xxxx.jpg"
                 value="<?= htmlspecialchars($isEdit ? ($editItem['image_url'] ?? '') : '') ?>">
        </div>

        <div class="row">
          <div class="f">
            <label>game_type (Phiên bản Free Fire)</label>
            <?php $gt = $isEdit ? ($editItem['game_type'] ?? 'com.dts.freefiremax') : 'com.dts.freefiremax'; ?>
            <select name="game_type">
              <option value="com.dts.freefireth"  <?= $gt === 'com.dts.freefireth'  ? 'selected' : '' ?>>com.dts.freefireth — FF Thường</option>
              <option value="com.dts.freefiremax" <?= $gt === 'com.dts.freefiremax' ? 'selected' : '' ?>>com.dts.freefiremax — FF Max</option>
              <option value="com.garena.ffvn"    <?= $gt === 'com.garena.ffvn'    ? 'selected' : '' ?>>com.garena.ffvn — FF VN</option>
            </select>
          </div>
          <div class="f">
            <label>skin_type (Danh mục trên App)</label>
            <?php $st = $isEdit ? ($editItem['skin_type'] ?? 'character') : 'character'; ?>
            <select name="skin_type">
              <option value="character" <?= $st === 'character' ? 'selected' : '' ?>>character — Nhân vật / Trang phục</option>
              <option value="gun"       <?= $st === 'gun'       ? 'selected' : '' ?>>gun — Skin súng</option>
              <option value="toy"       <?= $st === 'toy'       ? 'selected' : '' ?>>toy — Định vị / Vật phẩm</option>
              <option value="fixlag"    <?= $st === 'fixlag'    ? 'selected' : '' ?>>fixlag — Fix lag</option>
            </select>
          </div>
        </div>

        <button type="submit" class="btn"><?= $isEdit ? 'Lưu & Mã hoá vào data.chmr' : '+ Thêm & Mã hoá vào data.chmr' ?></button>
        <?php if ($isEdit): ?>
          <a href="mods.php" class="btn ghost">Huỷ sửa</a>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="chead">
      <h2>Danh sách giải mã từ data.chmr trên Server (<?= count($items) ?> bản mod)</h2>
      <span class="badge">Zero Plaintext JSON on Server</span>
    </div>
    <div class="cbody" style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Ảnh</th>
            <th>Title & Mô tả</th>
            <th>Game Type</th>
            <th>Category</th>
            <th>Link MediaFire</th>
            <th>Thao tác</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $m):
          $id   = (string)($m['id'] ?? '');
          $nm   = (string)($m['title'] ?? '');
          $desc = (string)($m['description'] ?? '');
          $pic  = (string)($m['image_url'] ?? '');
          $dl   = (string)($m['file_url'] ?? '');
          $pkg  = (string)($m['game_type'] ?? '');
          $cat  = (string)($m['skin_type'] ?? '');
        ?>
          <tr>
            <td><code><?= htmlspecialchars($id) ?></code></td>
            <td>
              <?php if ($pic): ?>
                <img class="thumb" src="<?= htmlspecialchars($pic) ?>" alt="" onerror="this.style.display='none'">
              <?php else: ?>
                <span>—</span>
              <?php endif; ?>
            </td>
            <td>
              <strong><?= htmlspecialchars($nm) ?></strong><br>
              <small style="color:var(--ink-soft);"><?= htmlspecialchars($desc) ?></small>
            </td>
            <td><code><?= htmlspecialchars($pkg) ?></code></td>
            <td><code><?= htmlspecialchars($cat) ?></code></td>
            <td><span class="truncate" title="<?= htmlspecialchars($dl) ?>"><?= htmlspecialchars($dl) ?></span></td>
            <td>
              <div class="actions">
                <form method="post" style="display:inline;">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="edit_form">
                  <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                  <button type="submit" class="btn ghost sm">Sửa</button>
                </form>
                <form method="post" style="display:inline;" onsubmit="return confirm('Xoá mod #<?= htmlspecialchars($id) ?>?');">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                  <button type="submit" class="btn danger sm">Xoá</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
