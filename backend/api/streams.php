<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

try {
    $streamId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($streamId) {
        $row = v17_stream((int)$streamId,current_user());
        if (!$row) {
            json_response(['message' => 'Yayın bulunamadı.'], 404);
        }
        json_response(['stream' => map_stream($row)]);
    }

    $sql = 'SELECT s.*, u.username, u.display_name, u.avatar_url,
               (SELECT COUNT(*) FROM stream_viewers sv WHERE sv.stream_id = s.id AND sv.last_seen_at >= (NOW() - INTERVAL 20 SECOND)) AS viewer_count_live
            FROM streams s
            JOIN users u ON u.id = s.user_id
            WHERE s.is_live = 1
            ORDER BY viewer_count_live DESC, s.started_at DESC';
    $sql=str_replace('WHERE s.is_live = 1','WHERE s.is_live = 1 AND '.v17_profile_sql('s.user_id',(int)(current_user()['id']??0)),$sql);
    $rows = db()->query($sql)->fetchAll();
    json_response(['streams' => array_map('map_stream', $rows)]);
} catch (Throwable $e) {
    json_response(['message' => 'Yayınlar alınamadı. Önce install.php çalıştırın.'], 500);
}
