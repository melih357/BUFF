<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
$me=require_auth();ensure_v16_schema(db());
try{$st=db()->prepare('SELECT * FROM dm_signals WHERE recipient_user_id=? AND delivered=0 ORDER BY id ASC LIMIT 100');$st->execute([(int)$me['id']]);$rows=$st->fetchAll();if($rows){$ids=array_map(fn($r)=>(int)$r['id'],$rows);$ph=implode(',',array_fill(0,count($ids),'?'));db()->prepare("UPDATE dm_signals SET delivered=1 WHERE id IN ($ph)")->execute($ids);}
 json_response(['signals'=>array_map(static fn($r)=>['id'=>(int)$r['id'],'roomId'=>(int)$r['room_id'],'senderUserId'=>(int)$r['sender_user_id'],'type'=>(string)$r['signal_type'],'payload'=>json_decode((string)$r['payload_json'],true)??[],'createdAt'=>(string)$r['created_at']],$rows)]);
}catch(Throwable $e){json_response(['message'=>'Arama sinyalleri alınamadı.'],500);}
