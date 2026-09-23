<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
$user = require_auth();
$streamId = (int)($_GET['stream_id'] ?? 0);
if ($streamId < 1) json_response(['role' => 'viewer', 'commands' => ['/help']]);
$role = stream_role($streamId, (int)$user['id']);
$commands = ['/help'];
if (in_array($role, ['moderator', 'owner'], true)) {
    $commands = array_merge($commands, ['/mute @kullanici 10', '/unmute @kullanici', '/ban @kullanici', '/unban @kullanici', '/clear', '/slow 5']);
}
if ($role === 'owner') {
    $commands = array_merge($commands, ['/mod @kullanici', '/unmod @kullanici']);
}
json_response(['role' => $role, 'commands' => $commands]);
