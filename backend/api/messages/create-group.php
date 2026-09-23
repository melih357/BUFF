<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
require_post();$me=require_auth();ensure_v16_schema(db());
try{
 $d=request_json();$title=text_substr(trim((string)($d['title']??'')),0,120);$ids=array_values(array_unique(array_map('intval',(array)($d['userIds']??[]))));
 $ids=array_values(array_filter($ids,fn($id)=>$id>0&&$id!==(int)$me['id']));
 if($title==='')json_response(['message'=>'Grup adı gerekli.'],400);
 if(count($ids)<2)json_response(['message'=>'Grup için sen dahil en az 3 kişi gerekir.'],400);
 if(count($ids)>49)json_response(['message'=>'Bir grupta en fazla 50 kişi olabilir.'],400);
 foreach($ids as $uid){if(!v16_mutual_follow((int)$me['id'],$uid))json_response(['message'=>'Gruba yalnızca karşılıklı takip ettiğin kullanıcıları ekleyebilirsin.'],403);}
 $pdo=db();$pdo->beginTransaction();$pdo->prepare("INSERT INTO message_rooms (room_type,title,created_by_user_id) VALUES ('group',?,?)")->execute([$title,(int)$me['id']]);$roomId=(int)$pdo->lastInsertId();
 $ins=$pdo->prepare('INSERT INTO message_room_members (room_id,user_id,member_role) VALUES (?,?,?)');$ins->execute([$roomId,(int)$me['id'],'owner']);foreach($ids as $uid)$ins->execute([$roomId,$uid,'member']);
 $pdo->prepare("INSERT INTO room_messages (room_id,user_id,message_type,body) VALUES (?,?,'system',?)")->execute([$roomId,(int)$me['id'],$me['display_name'].' grubu oluşturdu.']);
 $pdo->commit();$st=$pdo->prepare('SELECT * FROM message_rooms WHERE id=?');$st->execute([$roomId]);$room=$st->fetch();
 foreach($ids as $uid)create_notification($uid,(int)$me['id'],'message_group','Yeni grup',(string)$me['display_name'].' seni “'.$title.'” grubuna ekledi.','./messages.php?room='.$roomId);
 v14_publish('message.room.created',['roomId'=>$roomId],null,null,'room.'.$roomId);
 json_response(['message'=>'Grup oluşturuldu.','room'=>v16_room_payload($room,(int)$me['id'])],201);
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();error_log('[BUFF create group] '.$e->getMessage());json_response(['message'=>'Grup oluşturulamadı.'],500);}
