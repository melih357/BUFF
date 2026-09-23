<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
$user = require_auth();
if (request_method() !== 'POST') json_response(['message' => 'POST gerekli.'], 405);
try {
    if (empty($_FILES['audio']['name'])) json_response(['message' => 'Ses dosyası seçilmedi.'], 400);
    $file = $_FILES['audio'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) json_response(['message' => 'Ses dosyası yüklenemedi.'], 400);
    if (($file['size'] ?? 0) > 8 * 1024 * 1024) json_response(['message' => 'Ses dosyası en fazla 8 MB olabilir.'], 400);
    $finfo = new finfo(FILEINFO_MIME_TYPE);$mime=(string)$finfo->file((string)$file['tmp_name']);
    $allowed=['audio/mpeg'=>'mp3','audio/wav'=>'wav','audio/x-wav'=>'wav','audio/ogg'=>'ogg','audio/webm'=>'webm','audio/mp4'=>'m4a','audio/x-m4a'=>'m4a'];
    if (!isset($allowed[$mime])) json_response(['message'=>'MP3, WAV, OGG, WEBM veya M4A yükleyebilirsin.'],400);
    $original=preg_replace('/[^\pL\pN._ -]+/u','',basename((string)$file['name'])) ?: 'ses.'.$allowed[$mime];
    $stored=v14_store_upload($file,'gift-audio','gift_'.(int)$user['id'].'_',$allowed[$mime],$mime);
    v14_queue_media_moderation('gift_audio',0,(int)$user['id'],(string)$stored['url']);
    json_response(['audioUrl'=>$stored['url'],'audioName'=>text_substr($original,0,120),'storageProvider'=>$stored['provider']]);
} catch (Throwable $e) { json_response(['message'=>app_debug()?'Ses dosyası yüklenemedi: '.$e->getMessage():'Ses dosyası yüklenemedi.'],500); }
