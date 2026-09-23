<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
try {
    $admin = require_admin();
    $roles = [];
    if (ensure_admin_rbac_schema()) {
        $st = db()->prepare('SELECT r.code,r.name,r.permissions_json,a.expires_at,a.created_at FROM admin_role_assignments a JOIN admin_roles r ON r.id=a.role_id WHERE a.user_id=? AND (a.expires_at IS NULL OR a.expires_at>NOW()) ORDER BY r.id');
        $st->execute([(int)$admin['id']]);
        $roles = $st->fetchAll();
    }
    json_response([
        'admin'=>public_user($admin),
        'roles'=>$roles,
        'bootstrapAdmin'=>is_bootstrap_admin_account($admin),
        'permissions'=>admin_permission_set((int)$admin['id'])
    ]);
} catch (Throwable $e) {
    json_response(['message'=>'Admin oturumu bulunamadı.'],401);
}
