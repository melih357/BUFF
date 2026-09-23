<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
$user=require_auth();if(request_method()!=='POST')json_response(['message'=>'POST gerekli.'],405);
try{
  $streamId=(int)($_POST['streamId']??0);$duration=max(0,(int)($_POST['durationSeconds']??0));
  $title=trim((string)($_POST['title']??'Yayın Tekrarı'));$visibility=strtolower((string)($_POST['visibility']??$user['replay_visibility']??'private'));
  if(!in_array($visibility,['public','unlisted','private','vip'],true))json_response(['message'=>'Geçersiz görünürlük.'],400);
  $stream=get_stream_by_id($streamId);if(!$stream||(int)$stream['user_id']!==(int)$user['id'])json_response(['message'=>'Bu yayın için kayıt yükleme yetkin yok.'],403);
  if(empty($_FILES['video']['name']))json_response(['message'=>'Kayıt dosyası gerekli.'],400);$file=$_FILES['video'];
  if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)json_response(['message'=>'Kayıt dosyası yüklenemedi. PHP upload limitini kontrol et.'],400);
  if(($file['size']??0)>700*1024*1024)json_response(['message'=>'Kayıt dosyası 700 MB sınırını aşıyor. Parçalı yüklemeyi kullan.'],400);
  $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$allowed=['video/webm'=>'webm','video/mp4'=>'mp4','video/ogg'=>'ogv','application/octet-stream'=>'webm'];
  if(!isset($allowed[$mime]))json_response(['message'=>'Kayıt WEBM veya MP4 olmalıdır.'],400);
  $stored=v14_store_upload($file,'recordings','vod_'.(int)$user['id'].'_',$allowed[$mime],$mime);
  $thumbUrl=null;$thumbPath=null;
  if(!empty($_FILES['thumbnail']['name'])){
    $thumb=$_FILES['thumbnail'];if(($thumb['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_OK){
      $tm=(new finfo(FILEINFO_MIME_TYPE))->file($thumb['tmp_name']);$ta=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
      if(isset($ta[$tm])&&($thumb['size']??0)<=4*1024*1024){$ts=v14_store_upload($thumb,'thumbnails','vodthumb_'.(int)$user['id'].'_',$ta[$tm],$tm);$thumbUrl=$ts['url'];$thumbPath=$ts['path'];}
    }
  }
  $stmt=db()->prepare("INSERT INTO recordings (stream_id,user_id,title,file_path,file_url,thumbnail_path,thumbnail_url,duration_seconds,visibility,status,processing_status,storage_key,storage_provider) VALUES (?,?,?,?,?,?,?,?,?,'ready','queued',?,?)");
  $stmt->execute([$streamId,(int)$user['id'],text_substr($title?:$stream['title'],0,160),$stored['path'],$stored['url'],$thumbPath,$thumbUrl,$duration,$visibility,$stored['key'],$stored['provider']]);$recordingId=(int)db()->lastInsertId();
  v14_enqueue('media_transcode',['ownerType'=>'recording','ownerId'=>$recordingId,'inputUrl'=>$stored['url'],'storageKey'=>$stored['key'],'storageProvider'=>$stored['provider']],'media');
  v14_queue_media_moderation('recording',$recordingId,(int)$user['id'],$stored['url']);
  v14_publish('recording.queued',['recordingId'=>$recordingId,'visibility'=>$visibility],(int)$user['id'],$streamId,'stream.'.$streamId);
  json_response(['message'=>'Yayın kaydı alındı; HLS/DASH işleme kuyruğuna eklendi.','recordingId'=>$recordingId,'fileUrl'=>v17_media_url('recording',$recordingId),'processingStatus'=>'queued'],201);
}catch(Throwable $e){error_log('[BUFF recording upload] '.$e->getMessage());json_response(['message'=>'Kayıt yüklenemedi: '.$e->getMessage()],500);}
