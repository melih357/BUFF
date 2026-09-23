<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
require_post();$me=require_auth();ensure_v16_schema(db());
try{
 if(empty($_FILES['video']['name']))json_response(['message'=>'Video dosyası gerekli.'],400);
 $f=$_FILES['video'];if(($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)json_response(['message'=>'Video yüklenemedi.'],400);
 if(($f['size']??0)>500*1024*1024)json_response(['message'=>'Video 500 MB sınırını aşıyor.'],400);
 [$mime,$ext]=v17_video_mime($f['tmp_name']);$map=[$mime=>$ext];
 $title=text_substr(trim((string)($_POST['title']??'')),0,160);$description=text_substr(trim((string)($_POST['description']??'')),0,500);
 if($title==='')json_response(['message'=>'Klip başlığı gerekli.'],400);
 $duration=max(1,min(3600,(int)($_POST['durationSeconds']??30)));
 $key=v17_key(['requestId'=>$_POST['requestId']??bin2hex(random_bytes(16))]);$cached=v17_begin((int)$me['id'],$key,'upload.clip',[$title,$description,hash_file('sha256',$f['tmp_name'])]);if($cached)json_response($cached);
 $stored=v14_store_upload($f,'clips','userclip_'.(int)$me['id'].'_',$map[$mime],$mime);
 $st=db()->prepare("INSERT INTO clips (stream_id,recording_id,created_by_user_id,streamer_user_id,title,description,file_path,file_url,duration_seconds,visibility,source_type,processing_status,storage_key,storage_provider) VALUES (NULL,NULL,?,?,?,?,?,?,?,'public','upload','queued',?,?)");
 $st->execute([(int)$me['id'],(int)$me['id'],$title,$description,$stored['path'],$stored['url'],$duration,$stored['key'],$stored['provider']]);$clipId=(int)db()->lastInsertId();
 v14_enqueue('media_transcode',['ownerType'=>'clip','ownerId'=>$clipId,'inputUrl'=>$stored['url'],'storageKey'=>$stored['key'],'storageProvider'=>$stored['provider']],'media');
 v14_queue_media_moderation('clip',$clipId,(int)$me['id'],$stored['url']);
 $body='🎬 '.$title.($description!==''?' • '.$description:'');
 $feed=db()->prepare("INSERT INTO feed_posts (user_id,body,video_url,visibility,clip_id) VALUES (?,?,?,'public',?)");$feed->execute([(int)$me['id'],$body,$stored['url'],$clipId]);$postId=(int)db()->lastInsertId();
 v14_record_interaction((int)$me['id'],(int)$me['id'],'clip',$clipId,'upload',4.0);v14_add_loyalty((int)$me['id'],15,25);
 v14_publish('clip.created',['clipId'=>$clipId,'postId'=>$postId,'title'=>$title,'fileUrl'=>v17_media_url('clip',$clipId),'creatorUserId'=>(int)$me['id'],'sourceType'=>'upload'],!empty($me['is_private'])?(int)$me['id']:null,null,'clips');
 $response=['message'=>'Video klip olarak yayınlandı ve Keşfet akışına eklendi.','clipId'=>$clipId,'postId'=>$postId,'fileUrl'=>v17_media_url('clip',$clipId)];v17_finish((int)$me['id'],$key,$response);json_response($response,201);
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();error_log('[BUFF user clip] '.$e->getMessage());json_response(['message'=>'Video klip olarak yüklenemedi.'],500);}
