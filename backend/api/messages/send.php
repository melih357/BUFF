<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
require_post();$me=require_auth();ensure_v16_schema(db());
try{
 $roomId=(int)($_POST['roomId']??0);if($roomId<1){$d=request_json();$roomId=(int)($d['roomId']??0);$_POST['body']=$d['body']??'';}
 if($roomId<1||!v16_room_member($roomId,(int)$me['id']))json_response(['message'=>'Bu sohbete mesaj gönderemezsin.'],403);
 $roomSt=db()->prepare('SELECT room_type FROM message_rooms WHERE id=?');$roomSt->execute([$roomId]);$roomType=(string)($roomSt->fetchColumn()?:'');
 if($roomType==='direct'){
   $peer=db()->prepare('SELECT user_id FROM message_room_members WHERE room_id=? AND user_id<>? LIMIT 1');$peer->execute([$roomId,(int)$me['id']]);$peerId=(int)($peer->fetchColumn()?:0);
   if($peerId<1||!v16_mutual_follow((int)$me['id'],$peerId))json_response(['message'=>'Özel mesaj için karşılıklı takip devam etmeli.'],403);
 }
 $type='text';$body=text_substr(trim((string)($_POST['body']??'')),0,2000);$mediaUrl=null;$mediaMime=null;$duration=max(0,(int)($_POST['durationSeconds']??0));
 if(!empty($_FILES['media']['name'])){
   $f=$_FILES['media'];if(($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)json_response(['message'=>'Medya yüklenemedi.'],400);
   if(($f['size']??0)>25*1024*1024)json_response(['message'=>'Mesaj medyası 25 MB sınırını aşıyor.'],400);
   $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
   $map=['image/jpeg'=>['image','jpg'],'image/png'=>['image','png'],'image/webp'=>['image','webp'],'image/gif'=>['image','gif'],'audio/webm'=>['voice','webm'],'audio/ogg'=>['voice','ogg'],'audio/mpeg'=>['voice','mp3'],'audio/mp4'=>['voice','m4a'],'application/octet-stream'=>['voice','webm']];
   if(!isset($map[$mime]))json_response(['message'=>'Yalnızca fotoğraf veya sesli mesaj gönderebilirsin.'],415);
   [$type,$ext]=$map[$mime];$stored=v14_store_upload($f,$type==='image'?'messages/images':'messages/voice','msg_'.(int)$me['id'].'_',$ext,$mime);$mediaUrl=$stored['url'];$mediaMime=$mime;
   v14_queue_media_moderation('room_message',0,(int)$me['id'],$mediaUrl);
 }
 if($type==='text'&&$body==='')json_response(['message'=>'Mesaj boş olamaz.'],400);
 if($body!==''){$mod=v14_moderate_text($body);if(!$mod['allow'])json_response(['message'=>'Mesaj güvenlik filtresine takıldı.'],422);}
 $st=db()->prepare('INSERT INTO room_messages (room_id,user_id,message_type,body,media_url,media_mime,duration_seconds) VALUES (?,?,?,?,?,?,?)');$st->execute([$roomId,(int)$me['id'],$type,$body,$mediaUrl,$mediaMime,$duration]);$id=(int)db()->lastInsertId();
 db()->prepare('UPDATE message_rooms SET updated_at=NOW() WHERE id=?')->execute([$roomId]);
 $targets=db()->prepare('SELECT user_id FROM message_room_members WHERE room_id=? AND user_id<>?');$targets->execute([$roomId,(int)$me['id']]);foreach($targets->fetchAll() as $t){$uid=(int)$t['user_id'];create_notification($uid,(int)$me['id'],'direct_message','Yeni mesaj',(string)$me['display_name'].' sana mesaj gönderdi.','./messages.php?room='.$roomId);}
 v14_publish('message.created',['roomId'=>$roomId,'messageId'=>$id,'type'=>$type,'senderUserId'=>(int)$me['id']],null,null,'room.'.$roomId);
 json_response(['message'=>'Mesaj gönderildi.','messageId'=>$id,'type'=>$type,'mediaUrl'=>$mediaUrl],201);
}catch(Throwable $e){error_log('[BUFF message send] '.$e->getMessage());json_response(['message'=>'Mesaj gönderilemedi.'],500);}
