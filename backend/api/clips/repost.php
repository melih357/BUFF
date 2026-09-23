<?php
declare(strict_types=1);require_once dirname(__DIR__,2).'/bootstrap.php';require_post();$me=require_auth();ensure_v16_schema(db());
try{$d=request_json();$id=(int)($d['clipId']??0);v17_content('clip',$id,$me);if($id<1)json_response(['message'=>'Geçersiz klip.'],400);
 $st=db()->prepare('SELECT c.title,c.file_url,c.streamer_user_id FROM clips c WHERE c.id=? AND c.visibility=\'public\' LIMIT 1');$st->execute([$id]);$clip=$st->fetch();if(!$clip)json_response(['message'=>'Klip bulunamadı.'],404);
 $q=db()->prepare('SELECT 1 FROM clip_reposts WHERE clip_id=? AND user_id=?');$q->execute([$id,(int)$me['id']]);$on=(bool)$q->fetchColumn();
 if($on){db()->prepare('DELETE FROM clip_reposts WHERE clip_id=? AND user_id=?')->execute([$id,(int)$me['id']]);$on=false;}else{db()->prepare('INSERT INTO clip_reposts (clip_id,user_id) VALUES (?,?)')->execute([$id,(int)$me['id']]);db()->prepare("INSERT INTO feed_posts (user_id,body,video_url,visibility,clip_id) VALUES (?,?,?,'public',?)")->execute([(int)$me['id'],'🔁 Repost: '.$clip['title'],$clip['file_url'],$id]);$on=true;}
 $q=db()->prepare('SELECT COUNT(*) FROM clip_reposts WHERE clip_id=?');$q->execute([$id]);json_response(['reposted'=>$on,'repostCount'=>(int)$q->fetchColumn()]);
}catch(Throwable $e){json_response(['message'=>'Repost güncellenemedi.'],500);}
