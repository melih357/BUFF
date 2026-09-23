<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
$user = require_auth();

try {
    $streamId = filter_var($_GET['stream_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$streamId) {
        json_response(['messages' => []]);
    }

    v17_stream((int)$streamId,current_user());
    $stmt = db()->prepare('SELECT * FROM signal_messages WHERE stream_id = ? AND recipient_user_id = ? AND delivered = 0 ORDER BY id ASC LIMIT 40');
    $stmt->execute([(int)$streamId, (int)$user['id']]);
    $rows = $stmt->fetchAll();
    if ($rows) {
        $ids = array_map(static fn(array $r): int => (int)$r['id'], $rows);
        $idList = implode(',', array_map('intval', $ids));
        db()->exec("UPDATE signal_messages SET delivered = 1 WHERE id IN ({$idList})");
    }
    json_response(['messages' => array_map(static function(array $row): array {
        return [
            'id' => (int)$row['id'],
            'streamId' => (int)$row['stream_id'],
            'senderUserId' => (int)$row['sender_user_id'],
            'recipientUserId' => (int)$row['recipient_user_id'],
            'type' => (string)$row['type'],
            'payload' => json_decode((string)$row['payload'], true) ?: [],
            'createdAt' => (string)$row['created_at'],
        ];
    }, $rows)]);
} catch (Throwable $e) {
    json_response(['message' => 'Sinyaller alınamadı.'], 500);
}
