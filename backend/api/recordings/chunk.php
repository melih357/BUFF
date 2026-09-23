<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
$user=require_auth();if(request_method()!=='POST')json_response(['message'=>'POST gerekli.'],405);
try{
  $streamId=(int)($_POST['streamId']??0);$uploadId=preg_replace('/[^a-zA-Z0-9_-]/','',(string)($_POST['uploadId']??''));
  $chunkIndex=max(0,(int)($_POST['chunkIndex']??0));$totalChunks=max(1,min(20000,(int)($_POST['totalChunks']??1)));$duration=max(0,(int)($_POST['durationSeconds']??0));
  $title=trim((string)($_POST['title']??'Yayın Tekrarı'));$visibility=strtolower((string)($_POST['visibility']??$user['replay_visibility']??'private'));$mimeType=strtolower((string)($_POST['mimeType']??'video/webm'));
  if(!in_array($visibility,['public','unlisted','private','vip'],true))json_response(['message'=>'Geçersiz görünürlük.'],400);if($streamId<1||$uploadId===''||strlen($uploadId)>120||$chunkIndex>=$totalChunks)json_response(['message'=>'Geçersiz VOD yükleme bilgisi.'],400);
  $stream=get_stream_by_id($streamId);if(!$stream||(int)$stream['user_id']!==(int)$user['id'])json_response(['message'=>'Bu yayın için kayıt yükleme yetkin yok.'],403);
  if(empty($_FILES['videoChunk'])||($_FILES['videoChunk']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)json_response(['message'=>'VOD parçası yüklenemedi.'],400);
  $chunk=$_FILES['videoChunk'];if(($chunk['size']??0)>4*1024*1024)json_response(['message'=>'VOD parçası beklenenden büyük.'],400);
  $tmpRoot=dirname(__DIR__,3).'/uploads/.chunks';if(!is_dir($tmpRoot)&&!mkdir($tmpRoot,0775,true)&&!is_dir($tmpRoot))throw new RuntimeException('VOD geçici klasörü oluşturulamadı.');
  $uploadId='u'.(int)$user['id'].'_'.$uploadId;$uploadDir=$tmpRoot.'/'.$uploadId;if(!is_dir($uploadDir)&&!mkdir($uploadDir,0775,true)&&!is_dir($uploadDir))throw new RuntimeException('VOD yükleme klasörü oluşturulamadı.');
  $partPath=$uploadDir.'/'.sprintf('%06d.part',$chunkIndex);if(!move_uploaded_file($chunk['tmp_name'],$partPath))throw new RuntimeException('VOD parçası diske yazılamadı.');
  if($chunkIndex<$totalChunks-1)json_response(['message'=>'VOD parçası alındı.','received'=>$chunkIndex+1,'total'=>$totalChunks]);
  for($i=0;$i<$totalChunks;$i++)if(!is_file($uploadDir.'/'.sprintf('%06d.part',$i)))json_response(['message'=>'VOD yüklemesinde eksik parça var. Tekrar deneyin.'],409);
  $ext=str_contains($mimeType,'mp4')?'mp4':'webm';$assembled=$tmpRoot.'/assembled_'.$uploadId.'.'.$ext;$out=fopen($assembled,'wb');if(!$out)throw new RuntimeException('VOD dosyası oluşturulamadı.');
  try{for($i=0;$i<$totalChunks;$i++){$part=$uploadDir.'/'.sprintf('%06d.part',$i);$in=fopen($part,'rb');if(!$in)throw new RuntimeException('VOD parçası okunamadı.');stream_copy_to_stream($in,$out);fclose($in);}}finally{fclose($out);} 
  foreach(glob($uploadDir.'/*.part')?:[] as $part)@unlink($part);@rmdir($uploadDir);if(!is_file($assembled)||filesize($assembled)<1)throw new RuntimeException('VOD dosyası boş oluştu.');
  $stored=v14_storage_put_path($assembled,'recordings','vod_'.(int)$user['id'].'_'.safe_upload_name($ext),$ext==='mp4'?'video/mp4':'video/webm',true);
  $pdo=db();$stmt=$pdo->prepare("INSERT INTO recordings (stream_id,user_id,title,file_path,file_url,thumbnail_path,thumbnail_url,duration_seconds,visibility,status,processing_status,storage_key,storage_provider) VALUES (?,?,?,?,?,NULL,NULL,?,?, 'ready','queued',?,?)");
  $stmt->execute([$streamId,(int)$user['id'],text_substr($title?:$stream['title'],0,160),$stored['path'],$stored['url'],$duration,$visibility,$stored['key'],$stored['provider']]);$recordingId=(int)$pdo->lastInsertId();
  v14_enqueue('media_transcode',['ownerType'=>'recording','ownerId'=>$recordingId,'inputUrl'=>$stored['url'],'storageKey'=>$stored['key'],'storageProvider'=>$stored['provider']],'media');
  v14_queue_media_moderation('recording',$recordingId,(int)$user['id'],$stored['url']);
  v14_publish('recording.queued',['recordingId'=>$recordingId,'visibility'=>$visibility],(int)$user['id'],$streamId,'stream.'.$streamId);
  json_response(['message'=>$visibility==='public'?'Yayın tekrarı işlenmek üzere kaydedildi.':'Yayın tekrarı kaydedildi.','recordingId'=>$recordingId,'fileUrl'=>v17_media_url('recording',$recordingId),'visibility'=>$visibility,'processingStatus'=>'queued'],201);
}catch(Throwable $e){error_log('[BUFF recording chunk] '.$e->getMessage());json_response(['message'=>'VOD yüklenemedi: '.$e->getMessage()],500);}
