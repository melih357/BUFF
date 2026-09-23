<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap.php';
try{
  $me=current_user();$meId=(int)($me['id']??0);
  $scoreExpr='((SELECT COUNT(*) FROM stream_viewers sv WHERE sv.stream_id=s.id AND sv.last_seen_at>=(NOW()-INTERVAL 20 SECOND))*10 + LEAST(s.gift_total,100000)/100)';
  if($me){
    $scoreExpr.=' + IF(EXISTS(SELECT 1 FROM follows fx WHERE fx.follower_user_id='.$meId.' AND fx.followed_user_id=s.user_id),500,0) + COALESCE((SELECT SUM(ci.weight) FROM content_interactions ci WHERE ci.user_id='.$meId.' AND ci.channel_user_id=s.user_id AND ci.created_at>=NOW()-INTERVAL 90 DAY),0)*20';
  }
  $base='SELECT s.*,u.username,u.display_name,u.avatar_url,(SELECT COUNT(*) FROM stream_viewers sv WHERE sv.stream_id=s.id AND sv.last_seen_at>=(NOW()-INTERVAL 20 SECOND)) viewer_count_live,(SELECT COUNT(*) FROM follows f WHERE f.followed_user_id=s.user_id) follower_count,'.$scoreExpr.' recommendation_score FROM streams s JOIN users u ON u.id=s.user_id WHERE s.is_live=1 AND '.v17_profile_sql('s.user_id',$meId);
  $live=db()->query($base.' ORDER BY recommendation_score DESC,s.started_at DESC')->fetchAll();
  $rising=db()->query($base.' ORDER BY s.started_at DESC,viewer_count_live DESC LIMIT 12')->fetchAll();
  $supported=db()->query($base.' ORDER BY s.gift_total DESC,viewer_count_live DESC LIMIT 12')->fetchAll();
  $categories=[];$stmt=db()->query('SELECT category,COUNT(*) live_count,COALESCE(SUM(gift_total),0) gift_total FROM streams WHERE is_live=1 AND '.v17_profile_sql('streams.user_id',$meId).' GROUP BY category ORDER BY live_count DESC,gift_total DESC,category ASC LIMIT 12');
  foreach($stmt->fetchAll() as $row)$categories[]=['name'=>(string)$row['category'],'liveCount'=>(int)$row['live_count'],'giftTotal'=>(int)$row['gift_total']];
  $following=[];if($me){$stmt=db()->prepare($base.' AND EXISTS(SELECT 1 FROM follows f2 WHERE f2.follower_user_id=? AND f2.followed_user_id=s.user_id) ORDER BY recommendation_score DESC');$stmt->execute([$meId]);$following=$stmt->fetchAll();}
  $clipScore=$me?" + COALESCE((SELECT SUM(ci.weight) FROM content_interactions ci WHERE ci.user_id={$meId} AND ci.channel_user_id=c.streamer_user_id AND ci.created_at>=NOW()-INTERVAL 90 DAY),0)*25":'';
  $clipAccess=v17_profile_sql('c.streamer_user_id',$meId).' AND '.v17_profile_sql('c.created_by_user_id',$meId);
  $clips=db()->query("SELECT c.id,c.title,c.file_url,c.hls_url,c.thumbnail_url,c.duration_seconds,c.view_count,c.streamer_user_id,c.created_by_user_id,u.username,u.display_name,u.avatar_url,(c.view_count+1){$clipScore} score FROM clips c JOIN users u ON u.id=c.streamer_user_id WHERE c.visibility='public' AND {$clipAccess} ORDER BY score DESC,c.id DESC LIMIT 24")->fetchAll();
  $recommendedClips=array_map(static fn($r)=>['id'=>(int)$r['id'],'title'=>$r['title'],'fileUrl'=>v17_media_url('clip',(int)$r['id']),'originalUrl'=>v17_media_url('clip',(int)$r['id']),'thumbnailUrl'=>$r['thumbnail_url']?v17_media_url('clip',(int)$r['id'],'thumbnail'):null,'durationSeconds'=>(int)$r['duration_seconds'],'viewCount'=>(int)$r['view_count'],'streamerUserId'=>(int)$r['streamer_user_id'],'createdByUserId'=>(int)$r['created_by_user_id'],'channel'=>['username'=>$r['username'],'displayName'=>$r['display_name'],'avatarUrl'=>$r['avatar_url']?:null]],$clips);
  json_response(['live'=>array_map('map_stream',$live),'rising'=>array_map('map_stream',$rising),'supported'=>array_map('map_stream',$supported),'following'=>array_map('map_stream',$following),'categories'=>$categories,'recommendedClips'=>$recommendedClips]);
}catch(Throwable $e){error_log('[BUFF discover] '.$e->getMessage());json_response(['message'=>'Keşfet verileri alınamadı.'],500);} 
