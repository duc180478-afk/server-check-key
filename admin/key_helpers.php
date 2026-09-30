<?php
/** Timezone VN — tránh hiện UTC làm tưởng thiếu 24h */
function adminEnsureTz() {
    @date_default_timezone_set('Asia/Ho_Chi_Minh');
}

/** Tạo key: random suffix HOẶC đúng chuỗi custom */
function createKeyFlexible($prefixOrKey, $expiresDays, $hwidLimit, $note, $app, $mode, $createdBy = '') {
    adminEnsureTz();
    $pdo = Database::getConnection();
    $prefixOrKey = trim($prefixOrKey);
    if ($prefixOrKey === '') throw new InvalidArgumentException('Thiếu prefix/key');

    if ($mode === 'custom') {
        // Key đúng như nhập (không random)
        $key = $prefixOrKey;
        $prefix = preg_replace('/[^A-Za-z0-9].*$/', '', $key);
        if ($prefix === '') $prefix = 'KEY';
    } else {
        // prefix + random 12
        $prefix = preg_replace('/[^A-Za-z0-9]/', '', $prefixOrKey);
        if ($prefix === '') $prefix = 'KEY';
        $key = $prefix . '-' . generateRandomString(12);
    }

    // Trùng key?
    $chk = $pdo->prepare("SELECT id FROM keys WHERE key = ?");
    $chk->execute([$key]);
    if ($chk->fetch()) {
        throw new RuntimeException('Key đã tồn tại: ' . $key);
    }

    $createdAt = date('Y-m-d H:i:s');
    $expiresAt = null;
    if ($expiresDays !== null && (int)$expiresDays > 0) {
        $expiresAt = date('Y-m-d H:i:s', time() + (int)$expiresDays * 86400);
    }

    // Thử insert kèm created_by
    try {
        $stmt = $pdo->prepare("INSERT INTO keys (key, prefix, created_at, expires_at, hwid_limit, hwids, status, note, app, created_by)
            VALUES (?, ?, ?, ?, ?, '[]', 'active', ?, ?, ?)");
        $stmt->execute([$key, $prefix, $createdAt, $expiresAt, (int)$hwidLimit, $note, $app, $createdBy]);
    } catch (Throwable $e) {
        // Fallback không có created_by
        $stmt = $pdo->prepare("INSERT INTO keys (key, prefix, created_at, expires_at, hwid_limit, hwids, status, note, app)
            VALUES (?, ?, ?, ?, ?, '[]', 'active', ?, ?)");
        $stmt->execute([$key, $prefix, $createdAt, $expiresAt, (int)$hwidLimit, $note, $app]);
    }
    return $key;
}

function listKeysForUser($username, $role, $limit = 100) {
    $pdo = Database::getConnection();
    if ($role === 'admin') {
        $st = $pdo->query("SELECT * FROM keys ORDER BY id DESC LIMIT " . (int)$limit);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }
    $st = $pdo->prepare("SELECT * FROM keys WHERE created_by = ? ORDER BY id DESC LIMIT " . (int)$limit);
    $st->execute([$username]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
