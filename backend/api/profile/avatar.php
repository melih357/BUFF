<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
$user = require_auth();
if (request_method() !== 'POST') json_response(['message'=>'Bu işlem POST isteği gerektirir.'],405);
try {
    if (!isset($_FILES['avatar']) || !is_array($_FILES['avatar'])) json_response(['message'=>'Profil fotoğrafı seçilmedi.'],400);
    $file=$_FILES['avatar'];
    if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) json_response(['message'=>'Dosya yüklenemedi.'],400);
    if ((int)($file['size']??0)>3*1024*1024) json_response(['message'=>'Profil fotoğrafı en fazla 3 MB olabilir.'],400);
    $tmp=(string)$file['tmp_name'];$info=@getimagesize($tmp);if(!$info)json_response(['message'=>'Geçerli bir görsel yükleyin.'],400);
    $mime=(string)($info['mime']??'');$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!isset($allowed[$mime]))json_response(['message'=>'Yalnızca JPG, PNG veya WEBP yüklenebilir.'],400);
    $stored=v14_store_upload($file,'avatars','avatar_'.(int)$user['id'].'_',$allowed[$mime],$mime);
    db()->prepare('UPDATE users SET avatar_url=? WHERE id=?')->execute([$stored['url'],(int)$user['id']]);
    v14_queue_media_moderation('avatar',(int)$user['id'],(int)$user['id'],$stored['url']);
    v14_publish('profile.avatar.updated',['avatarUrl'=>$stored['url']],(int)$user['id'],null,'user.'.(int)$user['id']);
    json_response(['message'=>'Profil fotoğrafı güncellendi.','user'=>public_user(fetch_user_by_id((int)$user['id']))]);
} catch(Throwable $e){error_log('[BUFF avatar] '.$e->getMessage());json_response(['message'=>'Profil fotoğrafı güncellenemedi.'],500);}
