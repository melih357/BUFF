<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
$user = require_auth();
$stmt = db()->prepare('SELECT n.*, a.display_name AS actor_name FROM notifications n LEFT JOIN users a ON a.id = n.actor_user_id WHERE n.user_id = ? ORDER BY n.id DESC LIMIT 25');
$stmt->execute([(int)$user['id']]);
$rows = $stmt->fetchAll();
$unread = 0;
$items = [];
foreach ($rows as $row) {
    if (!(bool)$row['is_read']) $unread++;
    $items[] = [
        'id' => (int)$row['id'],
        'type' => (string)$row['type'],
        'title' => (string)$row['title'],
        'body' => (string)$row['body'],
        'linkUrl' => $row['link_url'] ?: null,
        'isRead' => (bool)$row['is_read'],
        'actorName' => $row['actor_name'] ?: null,
        'createdAt' => (string)$row['created_at'],
    ];
}
json_response(['unread' => $unread, 'notifications' => $items]);
