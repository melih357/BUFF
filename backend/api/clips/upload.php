<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
$user=require_auth();if(request_method()!=='POST')json_response(['message'=>'POST gerekli.'],405);
try{
 $streamId=(int)($_POST['streamId']??0);$duration=(int)($_POST['durationSeconds']??30);if(!in_array($duration,[30,60],true))$duration=30;
 $stream=v17_stream($streamId,$user);if(!$stream)json_response(['message'=>'Yayın bulunamadı.'],404);
 if(empty($_FILES['video']['name']))json_response(['message'=>'Klip videosu gerekli.'],400);$f=$_FILES['video'];if(($f['error']??1)!==UPLOAD_ERR_OK)json_response(['message'=>'Klip yüklenemedi.'],400);
 if(($f['size']??0)>180*1024*1024)json_response(['message'=>'Klip 180 MB sınırını aşıyor.'],400);
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$a=['video/webm'=>'webm','video/mp4'=>'mp4','application/octet-stream'=>'webm'];if(!isset($a[$mime]))json_response(['message'=>'Klip WEBM veya MP4 olmalıdır.'],400);
 $stored=v14_store_upload($f,'clips','clip_'.(int)$user['id'].'_',$a[$mime],$mime);
 $title=text_substr(trim((string)($_POST['title']??($stream['title'].' klibi'))),0,160);
 // Keep both legacy file_path and V14 storage key/provider.
 $st=db()->prepare("INSERT INTO clips (stream_id,created_by_user_id,streamer_user_id,title,file_path,file_url,duration_seconds,visibility,processing_status,storage_key,storage_provider) VALUES (?,?,?,?,?,?,?,'public','queued',?,?)");
 $st->execute([$streamId,(int)$user['id'],(int)$stream['user_id'],$title,$stored['path'],$stored['url'],$duration,$stored['key'],$stored['provider']]);$clipId=(int)db()->lastInsertId();
 v14_enqueue('media_transcode',['ownerType'=>'clip','ownerId'=>$clipId,'inputUrl'=>$stored['url'],'storageKey'=>$stored['key'],'storageProvider'=>$stored['provider']],'media');
 v14_queue_media_moderation('clip',$clipId,(int)$user['id'],$stored['url']);
 // Every public clip becomes a feed item, creating an organic discovery loop.
 $body='🎬 '.(string)$user['display_name'].' yeni bir klip oluşturdu: '.$title;
 $feed=db()->prepare("INSERT INTO feed_posts (user_id,body,video_url,visibility,clip_id) VALUES (?,?,?,'public',?)");$feed->execute([(int)$user['id'],$body,$stored['url'],$clipId]);$postId=(int)db()->lastInsertId();
 // System-style chat message points viewers to the new clip while the stream is live.
 if(!empty($stream['is_live'])){
   $m=db()->prepare('INSERT INTO chat_messages (stream_id,user_id,message) VALUES (?,?,?)');$m->execute([$streamId,(int)$user['id'],'🎬 Yeni klip: '.$title.' (#clip-'.$clipId.')']);$messageId=(int)db()->lastInsertId();
   try{db()->prepare("INSERT INTO chat_message_meta (message_id,message_type,chat_color) VALUES (?,'system','#FF7A45')")->execute([$messageId]);}catch(Throwable $e){}
 }
 v14_channel_loyalty_add((int)$user['id'],(int)$stream['user_id'],['clips'=>1,'xp'=>20]);
 v14_add_loyalty((int)$user['id'],10,20);
 v14_record_interaction((int)$user['id'],(int)$stream['user_id'],'clip',$clipId,'create',3.0);
 v14_publish('clip.created',['clipId'=>$clipId,'postId'=>$postId,'title'=>$title,'fileUrl'=>v17_media_url('clip',$clipId),'creatorUserId'=>(int)$user['id']],null,$streamId,'stream.'.$streamId);
 json_response(['message'=>'Klip oluşturuldu ve Keşfet akışına eklendi.','clipId'=>$clipId,'postId'=>$postId,'fileUrl'=>v17_media_url('clip',$clipId),'processingStatus'=>'queued'],201);
}catch(Throwable $e){error_log('[BUFF clip] '.$e->getMessage());json_response(['message'=>'Klip oluşturulamadı: '.$e->getMessage()],500);}
