<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
require_post();$u=require_auth();
try{
  $d=request_json();$email=strtolower(trim((string)($d['email']??'')));$pass=(string)($d['password']??'');
  if(!filter_var($email,FILTER_VALIDATE_EMAIL))json_response(['message'=>'Geçerli e-posta gir.'],400);
  if(!password_verify($pass,(string)$u['password_hash']))json_response(['message'=>'Şifre yanlış.'],400);
  $st=db()->prepare('SELECT id FROM users WHERE email=? AND id<>?');$st->execute([$email,(int)$u['id']]);if($st->fetch())json_response(['message'=>'Bu e-posta kullanımda.'],409);
  $old=(string)$u['email'];$pdo=db();$pdo->beginTransaction();
  $pdo->prepare('UPDATE users SET email=?,email_changed_at=NOW() WHERE id=?')->execute([$email,(int)$u['id']]);
  v14_enqueue('send_email',['template'=>'email_changed','userId'=>(int)$u['id'],'to'=>$email,'oldEmail'=>$old],'email');
  $pdo->commit();v14_publish('account.email.changed',['userId'=>(int)$u['id']],(int)$u['id'],null,'user');
  json_response(['message'=>'E-posta değiştirildi. Bildirim arka planda gönderilecek.']);
}catch(Throwable $e){if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();json_response(['message'=>app_debug()?'E-posta değiştirilemedi: '.$e->getMessage():'E-posta değiştirilemedi.'],500);}
