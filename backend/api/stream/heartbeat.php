<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();
$user = require_auth();

$pdo = db();
$stmt = $pdo->prepare('UPDATE streams SET updated_at = NOW() WHERE user_id = ? AND is_live = 1');
$stmt->execute([(int)$user['id']]);

$streamStmt = $pdo->prepare('SELECT id FROM streams WHERE user_id = ? AND is_live = 1 ORDER BY started_at DESC LIMIT 1');
$streamStmt->execute([(int)$user['id']]);
$streamId = (int)($streamStmt->fetchColumn() ?: 0);
$viewerCount = 0;
$chatterCount = 0;
if ($streamId > 0 && db_table_exists('stream_metric_samples')) {
    try {
        $viewerCount = active_viewer_count($streamId);
        $chatStmt = $pdo->prepare('SELECT COUNT(DISTINCT user_id) FROM chat_messages WHERE stream_id = ? AND created_at >= (NOW() - INTERVAL 60 SECOND)');
        $chatStmt->execute([$streamId]);
        $chatterCount = (int)$chatStmt->fetchColumn();
        $lastStmt = $pdo->prepare('SELECT sampled_at FROM stream_metric_samples WHERE stream_id = ? ORDER BY id DESC LIMIT 1');
        $lastStmt->execute([$streamId]);
        $lastSample = $lastStmt->fetchColumn();
        if (!$lastSample || strtotime((string)$lastSample) <= time() - 15) {
            $pdo->prepare('INSERT INTO stream_metric_samples (stream_id, creator_user_id, viewer_count, chatter_count, sampled_at) VALUES (?, ?, ?, ?, NOW())')
                ->execute([$streamId, (int)$user['id'], $viewerCount, $chatterCount]);
        }
    } catch (Throwable $e) { error_log('[BUFF stream metrics] '.$e->getMessage()); }
}
json_response(['ok' => true, 'streamId' => $streamId, 'viewerCount' => $viewerCount, 'chatterCount' => $chatterCount]);
