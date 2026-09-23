<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();
$user=require_auth();
try{
  $data=request_json();$postId=(int)($data['postId']??0);v17_content('post',$postId,current_user());if($postId<1)json_response(['message'=>'Geçersiz gönderi.'],400);
  $stmt=db()->prepare('INSERT IGNORE INTO feed_post_shares (post_id,user_id) VALUES (?,?)');$stmt->execute([$postId,(int)$user['id']]);
  $stmt=db()->prepare('SELECT COUNT(*) c FROM feed_post_shares WHERE post_id=?');$stmt->execute([$postId]);
  json_response(['message'=>'Gönderi paylaşım listene eklendi.','shareCount'=>(int)$stmt->fetch()['c']]);
}catch(Throwable $e){json_response(['message'=>'Gönderi paylaşılamadı.'],500);}
