<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();
$user = require_auth();
try {
    $data = request_json();
    $mode = strtolower(trim((string)($data['mode'] ?? 'viewer')));
    if (!in_array($mode, ['viewer','creator'], true)) json_response(['message'=>'Geçersiz hesap modu.'],400);
    $creatorEnabled = (bool)($user['creator_enabled'] ?? false);
    if ($mode === 'creator' && !$creatorEnabled) {
        $stmt = db()->prepare('UPDATE users SET creator_enabled = 1, account_mode = ? WHERE id = ?');
        $stmt->execute(['creator',(int)$user['id']]);
    } else {
        $stmt = db()->prepare('UPDATE users SET account_mode = ? WHERE id = ?');
        $stmt->execute([$mode,(int)$user['id']]);
    }
    $updated = fetch_user_by_id((int)$user['id']);
    json_response(['message'=>$mode==='creator'?'Yayıncı moduna geçildi.':'İzleyici moduna geçildi.','user'=>public_user($updated)]);
} catch (Throwable $e) { json_response(['message'=>'Hesap modu değiştirilemedi.'],500); }
