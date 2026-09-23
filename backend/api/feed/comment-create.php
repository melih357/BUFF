<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';require_post();$u=require_auth();
try{
 $d=request_json();$postId=(int)($d['postId']??0);v17_content('post',$postId,current_user());$parent=max(0,(int)($d['parentCommentId']??0));$body=trim((string)($d['body']??''));
 if($postId<1||$body===''||text_len($body)>600)json_response(['message'=>'Yorum 1-600 karakter olmalıdır.'],400);
 if($parent&&!v17_query('SELECT 1 FROM feed_post_comments WHERE id=? AND post_id=?',[$parent,$postId])->fetchColumn())json_response(['message'=>'Yanıt bu içeriğin yorumuna ait olmalı.'],400);$mod=v14_moderate_text($body);if(!$mod['allow'])json_response(['message'=>'Yorum otomatik güvenlik filtresine takıldı.'],422);
 $owner=db()->prepare('SELECT user_id FROM feed_posts WHERE id=? LIMIT 1');$owner->execute([$postId]);$ownerId=(int)($owner->fetchColumn()?:0);if($ownerId<1)json_response(['message'=>'Gönderi bulunamadı.'],404);
 $st=db()->prepare('INSERT INTO feed_post_comments (post_id,user_id,parent_comment_id,body) VALUES (?,?,?,?)');$st->execute([$postId,(int)$u['id'],$parent?:null,$body]);$id=(int)db()->lastInsertId();
 v14_record_interaction((int)$u['id'],$ownerId,'feed',$postId,'comment',2.0);v14_add_loyalty((int)$u['id'],2,3);
 if($ownerId!==(int)$u['id'])create_notification($ownerId,(int)$u['id'],'feed_comment','Yeni yorum',(string)$u['display_name'].' gönderine yorum yaptı.','./moments.php?post='.$postId);
 v14_publish('feed.comment',['commentId'=>$id,'postId'=>$postId,'userId'=>(int)$u['id']],null,null,'feed.post.'.$postId);
 json_response(['message'=>'Yorum eklendi.','commentId'=>$id],201);
}catch(Throwable $e){error_log('[BUFF feed comment] '.$e->getMessage());json_response(['message'=>'Yorum eklenemedi.'],500);} 
