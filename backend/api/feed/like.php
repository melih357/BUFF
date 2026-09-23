<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();
$user = require_auth();
try {
    $data = request_json();
    $postId = (int)($data['postId'] ?? 0);
    if ($postId < 1) json_response(['message' => 'Geçersiz gönderi.'], 400);
    $stmt = db()->prepare('SELECT 1 FROM feed_post_likes WHERE post_id = ? AND user_id = ?');
    $stmt->execute([$postId, (int)$user['id']]);
    if ($stmt->fetch()) {
        db()->prepare('DELETE FROM feed_post_likes WHERE post_id = ? AND user_id = ?')->execute([$postId, (int)$user['id']]);
        $liked = false;
    } else {
        db()->prepare('INSERT INTO feed_post_likes (post_id, user_id) VALUES (?, ?)')->execute([$postId, (int)$user['id']]);
        $liked = true;
    }
    $stmt = db()->prepare('SELECT COUNT(*) AS c FROM feed_post_likes WHERE post_id = ?');
    $stmt->execute([$postId]);
    $count=(int)$stmt->fetch()['c'];
    $owner=db()->prepare('SELECT user_id FROM feed_posts WHERE id=? LIMIT 1');$owner->execute([$postId]);$ownerId=(int)($owner->fetchColumn()?:0);
    if($liked){v14_record_interaction((int)$user['id'],$ownerId?:null,'feed',$postId,'like',2.5);v14_add_loyalty((int)$user['id'],1,1);}
    v14_publish('feed.like',['postId'=>$postId,'userId'=>(int)$user['id'],'liked'=>$liked,'likeCount'=>$count],null,null,'feed.post.'.$postId);
    json_response(['liked' => $liked, 'likeCount' => $count]);
} catch (Throwable $e) {
    json_response(['message' => 'Beğeni güncellenemedi.'], 500);
}
