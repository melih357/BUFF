<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
$user = current_user();
if (!$user) {
    json_response(['streams' => []]);
}
$sql = 'SELECT s.*, u.username, u.display_name, u.avatar_url,
           (SELECT COUNT(*) FROM stream_viewers sv WHERE sv.stream_id = s.id AND sv.last_seen_at >= (NOW() - INTERVAL 20 SECOND)) AS viewer_count_live
        FROM follows f
        JOIN streams s ON s.user_id = f.followed_user_id AND s.is_live = 1
        JOIN users u ON u.id = s.user_id
        WHERE f.follower_user_id = ?
        ORDER BY viewer_count_live DESC, s.started_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute([(int)$user['id']]);
json_response(['streams' => array_map('map_stream', $stmt->fetchAll())]);
