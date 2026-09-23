<?php
declare(strict_types=1);require_once dirname(__DIR__,2).'/bootstrap.php';require_post();$me=require_auth();ensure_v16_schema(db());
try{$d=request_json();$id=(int)($d['clipId']??0);v17_content('clip',$id,$me);if($id<1)json_response(['message'=>'Geçersiz klip.'],400);
 $c=db()->prepare('SELECT streamer_user_id FROM clips WHERE id=? AND visibility=\'public\'');$c->execute([$id]);$owner=(int)($c->fetchColumn()?:0);if(!$owner)json_response(['message'=>'Klip bulunamadı.'],404);
 $st=db()->prepare('SELECT 1 FROM clip_likes WHERE clip_id=? AND user_id=?');$st->execute([$id,(int)$me['id']]);$liked=(bool)$st->fetchColumn();
 if($liked){db()->prepare('DELETE FROM clip_likes WHERE clip_id=? AND user_id=?')->execute([$id,(int)$me['id']]);$liked=false;}else{db()->prepare('INSERT INTO clip_likes (clip_id,user_id) VALUES (?,?)')->execute([$id,(int)$me['id']]);$liked=true;v14_record_interaction((int)$me['id'],$owner,'clip',$id,'like',3.0);}
 $st=db()->prepare('SELECT COUNT(*) FROM clip_likes WHERE clip_id=?');$st->execute([$id]);$count=(int)$st->fetchColumn();v14_publish('clip.like',['clipId'=>$id,'liked'=>$liked,'likeCount'=>$count],null,null,'clip.'.$id);json_response(['liked'=>$liked,'likeCount'=>$count]);
}catch(Throwable $e){json_response(['message'=>'Beğeni güncellenemedi.'],500);}
