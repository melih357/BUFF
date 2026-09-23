<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();

try {
    ensure_creator_stream_schema();
    $user = require_auth();
    $data = request_json();
    $delay = (int)($data['delaySeconds'] ?? -1);
    $allowed = [0,3,5,10,15,30];
    if (!in_array($delay, $allowed, true)) json_response(['message' => 'Geçersiz yayın gecikmesi.'], 400);

    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM streams WHERE user_id=? AND is_live=1 LIMIT 1');
    $stmt->execute([(int)$user['id']]);
    $streamId = (int)($stmt->fetchColumn() ?: 0);
    if ($streamId < 1) json_response(['message' => 'Aktif yayın bulunamadı.'], 404);

    $pdo->prepare('UPDATE streams SET delay_seconds=?, updated_at=NOW() WHERE id=?')->execute([$delay, $streamId]);
    try {
        if (db_table_exists('user_preferences')) {
            $pdo->prepare('INSERT INTO user_preferences (user_id,default_stream_delay) VALUES (?,?) ON DUPLICATE KEY UPDATE default_stream_delay=VALUES(default_stream_delay)')
                ->execute([(int)$user['id'], $delay]);
        }
    } catch (Throwable $e) {}

    json_response(['message' => 'Yayın gecikmesi güncellendi.', 'streamId' => $streamId, 'delaySeconds' => $delay]);
} catch (Throwable $e) {
    json_response(['message' => 'Yayın gecikmesi güncellenemedi: ' . $e->getMessage()], 500);
}
