<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
require_post();$me=require_auth();ensure_v16_schema(db());
try{
 $d=request_json();$targetId=(int)($d['userId']??0);$publicId=trim((string)($d['publicId']??''));
 if($targetId<1 && $publicId!==''){$st=db()->prepare('SELECT id FROM users WHERE public_id=? LIMIT 1');$st->execute([$publicId]);$targetId=(int)($st->fetchColumn()?:0);}
 if($targetId<1||$targetId===(int)$me['id'])json_response(['message'=>'Geçerli bir kullanıcı seç.'],400);
 if(!fetch_user_by_id($targetId))json_response(['message'=>'Kullanıcı bulunamadı.'],404);
 if(!v16_mutual_follow((int)$me['id'],$targetId))json_response(['message'=>'Özel sohbet için iki kullanıcının da birbirini takip etmesi gerekiyor.'],403);
 $pdo=db();$key=v16_direct_room_key((int)$me['id'],$targetId);
 $st=$pdo->prepare('SELECT * FROM message_rooms WHERE direct_key=? LIMIT 1');$st->execute([$key]);$room=$st->fetch();
 if(!$room){
   $pdo->beginTransaction();
   $pdo->prepare("INSERT INTO message_rooms (room_type,direct_key,title,created_by_user_id) VALUES ('direct',?,'',?)")->execute([$key,(int)$me['id']]);
   $roomId=(int)$pdo->lastInsertId();
   $ins=$pdo->prepare("INSERT INTO message_room_members (room_id,user_id,member_role) VALUES (?,?,'owner'),(?,?,'member')");
   $ins->execute([$roomId,(int)$me['id'],$roomId,$targetId]);$pdo->commit();
   $st=$pdo->prepare('SELECT * FROM message_rooms WHERE id=?');$st->execute([$roomId]);$room=$st->fetch();
 }
 json_response(['room'=>v16_room_payload($room,(int)$me['id'])]);
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();error_log('[BUFF create direct] '.$e->getMessage());json_response(['message'=>'Sohbet başlatılamadı.'],500);}
