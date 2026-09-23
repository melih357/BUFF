<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
v14_revoke_token(v14_request_token());
setcookie('buff_token','',['expires'=>time()-3600,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Lax']);
require_post();
unset($_SESSION['admin_user_id'], $_SESSION['admin_authenticated_at']);
json_response(['message'=>'Admin oturumu kapatıldı.']);
