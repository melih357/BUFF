<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
ensure_v16_schema(db());
try{
 $uid=max(0,(int)($_GET['user_id']??0));$limit=max(1,min(100,(int)($_GET['limit']??48)));$where="c.visibility='public'";$args=[];if($uid){$where.=' AND c.created_by_user_id=?';$args[]=$uid;}
 $me=current_user();$meId=(int)($me['id']??0);$where.=' AND '.v17_profile_sql('c.created_by_user_id',$meId).' AND '.v17_profile_sql('c.streamer_user_id',$meId);$cid=(int)($_GET['id']??0);if($cid){v17_content('clip',$cid,$me);$where.=' AND c.id='.$cid;}
 $sql="SELECT c.*,u.display_name AS streamer_name,u.username,u.avatar_url,COALESCE(s.category,'Topluluk') AS category,
   cu.display_name AS clipper_name,(SELECT MIN(fp.id) FROM feed_posts fp WHERE fp.clip_id=c.id) AS feed_post_id,
   (SELECT COUNT(*) FROM clip_likes cl WHERE cl.clip_id=c.id) like_count,
   (SELECT COUNT(*) FROM clip_comments cc WHERE cc.clip_id=c.id) comment_count,
   (SELECT COUNT(*) FROM clip_reposts cr WHERE cr.clip_id=c.id) repost_count,
   (SELECT COUNT(*) FROM clip_shares cs WHERE cs.clip_id=c.id) share_count".
   ($meId>0?",EXISTS(SELECT 1 FROM clip_likes mlx WHERE mlx.clip_id=c.id AND mlx.user_id=$meId) liked_by_me,EXISTS(SELECT 1 FROM clip_reposts mrx WHERE mrx.clip_id=c.id AND mrx.user_id=$meId) reposted_by_me":",0 liked_by_me,0 reposted_by_me").
   " FROM clips c JOIN users u ON u.id=c.streamer_user_id JOIN users cu ON cu.id=c.created_by_user_id
     LEFT JOIN streams s ON s.id=c.stream_id
     
     WHERE $where ORDER BY c.id DESC LIMIT $limit";
 $st=db()->prepare($sql);$st->execute($args);$rows=$st->fetchAll();
 json_response(['clips'=>array_map(static fn($r)=>[
   'id'=>(int)$r['id'],'streamId'=>$r['stream_id']?(int)$r['stream_id']:null,'title'=>(string)$r['title'],'description'=>(string)($r['description']??''),
   'userId'=>(int)$r['created_by_user_id'],'fileUrl'=>v17_media_url('clip',(int)$r['id']),'thumbnailUrl'=>$r['thumbnail_url']?v17_media_url('clip',(int)$r['id'],'thumbnail'):null,'durationSeconds'=>(int)$r['duration_seconds'],'viewCount'=>(int)$r['view_count'],'createdAt'=>(string)$r['created_at'],
   'streamerName'=>(string)$r['streamer_name'],'username'=>(string)$r['username'],'avatarUrl'=>$r['avatar_url']?:null,'category'=>(string)$r['category'],'clipperName'=>(string)$r['clipper_name'],
   'sourceType'=>(string)($r['source_type']??'live'),'feedPostId'=>$r['feed_post_id']?(int)$r['feed_post_id']:null,
   'likeCount'=>(int)$r['like_count'],'commentCount'=>(int)$r['comment_count'],'repostCount'=>(int)$r['repost_count'],'shareCount'=>(int)$r['share_count'],
   'liked'=>(bool)$r['liked_by_me'],'reposted'=>(bool)$r['reposted_by_me']
 ],$rows)]);
}catch(Throwable $e){error_log('[BUFF clips list] '.$e->getMessage());json_response(['message'=>'Klipler alınamadı.'],500);}
