<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
$me=require_auth();ensure_v16_schema(db());$roomId=(int)($_GET['room_id']??0);$after=max(0,(int)($_GET['after_id']??0));
if($roomId<1||!v16_room_member($roomId,(int)$me['id']))json_response(['message'=>'Bu sohbete erişimin yok.'],403);
try{
 $st=db()->prepare('SELECT rm.*,u.public_id,u.username,u.display_name,u.avatar_url FROM room_messages rm JOIN users u ON u.id=rm.user_id WHERE rm.room_id=? AND rm.id>? AND rm.deleted_at IS NULL ORDER BY rm.id ASC LIMIT 250');
 $st->execute([$roomId,$after]);$rows=$st->fetchAll();$max=$after;foreach($rows as $r)$max=max($max,(int)$r['id']);
 if($max>$after)db()->prepare('UPDATE message_room_members SET last_read_message_id=GREATEST(last_read_message_id,?) WHERE room_id=? AND user_id=?')->execute([$max,$roomId,(int)$me['id']]);
 json_response(['messages'=>array_map(static fn($r)=>['id'=>(int)$r['id'],'roomId'=>(int)$r['room_id'],'type'=>(string)$r['message_type'],'body'=>(string)$r['body'],'mediaUrl'=>$r['media_url']?:null,'mediaMime'=>$r['media_mime']?:null,'durationSeconds'=>(int)$r['duration_seconds'],'createdAt'=>(string)$r['created_at'],'mine'=>(int)$r['user_id']===(int)$me['id'],'user'=>['id'=>(int)$r['user_id'],'publicId'=>(string)($r['public_id']??''),'username'=>(string)$r['username'],'displayName'=>(string)$r['display_name'],'avatarUrl'=>$r['avatar_url']?:null]],$rows),'lastId'=>$max]);
}catch(Throwable $e){json_response(['message'=>'Mesajlar alınamadı.'],500);}
