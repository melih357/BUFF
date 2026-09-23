<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
$user = current_user();
if (!$user) {
    json_response(['message' => 'Oturum yok.'], 401);
}
json_response(['user' => public_user($user)]);
