<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();

try {
    $data = request_json();
    $identity = strtolower(trim((string)($data['identity'] ?? '')));
    $password = (string)($data['password'] ?? '');
    if ($identity === '' || $password === '') json_response(['message' => 'Kullanıcı adı/e-posta ve şifre zorunludur.'], 400);

    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(username) = ? OR LOWER(email) = ? LIMIT 1');
    $stmt->execute([$identity, $identity]);
    $user = $stmt->fetch();

    // First-install bootstrap admin seed. It is only available while no admin
    // account exists; after the admin changes email/password in Control Center,
    // old .env credentials can no longer overwrite the database account.
    if (!$user) {
        $env = app_env();
        $bootstrapUsername = strtolower(trim((string)($env['ADMIN_USERNAME'] ?? 'admin')));
        $bootstrapEmail = strtolower(trim((string)($env['ADMIN_EMAIL'] ?? 'buffyonetim@gmail.com')));
        $bootstrapPassword = (string)($env['ADMIN_PASSWORD'] ?? '');
        $adminCount = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_admin=1')->fetchColumn();
        $bootstrapMatch = $bootstrapPassword !== '' && in_array($identity,[$bootstrapUsername,$bootstrapEmail],true) && hash_equals($bootstrapPassword,$password);
        if ($bootstrapMatch && $adminCount === 0) {
            try { ensure_admin_control_schema(); ensure_admin_rbac_schema(); } catch (Throwable $e) {}
            $hash=password_hash($bootstrapPassword,PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (username,email,password_hash,display_name,account_mode,creator_enabled,is_admin,account_status) VALUES (?,?,?,'BUFF Admin','viewer',0,1,'active')")
                ->execute([$bootstrapUsername,$bootstrapEmail,$hash]);
            $user = fetch_user_by_id((int)$pdo->lastInsertId());
            if ($user && ensure_admin_rbac_schema()) {
                $roleId=(int)($pdo->query("SELECT id FROM admin_roles WHERE code='super_admin' LIMIT 1")->fetchColumn()?:0);
                if($roleId>0)$pdo->prepare('INSERT INTO admin_role_assignments (user_id,role_id,granted_by_user_id,expires_at) VALUES (?,?,?,NULL) ON DUPLICATE KEY UPDATE expires_at=NULL')->execute([(int)$user['id'],$roleId,(int)$user['id']]);
            }
        }
    }

    if (!$user || !password_verify($password, (string)$user['password_hash'])) json_response(['message' => 'Giriş bilgileri hatalı.'], 401);
    if (($user['account_status'] ?? 'active') !== 'active') json_response(['message' => 'Bu hesap geçici olarak kullanıma kapalı.'], 403);

    if (!empty($user['is_admin']) && !is_bootstrap_admin_account($user) && !admin_has_active_assignment((int)$user['id'])) {
        $pdo->prepare('UPDATE users SET is_admin=0 WHERE id=?')->execute([(int)$user['id']]);
        $user['is_admin']=0;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    touch_user_session((int)$user['id']);

    $redirect = null;
    if (!empty($user['is_admin'])) {
        $_SESSION['admin_user_id'] = (int)$user['id'];
        $_SESSION['admin_authenticated_at'] = time();
        $redirect = './admin.php';
    }

    $shared = v14_create_shared_session((int)$user['id'], !empty($user['is_admin']));
    json_response(['message' => 'Giriş başarılı.', 'user' => public_user($user), 'redirect' => $redirect, 'accessToken' => $shared['token'], 'tokenExpiresAt' => $shared['expiresAt']]);
} catch (Throwable $e) {
    error_log('[BUFF auth] ' . $e->getMessage());
    json_response(['message' => 'Giriş sırasında hata oluştu.' . (app_debug() ? ' Teknik ayrıntı: ' . $e->getMessage() : '')], 500);
}
