<?php
declare(strict_types=1);require_once dirname(__DIR__,2).'/bootstrap.php';require_post();$me=require_auth();ensure_v16_schema(db());
try{$d=request_json();$clipId=(int)($d['clipId']??0);v17_content('clip',$clipId,current_user());$parent=max(0,(int)($d['parentCommentId']??0));$body=trim((string)($d['body']??''));if($clipId<1||$body===''||text_len($body)>600)json_response(['message'=>'Yorum 1-600 karakter olmalıdır.'],400);
 $st=db()->prepare('SELECT streamer_user_id FROM clips WHERE id=? LIMIT 1');$st->execute([$clipId]);$owner=(int)($st->fetchColumn()?:0);if(!$owner)json_response(['message'=>'Klip bulunamadı.'],404);
 if($parent&&!v17_query('SELECT 1 FROM clip_comments WHERE id=? AND clip_id=?',[$parent,$clipId])->fetchColumn())json_response(['message'=>'Yanıt bu içeriğin yorumuna ait olmalı.'],400);$mod=v14_moderate_text($body);if(!$mod['allow'])json_response(['message'=>'Yorum güvenlik filtresine takıldı.'],422);
 $st=db()->prepare('INSERT INTO clip_comments (clip_id,user_id,parent_comment_id,body) VALUES (?,?,?,?)');$st->execute([$clipId,(int)$me['id'],$parent?:null,$body]);$id=(int)db()->lastInsertId();
 if($owner!==(int)$me['id'])create_notification($owner,(int)$me['id'],'clip_comment','Klibine yorum geldi',(string)$me['display_name'].' klibine yorum yaptı.','./clips.php?clip='.$clipId);
 v14_publish('clip.comment',['clipId'=>$clipId,'commentId'=>$id],null,null,'clip.'.$clipId);json_response(['message'=>'Yorum eklendi.','commentId'=>$id],201);
}catch(Throwable $e){json_response(['message'=>'Yorum eklenemedi.'],500);}
