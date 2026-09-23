<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
$user=require_auth();
if(request_method()!=='POST')json_response(['message'=>'POST gerekli.'],405);
try{
  $body=trim((string)($_POST['body']??''));
  $moderation=v14_moderate_text($body);
  if(!$moderation['allow'])json_response(['message'=>'Gönderi otomatik güvenlik filtresine takıldı.'],422);
  $pollRaw=trim((string)($_POST['poll']??''));$pollJson=null;
  if($pollRaw!==''){
    $poll=json_decode($pollRaw,true);
    if(!is_array($poll)||!isset($poll['question'],$poll['options'])||!is_array($poll['options']))json_response(['message'=>'Anket biçimi geçersiz.'],400);
    $opts=array_values(array_filter(array_map(static fn($v)=>text_substr(trim((string)$v),0,90),$poll['options']),static fn($v)=>$v!==''));
    if(count($opts)<2||count($opts)>6)json_response(['message'=>'Anket 2-6 seçenek içermelidir.'],400);
    $pollJson=json_encode(['question'=>text_substr(trim((string)$poll['question']),0,140),'options'=>$opts],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  }
  if(text_len($body)>1000)json_response(['message'=>'Gönderi en fazla 1000 karakter olabilir.'],400);
  $visibility=strtolower(trim((string)($_POST['visibility']??'public')));if(!in_array($visibility,['public','members'],true))$visibility='public';
  $requiredTier=$visibility==='members'?strtolower(trim((string)($_POST['membershipTier']??'plus'))):null;
  if($requiredTier!==null&&!in_array($requiredTier,['plus','pro','premium'],true))$requiredTier='plus';
  $key=v17_key(['requestId'=>$_POST['requestId']??bin2hex(random_bytes(16))]);$hashes=[];foreach(['image','video'] as $field)if(!empty($_FILES[$field]['tmp_name']))$hashes[$field]=hash_file('sha256',$_FILES[$field]['tmp_name']);$cached=v17_begin((int)$user['id'],$key,'upload.post',[$body,$visibility,$pollJson,$hashes]);if($cached)json_response($cached);
  $imageUrl=null;$videoUrl=null;$media=[];
  if(!empty($_FILES['image']['name'])){
    $f=$_FILES['image'];if(($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)json_response(['message'=>'Görsel yüklenemedi.'],400);if(($f['size']??0)>8*1024*1024)json_response(['message'=>'Görsel en fazla 8 MB olabilir.'],400);
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$a=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];if(!isset($a[$mime]))json_response(['message'=>'Sadece JPG, PNG veya WEBP yüklenebilir.'],400);
    $media=v14_store_upload($f,'feed','feed_'.(int)$user['id'].'_',$a[$mime],$mime);$imageUrl=$media['url'];
  }
  if(!empty($_FILES['video']['name'])){
    $f=$_FILES['video'];if(($f['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)json_response(['message'=>'Video yüklenemedi.'],400);if(($f['size']??0)>80*1024*1024)json_response(['message'=>'Kısa video en fazla 80 MB olabilir.'],400);
    [$mime,$ext]=v17_video_mime($f['tmp_name']);$a=[$mime=>$ext];
    $media=v14_store_upload($f,'feed','post_'.(int)$user['id'].'_',$a[$mime],$mime);$videoUrl=$media['url'];
  }
  if($body===''&&!$imageUrl&&!$videoUrl&&!$pollJson)json_response(['message'=>'Gönderiye metin, görsel, kısa video veya anket eklemelisin.'],400);
  $st=db()->prepare('INSERT INTO feed_posts (user_id,body,image_url,video_url,poll_json,visibility,membership_tier_required) VALUES (?,?,?,?,?,?,?)');
  $st->execute([(int)$user['id'],$body,$imageUrl,$videoUrl,$pollJson,$visibility,$requiredTier]);$postId=(int)db()->lastInsertId();
  if($imageUrl||$videoUrl)v14_queue_media_moderation('feed_post',$postId,(int)$user['id'],$videoUrl?:$imageUrl);
  if($videoUrl)v14_enqueue('media_transcode',['ownerType'=>'feed','ownerId'=>$postId,'inputUrl'=>$videoUrl,'storageKey'=>$media['key']??null],'media');
  v14_record_interaction((int)$user['id'],(int)$user['id'],'feed',$postId,'create',1.5);
  v14_publish('feed.created',['postId'=>$postId,'userId'=>(int)$user['id'],'visibility'=>$visibility],null,null,'feed');
  $response=['message'=>'Gönderi paylaşıldı.','postId'=>$postId,'visibility'=>$visibility];v17_finish((int)$user['id'],$key,$response);json_response($response,201);
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();error_log('[BUFF feed create] '.$e->getMessage());json_response(['message'=>'Gönderi oluşturulamadı: '.$e->getMessage()],500);}
