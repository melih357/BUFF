<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
$admin=require_admin();

if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
    json_response(['admin'=>public_user($admin)]);
}
require_post();
$d=request_json();
$action=(string)($d['action']??'');
$currentPassword=(string)($d['currentPassword']??'');
if(!password_verify($currentPassword,(string)$admin['password_hash']))json_response(['message'=>'Mevcut admin şifresi yanlış.'],400);
$pdo=db();

try{
    if($action==='change_email'){
        $email=strtolower(trim((string)($d['email']??'')));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))json_response(['message'=>'Geçerli bir e-posta adresi gir.'],400);
        $st=$pdo->prepare('SELECT id FROM users WHERE email=? AND id<>? LIMIT 1');$st->execute([$email,(int)$admin['id']]);
        if($st->fetch())json_response(['message'=>'Bu e-posta başka bir hesap tarafından kullanılıyor.'],409);
        $old=(string)$admin['email'];
        $pdo->prepare('UPDATE users SET email=?,email_changed_at=NOW() WHERE id=?')->execute([$email,(int)$admin['id']]);
        admin_audit((int)$admin['id'],'admin_email_change','user',(int)$admin['id'],['oldEmail'=>$old,'newEmail'=>$email]);
        json_response(['message'=>'Admin e-posta adresi değiştirildi. Bir sonraki girişte yeni e-postayı kullanabilirsin.','admin'=>public_user(fetch_user_by_id((int)$admin['id']))]);
    }
    if($action==='change_password'){
        $new=(string)($d['newPassword']??'');$confirm=(string)($d['confirmPassword']??'');
        if(text_len($new)<8)json_response(['message'=>'Yeni şifre en az 8 karakter olmalı.'],400);
        if($new!==$confirm)json_response(['message'=>'Yeni şifreler eşleşmiyor.'],400);
        if(password_verify($new,(string)$admin['password_hash']))json_response(['message'=>'Yeni şifre mevcut şifreyle aynı olamaz.'],409);
        $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),(int)$admin['id']]);
        admin_audit((int)$admin['id'],'admin_password_change','user',(int)$admin['id']);
        json_response(['message'=>'Admin şifresi değiştirildi.']);
    }
    json_response(['message'=>'Geçersiz admin profil işlemi.'],400);
}catch(Throwable $e){
    error_log('[BUFF admin profile] '.$e->getMessage());
    json_response(['message'=>app_debug()?'Admin hesabı güncellenemedi: '.$e->getMessage():'Admin hesabı güncellenemedi.'],500);
}
