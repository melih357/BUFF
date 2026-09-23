<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
$me=require_auth();ensure_v16_schema(db());
try{
 $st=db()->prepare('SELECT r.* FROM message_rooms r JOIN message_room_members m ON m.room_id=r.id WHERE m.user_id=? ORDER BY r.updated_at DESC,r.id DESC LIMIT 100');
 $st->execute([(int)$me['id']]);
 json_response(['rooms'=>array_map(fn($r)=>v16_room_payload($r,(int)$me['id']),$st->fetchAll())]);
}catch(Throwable $e){error_log('[BUFF rooms] '.$e->getMessage());json_response(['message'=>'Sohbet odaları alınamadı.'],500);}
