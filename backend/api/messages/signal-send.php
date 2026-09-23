<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
require_post();$me=require_auth();ensure_v16_schema(db());
try{$d=request_json();$roomId=(int)($d['roomId']??0);$to=(int)($d['recipientUserId']??0);$type=(string)($d['type']??'');$payload=$d['payload']??[];
 if($roomId<1||$to<1||!in_array($type,['offer','answer','candidate','ring','accept','reject','hangup'],true))json_response(['message'=>'Geçersiz arama sinyali.'],400);
 if(!v16_room_member($roomId,(int)$me['id'])||!v16_room_member($roomId,$to))json_response(['message'=>'Arama yetkin yok.'],403);
 $room=db()->prepare('SELECT room_type FROM message_rooms WHERE id=?');$room->execute([$roomId]);if($room->fetchColumn()!=='direct')json_response(['message'=>'Sesli arama şu anda yalnızca birebir sohbetlerde kullanılabilir.'],409);
 if(!v16_mutual_follow((int)$me['id'],$to))json_response(['message'=>'Arama için karşılıklı takip gerekli.'],403);
 db()->prepare('INSERT INTO dm_signals (room_id,sender_user_id,recipient_user_id,signal_type,payload_json) VALUES (?,?,?,?,?)')->execute([$roomId,(int)$me['id'],$to,$type,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
 v14_publish('call.signal',['roomId'=>$roomId,'fromUserId'=>(int)$me['id'],'toUserId'=>$to,'type'=>$type],$to,null,'user.'.$to);
 json_response(['ok'=>true]);
}catch(Throwable $e){json_response(['message'=>'Arama sinyali gönderilemedi.'],500);}
