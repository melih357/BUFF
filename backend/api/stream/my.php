<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
$user = require_auth();
$stmt = db()->prepare('SELECT s.*, u.username, u.display_name, u.avatar_url FROM streams s JOIN users u ON u.id = s.user_id WHERE s.user_id = ? LIMIT 1');
$stmt->execute([(int)$user['id']]);
$row = $stmt->fetch();
json_response(['stream' => $row ? map_stream($row) : null]);
