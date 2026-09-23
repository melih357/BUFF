<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();
$user = require_auth();

try {
    $data = request_json();
    $streamId = (int)($data['streamId'] ?? 0);
    if ($streamId < 1) {
        json_response(['message' => 'Geçersiz yayın.'], 400);
    }
    $stream = v17_stream($streamId,$user);
    if (!$stream || !(bool)$stream['is_live']) {
        json_response(['message' => 'Yayın aktif değil.'], 404);
    }
    if ((int)$stream['user_id'] === (int)$user['id']) {
        json_response(['ok' => true, 'viewerCount' => active_viewer_count($streamId)]);
    }
    $stmt = db()->prepare('INSERT INTO stream_viewers (stream_id, user_id, last_seen_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE last_seen_at = NOW()');
    $stmt->execute([$streamId, (int)$user['id']]);
    v14_channel_loyalty_add((int)$user['id'],(int)$stream['user_id'],['watchSeconds'=>10,'xp'=>1]);
    v14_record_interaction((int)$user['id'],(int)$stream['user_id'],'stream',$streamId,'watch',1.0,10);
    $loyalty=v14_loyalty_snapshot((int)$user['id']);
    json_response(['ok' => true, 'viewerCount' => active_viewer_count($streamId),'loyalty'=>$loyalty]);
} catch (Throwable $e) {
    json_response(['message' => 'İzleyici durumu güncellenemedi.'], 500);
}
