<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();
$user = require_auth();

try {
    $data = request_json();
    $streamId = (int)($data['streamId'] ?? 0);
    $recipientUserId = (int)($data['recipientUserId'] ?? 0);
    $type = trim((string)($data['type'] ?? ''));
    $payload = $data['payload'] ?? null;

    if ($streamId < 1 || $recipientUserId < 1 || $type === '' || !is_array($payload)) {
        json_response(['message' => 'Geçersiz sinyal verisi.'], 400);
    }

    $stream = v17_stream($streamId,$user);
    if (!$stream) {
        json_response(['message' => 'Yayın bulunamadı.'], 404);
    }

    if((int)$stream['user_id']!==(int)$user['id'] && $recipientUserId!==(int)$stream['user_id'])json_response(['message'=>'Geçersiz alıcı.'],403);
    if((int)$stream['user_id']===(int)$user['id']&&!v17_can_view_profile((int)$stream['user_id'],fetch_user_by_id($recipientUserId)))json_response(['message'=>'İzleme izni yok.'],403);
    $stmt = db()->prepare('INSERT INTO signal_messages (stream_id, sender_user_id, recipient_user_id, type, payload) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$streamId, (int)$user['id'], $recipientUserId, text_substr($type, 0, 30), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    json_response(['ok' => true]);
} catch (Throwable $e) {
    json_response(['message' => 'Sinyal gönderilemedi.'], 500);
}
