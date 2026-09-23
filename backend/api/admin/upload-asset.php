<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
$admin=require_admin_permission('content.write');
ensure_admin_control_schema();
if(request_method()!=='POST') json_response(['message'=>'POST gerekli.'],405);
if(empty($_FILES['file'])||!is_uploaded_file((string)$_FILES['file']['tmp_name'])) json_response(['message'=>'Yüklenecek dosya seçilmedi.'],400);
$f=$_FILES['file'];
if((int)$f['error']!==UPLOAD_ERR_OK) json_response(['message'=>'Dosya yükleme hatası: '.(int)$f['error']],400);
if((int)$f['size']>250*1024*1024) json_response(['message'=>'Dosya 250 MB sınırını aşıyor.'],413);
$ext=strtolower(pathinfo((string)$f['name'],PATHINFO_EXTENSION));
$allowed=['mp4','webm','vtt','srt','mp3','m4a','aac','wav','png','jpg','jpeg','webp'];
if(!in_array($ext,$allowed,true)) json_response(['message'=>'Bu dosya türüne izin verilmiyor.'],400);
$finfo=new finfo(FILEINFO_MIME_TYPE);$mime=(string)($finfo->file((string)$f['tmp_name'])?:($f['type']??'application/octet-stream'));
try{
  $stored=v14_store_upload($f,'admin-assets','asset_',$ext,$mime);
  v14_queue_media_moderation('admin_asset',0,(int)$admin['id'],(string)$stored['url']);
  // CMS asset kaydı oluşturulduktan sonra gerekirse transcoding kuyruğu asset kimliğiyle tetiklenebilir.
  json_response(['message'=>'Dosya yüklendi.','url'=>$stored['url'],'mimeType'=>$mime,'name'=>basename((string)$stored['key']),'storageProvider'=>$stored['provider']],201);
}catch(Throwable $e){
  json_response(['message'=>app_debug()?'Dosya yüklenemedi: '.$e->getMessage():'Dosya yüklenemedi.'],500);
}
