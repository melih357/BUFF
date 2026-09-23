<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();
$user = require_auth();

try {
    $data = request_json();
    $displayName = trim((string)($data['displayName'] ?? ''));
    $bio = trim((string)($data['bio'] ?? ''));
    if ($displayName === '') {
        $displayName = (string)$user['display_name'];
    }
    $displayName = text_substr($displayName, 0, 60);
    $bio = text_substr($bio, 0, 255);

    $stmt = db()->prepare('UPDATE users SET display_name = ?, bio = ? WHERE id = ?');
    $stmt->execute([$displayName, $bio, (int)$user['id']]);
    $updated = fetch_user_by_id((int)$user['id']);
    json_response(['message' => 'Profil güncellendi.', 'user' => public_user($updated)]);
} catch (Throwable $e) {
    json_response(['message' => 'Profil güncellenemedi.'], 500);
}
