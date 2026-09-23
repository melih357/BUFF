<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';

$streamId = filter_var($_GET['stream_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$afterId = max(0, (int)($_GET['after_id'] ?? 0));
if (!$streamId) json_response(['messages' => []]);

try {
    $where = $afterId > 0 ? 'cm.stream_id = ? AND cm.id > ?' : 'cm.stream_id = ?';
    $orderLimit = $afterId > 0 ? 'ORDER BY cm.id ASC LIMIT 100' : 'ORDER BY cm.id DESC LIMIT 60';
    $sql = "SELECT cm.id, cm.message, cm.created_at, u.id AS user_id, u.username, u.display_name, u.avatar_url,
                   uf.is_og, uf.vip_until, uf.vip_tier, uf.chat_color, COALESCE(cl.level,1) AS loyalty_level,
                   cmm.message_type, cmm.sticker_code, cmm.chat_color AS message_color,
                   CASE WHEN s.user_id = u.id THEN 1 ELSE 0 END AS is_owner,
                   CASE WHEN sm.moderator_user_id IS NOT NULL THEN 1 ELSE 0 END AS is_moderator
            FROM chat_messages cm
            JOIN users u ON u.id = cm.user_id
            JOIN streams s ON s.id = cm.stream_id
            LEFT JOIN user_features uf ON uf.user_id = u.id
            LEFT JOIN channel_loyalty cl ON cl.user_id = u.id AND cl.channel_user_id = s.user_id
            LEFT JOIN chat_message_meta cmm ON cmm.message_id = cm.id
            LEFT JOIN stream_moderators sm ON sm.streamer_user_id = s.user_id AND sm.moderator_user_id = u.id
            WHERE {$where} {$orderLimit}";
    v17_stream((int)$streamId,current_user());
    $stmt = db()->prepare($sql);
    $stmt->execute($afterId > 0 ? [(int)$streamId, $afterId] : [(int)$streamId]);
    $rows = $stmt->fetchAll();
    $reset = false;
    if ($afterId > 0 && !$rows) {
        $maxStmt = db()->prepare('SELECT COALESCE(MAX(id), 0) AS max_id FROM chat_messages WHERE stream_id = ?');
        $maxStmt->execute([(int)$streamId]);
        $maxId = (int)($maxStmt->fetch()['max_id'] ?? 0);
        $reset = $maxId < $afterId;
    }
    if ($afterId === 0) $rows = array_reverse($rows);
    json_response(['reset' => $reset, 'messages' => array_map(static function(array $row): array {
        $vip = !empty($row['vip_until']) && strtotime((string)$row['vip_until']) > time();
        return [
            'id' => (int)$row['id'],
            'userId' => (int)$row['user_id'],
            'username' => (string)$row['username'],
            'displayName' => (string)$row['display_name'],
            'avatarUrl' => $row['avatar_url'] ?: null,
            'message' => (string)$row['message'],
            'messageType' => (string)($row['message_type'] ?: 'text'),
            'stickerCode' => $row['sticker_code'] ?: null,
            'chatColor' => $vip ? (string)($row['message_color'] ?: $row['chat_color'] ?: '#E7ECFF') : '#E7ECFF',
            'isOg' => (bool)$row['is_og'],
            'isVip' => $vip,
            'vipTier' => $vip ? (string)($row['vip_tier'] ?: 'plus') : '',
            'isOwner' => (bool)$row['is_owner'],
            'isModerator' => (bool)$row['is_moderator'],
            'loyaltyLevel' => max(1, (int)($row['loyalty_level'] ?? 1)),
            'createdAt' => (string)$row['created_at'],
        ];
    }, $rows)]);
} catch (Throwable $e) {
    json_response(['message' => 'Chat yüklenemedi.'], 500);
}
