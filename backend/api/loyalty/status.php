<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
$user=require_auth();
try{
 $global=v14_loyalty_snapshot((int)$user['id']);
 $stmt=db()->prepare('SELECT cl.channel_user_id,cl.watch_seconds,cl.message_count,cl.gift_amount,cl.clip_count,cl.xp,cl.level,u.username,u.display_name,u.avatar_url FROM channel_loyalty cl JOIN users u ON u.id=cl.channel_user_id WHERE cl.user_id=? ORDER BY cl.level DESC,cl.xp DESC LIMIT 25');$stmt->execute([(int)$user['id']]);
 $channels=array_map(static fn($r)=>['channelUserId'=>(int)$r['channel_user_id'],'username'=>$r['username'],'displayName'=>$r['display_name'],'avatarUrl'=>$r['avatar_url']?:null,'watchSeconds'=>(int)$r['watch_seconds'],'messageCount'=>(int)$r['message_count'],'giftAmount'=>(int)$r['gift_amount'],'clipCount'=>(int)$r['clip_count'],'xp'=>(int)$r['xp'],'level'=>(int)$r['level']],$stmt->fetchAll());
 json_response(['loyalty'=>$global,'channels'=>$channels]);
}catch(Throwable $e){json_response(['message'=>'Sadakat bilgisi alınamadı.'],500);} 
