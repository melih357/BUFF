<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();

try {
    $data = request_json();
    $identity = strtolower(trim((string)($data['identity'] ?? '')));
    $password = (string)($data['password'] ?? '');
    if ($identity === '' || $password === '') json_response(['message'=>'Admin giriş bilgileri eksik.'],400);

    try { ensure_admin_control_schema(); ensure_admin_rbac_schema(); }
    catch (Throwable $schemaError) { error_log('[BUFF admin optional schema] ' . $schemaError->getMessage()); }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(username)=? OR LOWER(email)=? LIMIT 1');
    $stmt->execute([$identity,$identity]);
    $admin = $stmt->fetch();
    $adminId = 0;

    if ($admin) {
        if (empty($admin['is_admin']) || !password_verify($password,(string)$admin['password_hash'])) {
            json_response(['message'=>'Admin giriş bilgileri hatalı veya bu hesapta yönetici yetkisi yok.'],401);
        }
        if (($admin['account_status'] ?? 'active') !== 'active') json_response(['message'=>'Bu yönetici hesabı aktif değil.'],403);
        if (!is_bootstrap_admin_account($admin) && !admin_has_active_assignment((int)$admin['id'])) {
            $pdo->prepare('UPDATE users SET is_admin=0 WHERE id=?')->execute([(int)$admin['id']]);
            json_response(['message'=>'Bu hesabın yönetici yetkisi sona ermiş.'],403);
        }
        $adminId = (int)$admin['id'];
    } else {
        // Bootstrap credentials are a one-time recovery/seed path only. Once an
        // admin exists, changing its email/password in the panel is persistent
        // and the old .env credentials cannot silently overwrite it.
        $env = app_env();
        $bootstrapUsername = strtolower(trim((string)($env['ADMIN_USERNAME'] ?? 'admin')));
        $bootstrapEmail = strtolower(trim((string)($env['ADMIN_EMAIL'] ?? 'buffyonetim@gmail.com')));
        $bootstrapPassword = (string)($env['ADMIN_PASSWORD'] ?? '');
        $adminCount = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_admin=1')->fetchColumn();
        $bootstrapMatch = $bootstrapPassword !== '' && in_array($identity,[$bootstrapUsername,$bootstrapEmail],true) && hash_equals($bootstrapPassword,$password);
        if (!$bootstrapMatch || $adminCount > 0) json_response(['message'=>'Admin giriş bilgileri hatalı veya bu hesapta yönetici yetkisi yok.'],401);

        $hash = password_hash($bootstrapPassword,PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (username,email,password_hash,display_name,account_mode,creator_enabled,is_admin,account_status) VALUES (?,?,?,'BUFF Admin','viewer',0,1,'active')")
            ->execute([$bootstrapUsername,$bootstrapEmail,$hash]);
        $adminId = (int)$pdo->lastInsertId();
        $admin = fetch_user_by_id($adminId);
    }

    // Guarantee the bootstrap/super admin role without making login depend on it.
    try {
        if (ensure_admin_rbac_schema()) {
            $roleId = (int)($pdo->query("SELECT id FROM admin_roles WHERE code='super_admin' LIMIT 1")->fetchColumn() ?: 0);
            if ($roleId > 0 && is_bootstrap_admin_account(fetch_user_by_id($adminId) ?: [])) {
                $pdo->prepare('INSERT INTO admin_role_assignments (user_id,role_id,granted_by_user_id,expires_at) VALUES (?,?,?,NULL) ON DUPLICATE KEY UPDATE expires_at=NULL')->execute([$adminId,$roleId,$adminId]);
            }
        }
    } catch (Throwable $rbacError) { error_log('[BUFF admin RBAC login] ' . $rbacError->getMessage()); }

    session_regenerate_id(true);
    $_SESSION['admin_user_id']=$adminId;
    $_SESSION['admin_authenticated_at']=time();
    $admin=fetch_user_by_id($adminId);
    $shared = v14_create_shared_session($adminId, true);
    json_response(['message'=>'Admin girişi başarılı.','admin'=>public_user($admin),'redirect'=>'./admin.php','accessToken'=>$shared['token'],'tokenExpiresAt'=>$shared['expiresAt']]);
} catch(Throwable $e){
    error_log('[BUFF admin login] '.$e->getMessage());
    json_response(['message'=>app_debug()?'Admin girişi başarısız: '.$e->getMessage():'Admin girişi başarısız.'],500);
}
