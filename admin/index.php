<?php
session_start();
require_once '../config.php';
require_once '../functions.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Bộ lọc danh sách theo app
 $appFilter = $_GET['app'] ?? 'all';
if (isset(SYSTEM_APPS[$appFilter])) {
    $keys = listKeysByApp(200, $appFilter);
} else {
    $appFilter = 'all';
    $keys = listKeys(200);
}

 $msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel - Quản lý Key</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .form-section { margin-bottom: 30px; }
        .filter-bar { margin-bottom: 15px; }
        .app-tag { font-weight: bold; }
    </style>
</head>
<body>
    <h1>Admin Panel</h1>
    <a href="logout.php">Đăng xuất</a>
    <?php if ($msg): ?>
        <p style="color: green;"><?= htmlspecialchars($msg) ?></p>
    <?php endif; ?>

    <div class="form-section">
        <h2>Tạo key đơn lẻ</h2>
        <form method="post" action="actions.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create_single">
            <label>App:
                <select name="app" onchange="updatePrefix(this)">
                    <?php foreach (SYSTEM_APPS as $aKey => $a): ?>
                        <option value="<?= $aKey ?>" data-prefix="<?= $a['default_prefix'] ?>"><?= $a['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </label><br>
            <label>Prefix (tùy ý): <input type="text" name="prefix" value="FLFF" maxlength="20"></label><br>
            <label>Số ngày hết hạn (bỏ trống nếu vĩnh viễn): <input type="number" name="days" min="1"></label><br>
            <label>Giới hạn thiết bị: <input type="number" name="hwid_limit" value="1" min="1"></label><br>
            <label>Ghi chú: <input type="text" name="note"></label><br>
            <button type="submit">Tạo key</button>
        </form>
    </div>

    <div class="form-section">
        <h2>Tạo nhiều key (Packet)</h2>
        <form method="post" action="actions.php">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create_batch">
            <label>App:
                <select name="app" onchange="updatePrefix(this)">
                    <?php foreach (SYSTEM_APPS as $aKey => $a): ?>
                        <option value="<?= $aKey ?>" data-prefix="<?= $a['default_prefix'] ?>"><?= $a['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </label><br>
            <label>Prefix (tùy ý): <input type="text" name="prefix" value="FLFF" maxlength="20"></label><br>
            <label>Số lượng: <input type="number" name="count" value="5" min="1" max="100"></label><br>
            <label>Số ngày hết hạn (bỏ trống nếu vĩnh viễn): <input type="number" name="days" min="1"></label><br>
            <label>Giới hạn thiết bị: <input type="number" name="hwid_limit" value="1" min="1"></label><br>
            <label>Ghi chú: <input type="text" name="note"></label><br>
            <button type="submit">Tạo nhiều key</button>
        </form>
    </div>

    <h2>Danh sách key</h2>

    <div class="filter-bar">
        Xem theo app:
        <select onchange="location='?app='+this.value;">
            <option value="all" <?= $appFilter === 'all' ? 'selected' : '' ?>>Tất cả</option>
            <?php foreach (SYSTEM_APPS as $aKey => $a): ?>
                <option value="<?= $aKey ?>" <?= $appFilter === $aKey ? 'selected' : '' ?>><?= $a['label'] ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <table>
        <tr>
            <th>ID</th>
            <th>Key</th>
            <th>App</th>
            <th>Prefix</th>
            <th>Ngày tạo</th>
            <th>Hết hạn</th>
            <th>HWID đã dùng</th>
            <th>Trạng thái</th>
            <th>Số lần dùng</th>
            <th>Hành động</th>
        </tr>
        <?php foreach ($keys as $k): ?>
        <tr>
            <td><?= (int)$k['id'] ?></td>
            <td><?= htmlspecialchars($k['key']) ?></td>
            <td><?= htmlspecialchars(getAppLabel($k['app'] ?? '')) ?></td>
            <td><?= htmlspecialchars($k['prefix']) ?></td>
            <td><?= htmlspecialchars($k['created_at']) ?></td>
            <td><?= htmlspecialchars($k['expires_at'] ?: 'Vĩnh viễn') ?></td>
            <td><?= htmlspecialchars($k['hwids']) ?></td>
            <td><?= htmlspecialchars($k['status']) ?></td>
            <td><?= (int)$k['times_used'] ?></td>
            <td>
                <form method="post" action="actions.php" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                    <input type="hidden" name="return_app" value="<?= htmlspecialchars($appFilter) ?>">
                    <button type="submit"><?= $k['status'] === 'active' ? 'Disable' : 'Enable' ?></button>
                </form>
                <form method="post" action="actions.php" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                    <input type="hidden" name="return_app" value="<?= htmlspecialchars($appFilter) ?>">
                    <button type="submit" onclick="return confirm('Xóa key này?')">Xóa</button>
                </form>
                <form method="post" action="actions.php" style="display:inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="extend">
                    <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
                    <input type="hidden" name="return_app" value="<?= htmlspecialchars($appFilter) ?>">
                    <input type="number" name="days" placeholder="Số ngày" min="1" required>
                    <button type="submit">Gia hạn</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

<script>
function updatePrefix(sel) {
    const opt = sel.options[sel.selectedIndex];
    sel.form.prefix.value = opt.dataset.prefix;
}
</script>
</body>
</html>