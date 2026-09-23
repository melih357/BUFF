<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
$streamId = filter_var($_GET['stream_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$afterId = max(0, (int)($_GET['after_id'] ?? 0));
if (!$streamId) {
    json_response(['events' => []]);
}

v17_stream((int)$streamId,current_user());
if ($afterId > 0) {
    $stmt = db()->prepare('SELECT ge.id, ge.quantity, ge.total_price, ge.audio_url, ge.audio_name, ge.created_at, gc.id AS gift_id, gc.name, gc.icon, u.display_name AS sender_name, u.avatar_url AS sender_avatar
                           FROM gift_events ge
                           JOIN gift_catalog gc ON gc.id = ge.gift_id
                           JOIN users u ON u.id = ge.sender_user_id
                           WHERE ge.recording_id IS NULL AND ge.stream_id = ? AND ge.id > ?
                           ORDER BY ge.id ASC LIMIT 50');
    $stmt->execute([(int)$streamId, $afterId]);
} else {
    $stmt = db()->prepare('SELECT ge.id, ge.quantity, ge.total_price, ge.audio_url, ge.audio_name, ge.created_at, gc.id AS gift_id, gc.name, gc.icon, u.display_name AS sender_name, u.avatar_url AS sender_avatar
                           FROM gift_events ge
                           JOIN gift_catalog gc ON gc.id = ge.gift_id
                           JOIN users u ON u.id = ge.sender_user_id
                           WHERE ge.recording_id IS NULL AND ge.stream_id = ?
                           ORDER BY ge.id DESC LIMIT 30');
    $stmt->execute([(int)$streamId]);
}
$rows = $stmt->fetchAll();
json_response(['events' => array_map(static function(array $row): array {
    return [
        'id' => (int)$row['id'],
        'eventId' => (int)$row['id'],
        'kind' => 'stream',
        'giftId' => (int)$row['gift_id'],
        'quantity' => (int)$row['quantity'],
        'totalPrice' => (int)$row['total_price'],
        'createdAt' => (string)$row['created_at'],
        'giftName' => (string)$row['name'],
        'giftIcon' => (string)$row['icon'],
        'senderName' => (string)$row['sender_name'],
        'senderAvatar' => $row['sender_avatar'] ?: null,
        'audioUrl' => $row['audio_url'] ?: null,
        'audioName' => $row['audio_name'] ?: null,
    ];
}, $rows)]);
