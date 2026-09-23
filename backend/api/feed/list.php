<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
try{
  $me=current_user();$meId=(int)($me['id']??0);$uid=(int)($_GET['user_id']??0);$pid=(int)($_GET['id']??0);$before=(int)($_GET['before_id']??PHP_INT_MAX);$where=v17_profile_sql('p.user_id',$meId).' AND p.id<'.$before;if($uid)$where.=' AND p.user_id='.$uid;if($pid){v17_content('post',$pid,$me);$where.=' AND p.id='.$pid;}$where.=' AND (p.clip_id IS NULL OR EXISTS(SELECT 1 FROM clips pc WHERE pc.id=p.clip_id AND '.v17_profile_sql('pc.created_by_user_id',$meId).' AND '.v17_profile_sql('pc.streamer_user_id',$meId).'))';
  $sql='SELECT p.id,p.body,p.image_url,p.video_url,p.poll_json,p.is_pinned,p.created_at,p.visibility,p.membership_tier_required,p.clip_id,
               u.id user_id,u.username,u.display_name,u.avatar_url,
               (SELECT COUNT(*) FROM feed_post_likes l WHERE l.post_id=p.id) like_count,
               (SELECT COUNT(*) FROM feed_post_comments c WHERE c.post_id=p.id) comment_count,
               (SELECT COUNT(*) FROM feed_post_shares sh WHERE sh.post_id=p.id) share_count,'
       .($me?'EXISTS(SELECT 1 FROM feed_post_likes ml WHERE ml.post_id=p.id AND ml.user_id='.$meId.')':'0').' liked,'
       .($me?'EXISTS(SELECT 1 FROM saved_posts sp WHERE sp.post_id=p.id AND sp.user_id='.$meId.')':'0').' saved
         FROM feed_posts p JOIN users u ON u.id=p.user_id WHERE '.$where.' ORDER BY p.id DESC LIMIT 80';
  $rows=db()->query($sql)->fetchAll();$posts=[];
  foreach($rows as $r){
    $visibility=(string)($r['visibility']??'public');$required=(string)($r['membership_tier_required']??'');$ownerId=(int)$r['user_id'];
    if($visibility==='private'&&$ownerId!==$meId)continue;
    if($visibility==='members'&&$ownerId!==$meId){if(!$me||!v14_is_paid_member($meId,$required?:'plus'))continue;}
    $posts[]=['id'=>(int)$r['id'],'body'=>(string)$r['body'],'imageUrl'=>$r['image_url']?v17_media_url('post',(int)$r['id'],'image'):null,'videoUrl'=>$r['video_url']?v17_media_url('post',(int)$r['id']):null,
      'poll'=>$r['poll_json']?json_decode((string)$r['poll_json'],true):null,'isPinned'=>(bool)$r['is_pinned'],'createdAt'=>(string)$r['created_at'],
      'visibility'=>$visibility,'membershipTierRequired'=>$required?:null,'clipId'=>$r['clip_id']?(int)$r['clip_id']:null,
      'likeCount'=>(int)$r['like_count'],'commentCount'=>(int)$r['comment_count'],'shareCount'=>(int)$r['share_count'],'liked'=>(bool)$r['liked'],'saved'=>(bool)$r['saved'],
      'user'=>['id'=>$ownerId,'username'=>(string)$r['username'],'displayName'=>(string)$r['display_name'],'avatarUrl'=>$r['avatar_url']?:null]];
    if(count($posts)>=50)break;
  }
  $streamSql='SELECT s.*,u.username,u.display_name,u.avatar_url,(SELECT COUNT(*) FROM stream_viewers sv WHERE sv.stream_id=s.id AND sv.last_seen_at>=NOW()-INTERVAL 20 SECOND) viewer_count_live FROM streams s JOIN users u ON u.id=s.user_id WHERE s.is_live=1 AND '.v17_profile_sql('s.user_id',$meId).' ORDER BY viewer_count_live DESC,s.gift_total DESC LIMIT 8';
  $live=db()->query($streamSql)->fetchAll();
  json_response(['posts'=>$posts,'nextBeforeId'=>count($posts)>=50?(int)end($posts)['id']:null,'live'=>array_map('map_stream',$live),'loyalty'=>$me?v14_loyalty_snapshot($meId):null]);
}catch(Throwable $e){error_log('[BUFF feed list] '.$e->getMessage());json_response(['message'=>'Ana akış yüklenemedi.'],500);} 
