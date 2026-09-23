<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
$me=require_auth();ensure_v16_schema(db());
try{
 $st=db()->prepare("SELECT u.id,u.public_id,u.username,u.display_name,u.avatar_url,u.bio
 FROM follows a JOIN follows b ON b.follower_user_id=a.followed_user_id AND b.followed_user_id=a.follower_user_id
 JOIN users u ON u.id=a.followed_user_id
 WHERE a.follower_user_id=? AND u.account_status='active' ORDER BY u.display_name LIMIT 200");
 $st->execute([(int)$me['id']]);
 json_response(['contacts'=>array_map(static fn($u)=>['id'=>(int)$u['id'],'publicId'=>(string)($u['public_id']??''),'username'=>(string)$u['username'],'displayName'=>(string)$u['display_name'],'avatarUrl'=>$u['avatar_url']?:null,'bio'=>(string)($u['bio']??'')],$st->fetchAll())]);
}catch(Throwable $e){json_response(['message'=>'Kişiler alınamadı.'],500);}
