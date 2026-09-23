<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();
$user = require_auth();
$userId = (int)$user['id'];

try {
    $data = request_json();
    $displayName = trim((string)($data['displayName'] ?? $user['display_name']));
    $bio = trim((string)($data['bio'] ?? $user['bio'] ?? ''));
    $username = trim((string)($data['username'] ?? $user['username']));

    if ($displayName === '') $displayName = (string)$user['display_name'];
    if (text_len($displayName) > 60) $displayName = text_substr($displayName, 0, 60);
    $bio = text_substr($bio, 0, 255);

    if (!preg_match('/^[a-zA-Z0-9_]{3,24}$/', $username)) {
        json_response(['message' => 'Kullanıcı adı 3-24 karakter olmalı; harf, rakam ve alt çizgi kullanılabilir.'], 400);
    }

    $pdo = db();
    $pdo->beginTransaction();
    $usernameChanged = $username !== (string)$user['username'];
    if ($usernameChanged) {
        if (!empty($user['username_changed_at']) && strtotime((string)$user['username_changed_at']) > strtotime('-30 days')) {
            $next = date('d.m.Y H:i', strtotime((string)$user['username_changed_at'] . ' +30 days'));
            $pdo->rollBack();
            json_response(['message' => 'Kullanıcı adını 30 günde bir değiştirebilirsin. Sonraki değişiklik: ' . $next], 409);
        }
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1');
        $stmt->execute([$username, $userId]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            json_response(['message' => 'Bu kullanıcı adı başka bir hesap tarafından kullanılıyor.'], 409);
        }
        $pdo->prepare('INSERT INTO username_change_history (user_id, old_username, new_username) VALUES (?, ?, ?)')
            ->execute([$userId, (string)$user['username'], $username]);
        $pdo->prepare('UPDATE users SET username = ?, username_changed_at = NOW(), display_name = ?, bio = ? WHERE id = ?')
            ->execute([$username, $displayName, $bio, $userId]);
    } else {
        $pdo->prepare('UPDATE users SET display_name = ?, bio = ? WHERE id = ?')->execute([$displayName, $bio, $userId]);
    }
    $pdo->commit();
    $updated = fetch_user_by_id($userId);
    json_response(['message' => 'Profil ve biyografi kaydedildi.', 'user' => public_user($updated)]);
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    json_response(['message' => 'Profil ayarları kaydedilemedi.'], 500);
}
