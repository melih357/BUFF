<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';require_post();$u=require_auth();
try{
  $d=request_json();if(!password_verify((string)($d['password']??''),(string)$u['password_hash']))json_response(['message'=>'Şifre yanlış.'],400);
  $uid=(int)$u['id'];$pdo=db();$pdo->beginTransaction();
  // Queue user-owned media for storage deletion before PII references are anonymized.
  $media=[];
  foreach(['avatar_url'] as $col){if(!empty($u[$col]))$media[]=(string)$u[$col];}
  try{$s=$pdo->prepare('SELECT image_url,video_url FROM feed_posts WHERE user_id=?');$s->execute([$uid]);foreach($s->fetchAll() as $r){if($r['image_url'])$media[]=$r['image_url'];if($r['video_url'])$media[]=$r['video_url'];}}catch(Throwable $e){}
  try{$s=$pdo->prepare('SELECT file_url,thumbnail_url FROM recordings WHERE user_id=?');$s->execute([$uid]);foreach($s->fetchAll() as $r){if($r['file_url'])$media[]=$r['file_url'];if($r['thumbnail_url'])$media[]=$r['thumbnail_url'];}}catch(Throwable $e){}
  try{$s=$pdo->prepare('SELECT file_url,thumbnail_url FROM clips WHERE created_by_user_id=?');$s->execute([$uid]);foreach($s->fetchAll() as $r){if($r['file_url'])$media[]=$r['file_url'];if($r['thumbnail_url'])$media[]=$r['thumbnail_url'];}}catch(Throwable $e){}

  // Keep immutable financial/audit rows for statutory/accounting retention, but remove active payment/session credentials.
  try{$pdo->prepare('DELETE FROM payment_methods WHERE user_id=?')->execute([$uid]);}catch(Throwable $e){}
  try{$pdo->prepare('DELETE FROM user_sessions WHERE user_id=?')->execute([$uid]);}catch(Throwable $e){}
  try{$pdo->prepare('UPDATE shared_sessions SET revoked_at=NOW() WHERE user_id=?')->execute([$uid]);}catch(Throwable $e){}
  $anon='closed_'.$uid.'_'.substr(hash('sha256',(string)microtime(true).random_bytes(8)),0,12);
  $email=$anon.'@deleted.invalid';$hash=password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT);
  $pdo->prepare("UPDATE users SET username=?,email=?,phone=NULL,billing_address=NULL,password_hash=?,display_name='Closed User',avatar_url=NULL,bio='',account_status='closed' WHERE id=?")
      ->execute([$anon,$email,$hash,$uid]);
  try{$pdo->prepare("UPDATE channel_profiles SET bio='',social_links_json=NULL,banner_url=NULL WHERE user_id=?")->execute([$uid]);}catch(Throwable $e){}
  $pdo->commit();
  foreach(array_values(array_unique($media)) as $url){try{v14_enqueue('storage_cleanup',['url'=>$url,'userId'=>$uid],'media');}catch(Throwable $e){}}
  v14_finance_audit('account_closed','user',$uid,$uid,['retention'=>'financial_records_preserved','personal_data'=>'anonymized']);
  v14_revoke_token(v14_request_token());setcookie('buff_token','',['expires'=>time()-3600,'path'=>'/','secure'=>false,'httponly'=>true,'samesite'=>'Lax']);$_SESSION=[];session_destroy();
  json_response(['message'=>'Hesap kapatıldı ve kişisel veriler anonimleştirildi. Finansal kayıtlar yasal saklama yükümlülükleri kapsamında korunur.']);
}catch(Throwable $e){if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();error_log('[BUFF account close] '.$e->getMessage());json_response(['message'=>'Hesap kapatılamadı.'],500);} 
