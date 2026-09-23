<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
v14_revoke_token(v14_request_token());
setcookie('buff_token','',['expires'=>time()-3600,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Lax']);
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)($params['secure'] ?? false), (bool)($params['httponly'] ?? true));
}
session_destroy();
json_response(['message' => 'Çıkış yapıldı.']);
