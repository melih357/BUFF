<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
require_post();
$admin=require_admin();
ensure_admin_control_schema();
$pdo=db();
try{
  $d=request_json();
  $action=(string)($d['action']??'');
  $id=(int)($d['id']??0);
  $perm='system.write';
  if(str_starts_with($action,'user_')||in_array($action,['vip_grant','vip_revoke'],true))$perm='users.write';
  elseif(str_starts_with($action,'report_'))$perm='reports.write';
  elseif(str_starts_with($action,'support_'))$perm='support.write';
  elseif(str_starts_with($action,'cms_')||str_starts_with($action,'asset_')||str_starts_with($action,'season_')||str_starts_with($action,'episode_')||str_starts_with($action,'collection_')||str_starts_with($action,'category_')||str_starts_with($action,'content_'))$perm='content.write';
  elseif(str_starts_with($action,'membership_'))$perm='billing.write';
  elseif(str_starts_with($action,'stream_')||str_starts_with($action,'epg_'))$perm='streams.write';
  elseif(str_starts_with($action,'transcode_'))$perm='content.write';
  elseif(str_starts_with($action,'drm_'))$perm='system.write';
  elseif(str_starts_with($action,'ip_block_'))$perm='system.write';
  elseif(str_starts_with($action,'role_')||str_starts_with($action,'admin_'))$perm='roles.write';
  elseif(str_starts_with($action,'announcement_'))$perm='notifications.write';
  if(!admin_can((int)$admin['id'],$perm)) json_response(['message'=>'Bu işlem için yönetici yetkin yok.'],403);
  $targetType=null;
  $auditId=$id>0?$id:null;

  switch($action){
    case 'user_ban':
    case 'user_block':
      $targetType='user';
      if($id<1) json_response(['message'=>'Kullanıcı seçilmedi.'],400);
      if($id===(int)$admin['id']) json_response(['message'=>'Kendi yönetici hesabını banlayamaz veya engelleyemezsin.'],403);
      $st=$pdo->prepare('SELECT id,account_status FROM users WHERE id=? LIMIT 1');$st->execute([$id]);$target=$st->fetch();
      if(!$target) json_response(['message'=>'Kullanıcı bulunamadı.'],404);
      if((string)$target['account_status']!=='active') json_response(['message'=>'Bu hesap zaten aktif değil. Önce Aktifleştir işlemini kullan.'],409);
      $nextStatus=$action==='user_ban'?'banned':'blocked';
      $pdo->prepare('UPDATE users SET account_status=? WHERE id=?')->execute([$nextStatus,$id]);
      $body=$nextStatus==='banned'?'Hesabın yönetim ekibi tarafından banlandı.':'Hesabın yönetim ekibi tarafından askıya alındı.';
      create_notification($id,(int)$admin['id'],'account_action','Hesap durumu güncellendi',$body,'./settings.php');break;
    case 'user_activate':
      $targetType='user';
      if($id<1) json_response(['message'=>'Kullanıcı seçilmedi.'],400);
      if($id===(int)$admin['id']) json_response(['message'=>'Kendi hesabın zaten aktif yönetici oturumu kullanıyor.'],409);
      $st=$pdo->prepare('SELECT id,account_status FROM users WHERE id=? LIMIT 1');$st->execute([$id]);$target=$st->fetch();
      if(!$target) json_response(['message'=>'Kullanıcı bulunamadı.'],404);
      if(!in_array((string)$target['account_status'],['banned','blocked'],true)) json_response(['message'=>'Aktifleştir işlemi yalnızca banlı veya engelli hesaplarda kullanılabilir.'],409);
      $pdo->prepare("UPDATE users SET account_status='active' WHERE id=?")->execute([$id]);
      create_notification($id,(int)$admin['id'],'account_action','Hesabın yeniden aktif','BUFF hesabın yeniden aktifleştirildi.','./profile.php');break;
    case 'stream_stop':
      $targetType='stream';
      $st=$pdo->prepare('SELECT user_id,title FROM streams WHERE id=? LIMIT 1');$st->execute([$id]);$stream=$st->fetch();
      $pdo->prepare('UPDATE streams SET is_live=0,ended_at=COALESCE(ended_at,NOW()),updated_at=NOW() WHERE id=?')->execute([$id]);
      if($stream)create_notification((int)$stream['user_id'],(int)$admin['id'],'stream_action','Yayın yönetim tarafından kapatıldı',(string)$stream['title'].' yayını yönetim ekibi tarafından sonlandırıldı.','./studio.php');
      break;
    case 'report_reply':
      $targetType='report';$reply=text_substr(trim((string)($d['reply']??'')),0,2000);if(text_len($reply)<2)json_response(['message'=>'Yanıt metni gerekli.'],400);
      $st=$pdo->prepare('SELECT reporter_user_id FROM reports WHERE id=? LIMIT 1');$st->execute([$id]);$report=$st->fetch();if(!$report)json_response(['message'=>'Şikayet bulunamadı.'],404);
      $pdo->prepare("UPDATE reports SET admin_reply=?,replied_at=NOW(),replied_by_user_id=?,status='resolved',resolved_at=NOW() WHERE id=?")->execute([$reply,(int)$admin['id'],$id]);
      create_notification((int)$report['reporter_user_id'],(int)$admin['id'],'report_reply','Şikayetine yanıt geldi',$reply,'./settings.php?focus=report-'.$id.'#support');break;
    case 'report_resolve':
      $targetType='report';$pdo->prepare("UPDATE reports SET status='resolved',resolved_at=NOW() WHERE id=?")->execute([$id]);break;
    case 'report_reopen':
      $targetType='report';$pdo->prepare("UPDATE reports SET status='open',resolved_at=NULL WHERE id=?")->execute([$id]);break;
    case 'support_reply':
      $targetType='support_ticket';$reply=text_substr(trim((string)($d['reply']??'')),0,2000);if(text_len($reply)<2)json_response(['message'=>'Yanıt metni gerekli.'],400);
      $st=$pdo->prepare('SELECT user_id,subject,ticket_type FROM support_tickets WHERE id=? LIMIT 1');$st->execute([$id]);$ticket=$st->fetch();if(!$ticket)json_response(['message'=>'Destek kaydı bulunamadı.'],404);
      $pdo->prepare("UPDATE support_tickets SET admin_reply=?,replied_at=NOW(),replied_by_user_id=?,status='closed',updated_at=NOW() WHERE id=?")->execute([$reply,(int)$admin['id'],$id]);
      $title=((string)$ticket['ticket_type']==='complaint')?'Site şikayetine yanıt geldi':'Destek talebine yanıt geldi';
      create_notification((int)$ticket['user_id'],(int)$admin['id'],'support_reply',$title,$reply,'./settings.php?focus=support-'.$id.'#support');break;
    case 'support_close':
      $targetType='support_ticket';$pdo->prepare("UPDATE support_tickets SET status='closed',updated_at=NOW() WHERE id=?")->execute([$id]);break;

    case 'cms_title_create':
      $targetType='cms_title';$title=text_substr(trim((string)($d['title']??'')),0,180);if($title==='')json_response(['message'=>'Başlık gerekli.'],400);
      $type=(string)($d['contentType']??'film');if(!in_array($type,['film','series','live'],true))$type='film';
      $status=(string)($d['status']??'draft');if(!in_array($status,['draft','published','archived'],true))$status='draft';
      $pdo->prepare('INSERT INTO cms_titles (content_type,title,description,genre,cast_text,director,age_rating,poster_url,trailer_url,status) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([$type,$title,text_substr((string)($d['description']??''),0,5000),text_substr((string)($d['genre']??''),0,120),text_substr((string)($d['cast']??''),0,3000),text_substr((string)($d['director']??''),0,160),text_substr((string)($d['ageRating']??''),0,20),text_substr((string)($d['posterUrl']??''),0,500),text_substr((string)($d['trailerUrl']??''),0,500),$status]);
      $auditId=(int)$pdo->lastInsertId();break;
    case 'cms_title_delete':$targetType='cms_title';$pdo->prepare('DELETE FROM cms_titles WHERE id=?')->execute([$id]);break;
    case 'cms_title_publish':$targetType='cms_title';$pdo->prepare("UPDATE cms_titles SET status=IF(status='published','draft','published') WHERE id=?")->execute([$id]);break;
    case 'asset_create':
      $targetType='cms_asset';$url=text_substr(trim((string)($d['fileUrl']??'')),0,700);if($url==='')json_response(['message'=>'Dosya URL/yolu gerekli.'],400);
      $pdo->prepare('INSERT INTO cms_assets (title_id,asset_type,label,language_code,file_url,mime_type) VALUES (NULLIF(?,0),?,?,?,?,?)')->execute([(int)($d['titleId']??0),text_substr((string)($d['assetType']??'video'),0,30),text_substr((string)($d['label']??''),0,140),text_substr((string)($d['languageCode']??''),0,12),$url,text_substr((string)($d['mimeType']??''),0,100)]);$auditId=(int)$pdo->lastInsertId();break;
    case 'asset_delete':$targetType='cms_asset';$pdo->prepare('DELETE FROM cms_assets WHERE id=?')->execute([$id]);break;
    case 'season_create':
      $targetType='cms_season';$titleId=(int)($d['titleId']??0);$num=max(1,(int)($d['seasonNumber']??1));if($titleId<1)json_response(['message'=>'Dizi seçimi gerekli.'],400);
      $pdo->prepare('INSERT INTO cms_seasons (title_id,season_number,name) VALUES (?,?,?)')->execute([$titleId,$num,text_substr((string)($d['name']??('Sezon '.$num)),0,140)]);$auditId=(int)$pdo->lastInsertId();break;
    case 'season_delete':$targetType='cms_season';$pdo->prepare('DELETE FROM cms_seasons WHERE id=?')->execute([$id]);break;
    case 'episode_create':
      $targetType='cms_episode';$seasonId=(int)($d['seasonId']??0);$num=max(1,(int)($d['episodeNumber']??1));$title=text_substr(trim((string)($d['title']??'')),0,180);if($seasonId<1||$title==='')json_response(['message'=>'Sezon ve bölüm başlığı gerekli.'],400);
      $pdo->prepare('INSERT INTO cms_episodes (season_id,episode_number,title,description,video_url,duration_seconds,status) VALUES (?,?,?,?,?,?,?)')->execute([$seasonId,$num,$title,text_substr((string)($d['description']??''),0,5000),text_substr((string)($d['videoUrl']??''),0,700),max(0,(int)($d['durationSeconds']??0)),(string)($d['status']??'draft')]);$auditId=(int)$pdo->lastInsertId();break;
    case 'episode_delete':$targetType='cms_episode';$pdo->prepare('DELETE FROM cms_episodes WHERE id=?')->execute([$id]);break;
    case 'collection_create':
      $targetType='cms_collection';$name=text_substr(trim((string)($d['name']??'')),0,120);if($name==='')json_response(['message'=>'Koleksiyon adı gerekli.'],400);$slug=strtolower(trim((string)preg_replace('/[^a-zA-Z0-9]+/','-',$name),'-')).'-'.substr(bin2hex(random_bytes(3)),0,6);
      $pdo->prepare('INSERT INTO cms_collections (name,slug,collection_type,sort_order,is_active) VALUES (?,?,?,?,1)')->execute([$name,$slug,text_substr((string)($d['collectionType']??'manual'),0,30),max(0,(int)($d['sortOrder']??100))]);$auditId=(int)$pdo->lastInsertId();break;
    case 'collection_toggle':$targetType='cms_collection';$pdo->prepare('UPDATE cms_collections SET is_active=IF(is_active=1,0,1) WHERE id=?')->execute([$id]);break;
    case 'collection_reorder':
      $targetType='cms_collection';$order=$d['order']??[];if(!is_array($order))json_response(['message'=>'Sıralama verisi geçersiz.'],400);$pos=10;foreach($order as $collectionId){$collectionId=(int)$collectionId;if($collectionId>0){$pdo->prepare('UPDATE cms_collections SET sort_order=? WHERE id=?')->execute([$pos,$collectionId]);$pos+=10;}}$auditId=null;break;
    case 'collection_delete':$targetType='cms_collection';$pdo->prepare('DELETE FROM cms_collections WHERE id=?')->execute([$id]);break;

    case 'membership_save':
      $targetType='membership_plan';$code=preg_replace('/[^a-z0-9_-]/','',strtolower((string)($d['code']??'')));$name=text_substr(trim((string)($d['name']??'')),0,100);if($code===''||$name==='')json_response(['message'=>'Paket kodu ve adı gerekli.'],400);
      $pdo->prepare('INSERT INTO membership_plans (code,name,price_kurus,billing_period,quality,ads_enabled,family_slots,is_active) VALUES (?,?,?,?,?,?,?,1) ON DUPLICATE KEY UPDATE name=VALUES(name),price_kurus=VALUES(price_kurus),billing_period=VALUES(billing_period),quality=VALUES(quality),ads_enabled=VALUES(ads_enabled),family_slots=VALUES(family_slots)')->execute([$code,$name,max(0,(int)($d['priceKurus']??0)),(string)($d['billingPeriod']??'monthly'),text_substr((string)($d['quality']??'HD'),0,20),!empty($d['adsEnabled'])?1:0,max(1,(int)($d['familySlots']??1))]);break;
    case 'membership_toggle':$targetType='membership_plan';$pdo->prepare('UPDATE membership_plans SET is_active=IF(is_active=1,0,1) WHERE id=?')->execute([$id]);break;
    case 'vip_grant':
      $targetType='user';
      if($id<1||!fetch_user_by_id($id)) json_response(['message'=>'Kullanıcı bulunamadı.'],404);
      $tier=strtolower((string)($d['tier']??''));
      if(!in_array($tier,['plus','pro','premium'],true)) json_response(['message'=>'Üyelik seviyesi Plus, Pro veya Premium olmalı.'],400);
      $days=max(1,min(3650,(int)($d['days']??30)));$until=(new DateTimeImmutable('now'))->modify('+'.$days.' days')->format('Y-m-d H:i:s');
      $pdo->prepare('INSERT INTO user_features (user_id,vip_until,vip_tier) VALUES (?,?,?) ON DUPLICATE KEY UPDATE vip_until=VALUES(vip_until),vip_tier=VALUES(vip_tier)')->execute([$id,$until,$tier]);
      create_notification($id,(int)$admin['id'],'membership','Üyeliğin güncellendi',strtoupper($tier).' üyeliği '.$days.' gün süreyle hesabına tanımlandı.','./settings.php#membership');break;
    case 'vip_revoke':
      $targetType='user';
      if($id<1||!fetch_user_by_id($id)) json_response(['message'=>'Kullanıcı bulunamadı.'],404);
      $pdo->prepare('UPDATE user_features SET vip_until=NULL,vip_tier=NULL WHERE user_id=?')->execute([$id]);
      create_notification($id,(int)$admin['id'],'membership','Üyeliğin güncellendi','Yönetici tarafından VIP üyelik kaldırıldı.','./settings.php#membership');break;

    case 'stream_key_generate':
      $targetType='stream_key';$userId=function_exists('v16_resolve_user_id')?v16_resolve_user_id($d['userId']??$id):(int)($d['userId']??$id);if($userId<1)json_response(['message'=>'Kullanıcı gerekli.'],400);$key='buff_'.bin2hex(random_bytes(24));$pdo->prepare('INSERT INTO stream_keys (user_id,stream_key,status) VALUES (?,? ,\'active\')')->execute([$userId,$key]);$auditId=(int)$pdo->lastInsertId();json_response(['message'=>'Stream key üretildi.','streamKey'=>$key]);
    case 'stream_key_revoke':$targetType='stream_key';$pdo->prepare("UPDATE stream_keys SET status='revoked' WHERE id=?")->execute([$id]);break;
    case 'epg_create':
      $targetType='epg_event';$channel=text_substr(trim((string)($d['channelName']??'')),0,120);$title=text_substr(trim((string)($d['title']??'')),0,180);$start=(string)($d['startAt']??'');$end=(string)($d['endAt']??'');if($channel===''||$title===''||$start===''||$end==='')json_response(['message'=>'Kanal, program, başlangıç ve bitiş gerekli.'],400);$pdo->prepare('INSERT INTO epg_events (stream_id,channel_name,title,start_at,end_at,status) VALUES (NULLIF(?,0),?,?,?,?,?)')->execute([(int)($d['streamId']??0),$channel,$title,$start,$end,(string)($d['status']??'scheduled')]);$auditId=(int)$pdo->lastInsertId();break;
    case 'epg_delete':$targetType='epg_event';$pdo->prepare('DELETE FROM epg_events WHERE id=?')->execute([$id]);break;
    case 'transcode_create':
      $targetType='transcode_job';$input=text_substr(trim((string)($d['inputUrl']??'')),0,700);if($input==='')json_response(['message'=>'Giriş video yolu gerekli.'],400);$pdo->prepare("INSERT INTO transcode_jobs (asset_id,input_url,output_profile,status,progress) VALUES (NULLIF(?,0),?,?,'queued',0)")->execute([(int)($d['assetId']??0),$input,text_substr((string)($d['outputProfile']??'HLS_AUTO'),0,80)]);$auditId=(int)$pdo->lastInsertId();break;
    case 'transcode_status':
      $targetType='transcode_job';$status=(string)($d['status']??'queued');if(!in_array($status,['queued','processing','completed','failed'],true))$status='queued';$progress=max(0,min(100,(int)($d['progress']??0)));$pdo->prepare('UPDATE transcode_jobs SET status=?,progress=? WHERE id=?')->execute([$status,$progress,$id]);break;
    case 'drm_save':
      $targetType='drm_policy';$name=text_substr(trim((string)($d['name']??'')),0,120);if($name==='')json_response(['message'=>'Politika adı gerekli.'],400);$pdo->prepare('INSERT INTO drm_policies (name,widevine_enabled,fairplay_enabled,geo_mode,allowed_countries,blocked_countries,status) VALUES (?,?,?,?,?,?,?)')->execute([$name,!empty($d['widevineEnabled'])?1:0,!empty($d['fairplayEnabled'])?1:0,text_substr((string)($d['geoMode']??'off'),0,20),text_substr((string)($d['allowedCountries']??''),0,500),text_substr((string)($d['blockedCountries']??''),0,500),(string)($d['status']??'draft')]);$auditId=(int)$pdo->lastInsertId();break;
    case 'drm_toggle':$targetType='drm_policy';$pdo->prepare("UPDATE drm_policies SET status=IF(status='active','draft','active') WHERE id=?")->execute([$id]);break;
    case 'drm_delete':$targetType='drm_policy';$pdo->prepare('DELETE FROM drm_policies WHERE id=?')->execute([$id]);break;
    case 'ip_block_create':
      $targetType='ip_block';$ip=text_substr(trim((string)($d['ipAddress']??'')),0,64);if($ip==='')json_response(['message'=>'IP adresi gerekli.'],400);$pdo->prepare('INSERT INTO ip_blocks (ip_address,reason,is_active,created_by_user_id) VALUES (?,?,1,?) ON DUPLICATE KEY UPDATE reason=VALUES(reason),is_active=1')->execute([$ip,text_substr((string)($d['reason']??''),0,255),(int)$admin['id']]);break;
    case 'ip_block_toggle':$targetType='ip_block';$pdo->prepare('UPDATE ip_blocks SET is_active=IF(is_active=1,0,1) WHERE id=?')->execute([$id]);break;
    case 'ip_block_delete':$targetType='ip_block';$pdo->prepare('DELETE FROM ip_blocks WHERE id=?')->execute([$id]);break;

    case 'admin_grant':
      $targetType='admin_role';$userId=$id>0?$id:(function_exists('v16_resolve_user_id')?v16_resolve_user_id($d['userId']??0):(int)($d['userId']??0));
      if($userId<1) json_response(['message'=>'Kullanıcı gerekli.'],400);
      if($userId===(int)$admin['id']) json_response(['message'=>'Kendi yönetici yetkini bu ekrandan değiştiremezsin.'],403);
      $st=$pdo->prepare('SELECT id,account_status FROM users WHERE id=? LIMIT 1');$st->execute([$userId]);$target=$st->fetch();if(!$target)json_response(['message'=>'Kullanıcı bulunamadı.'],404);
      if((string)$target['account_status']!=='active')json_response(['message'=>'Banlı veya engelli hesaba admin yetkisi verilemez. Önce hesabı aktifleştir.'],409);
      $roleCode=(string)($d['roleCode']??'super_admin');$allowedRoles=['super_admin','content_manager','editor','support_agent'];if(!in_array($roleCode,$allowedRoles,true))json_response(['message'=>'Geçersiz yönetici rolü.'],400);
      $duration=(string)($d['duration']??'permanent');$durationMap=['1h'=>'+1 hour','3h'=>'+3 hours','1d'=>'+1 day','7d'=>'+7 days','permanent'=>null];if(!array_key_exists($duration,$durationMap))json_response(['message'=>'Geçersiz yetki süresi.'],400);
      $expiresAt=$durationMap[$duration]===null?null:(new DateTimeImmutable('now'))->modify($durationMap[$duration])->format('Y-m-d H:i:s');
      $roleSt=$pdo->prepare('SELECT id,name FROM admin_roles WHERE code=? LIMIT 1');$roleSt->execute([$roleCode]);$role=$roleSt->fetch();if(!$role)json_response(['message'=>'Admin rolü bulunamadı.'],404);
      $pdo->prepare('UPDATE users SET is_admin=1 WHERE id=?')->execute([$userId]);
      $pdo->prepare('INSERT INTO admin_role_assignments (user_id,role_id,granted_by_user_id,expires_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE granted_by_user_id=VALUES(granted_by_user_id),expires_at=VALUES(expires_at),created_at=CURRENT_TIMESTAMP')->execute([$userId,(int)$role['id'],(int)$admin['id'],$expiresAt]);
      $expiryText=$expiresAt?'Yetki bitişi: '.$expiresAt:'Yetki süresiz tanımlandı.';
      create_notification($userId,(int)$admin['id'],'admin_role','Yönetici yetkisi verildi',(string)$role['name'].' rolü tanımlandı. '.$expiryText,'./admin.php');
      $auditId=$userId;break;
    case 'admin_revoke':
      $targetType='admin_role';$userId=$id>0?$id:(function_exists('v16_resolve_user_id')?v16_resolve_user_id($d['userId']??0):(int)($d['userId']??0));
      if($userId<1)json_response(['message'=>'Kullanıcı gerekli.'],400);
      if($userId===(int)$admin['id'])json_response(['message'=>'Kendi yönetici yetkini kaldıramazsın.'],403);
      $pdo->prepare('DELETE FROM admin_role_assignments WHERE user_id=?')->execute([$userId]);
      $pdo->prepare('UPDATE users SET is_admin=0 WHERE id=?')->execute([$userId]);
      create_notification($userId,(int)$admin['id'],'admin_role','Yönetici yetkisi kaldırıldı','BUFF yönetim paneli erişimin kaldırıldı.','./index.php');
      $auditId=$userId;break;
    case 'role_assign':
      $targetType='admin_role';$userId=function_exists('v16_resolve_user_id')?v16_resolve_user_id($d['userId']??0):(int)($d['userId']??0);$roleId=(int)($d['roleId']??0);if($userId<1||$roleId<1)json_response(['message'=>'Kullanıcı ve rol gerekli.'],400);
      if($userId===(int)$admin['id'])json_response(['message'=>'Kendi rolünü bu ekrandan değiştiremezsin.'],403);
      $duration=(string)($d['duration']??'permanent');$durationMap=['1h'=>'+1 hour','3h'=>'+3 hours','1d'=>'+1 day','7d'=>'+7 days','permanent'=>null];if(!array_key_exists($duration,$durationMap))$duration='permanent';$expiresAt=$durationMap[$duration]===null?null:(new DateTimeImmutable('now'))->modify($durationMap[$duration])->format('Y-m-d H:i:s');
      $st=$pdo->prepare('SELECT account_status FROM users WHERE id=? LIMIT 1');$st->execute([$userId]);$status=$st->fetchColumn();if($status===false)json_response(['message'=>'Kullanıcı bulunamadı.'],404);if((string)$status!=='active')json_response(['message'=>'Aktif olmayan hesaba rol atanamaz.'],409);
      $pdo->prepare('UPDATE users SET is_admin=1 WHERE id=?')->execute([$userId]);$pdo->prepare('INSERT INTO admin_role_assignments (user_id,role_id,granted_by_user_id,expires_at) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE granted_by_user_id=VALUES(granted_by_user_id),expires_at=VALUES(expires_at),created_at=CURRENT_TIMESTAMP')->execute([$userId,$roleId,(int)$admin['id'],$expiresAt]);break;
    case 'role_unassign':
      $targetType='admin_role';$st=$pdo->prepare('SELECT user_id FROM admin_role_assignments WHERE id=? LIMIT 1');$st->execute([$id]);$roleUserId=(int)($st->fetchColumn()?:0);if($roleUserId<1)json_response(['message'=>'Rol ataması bulunamadı.'],404);if($roleUserId===(int)$admin['id'])json_response(['message'=>'Kendi yönetici rolünü kaldıramazsın.'],403);$pdo->prepare('DELETE FROM admin_role_assignments WHERE id=?')->execute([$id]);if(!admin_has_active_assignment($roleUserId))$pdo->prepare('UPDATE users SET is_admin=0 WHERE id=?')->execute([$roleUserId]);break;
    case 'announcement_send':
      $targetType='announcement';$title=text_substr(trim((string)($d['title']??'')),0,140);$body=text_substr(trim((string)($d['body']??'')),0,1000);if($title===''||$body==='')json_response(['message'=>'Duyuru başlığı ve metni gerekli.'],400);
      $scope=(string)($d['targetScope']??'all');$pdo->prepare('INSERT INTO system_announcements (title,body,channel,target_scope,created_by_user_id) VALUES (?,?,?,?,?)')->execute([$title,$body,'in_app',$scope,(int)$admin['id']]);$auditId=(int)$pdo->lastInsertId();
      $sql="SELECT id FROM users WHERE is_admin=0";$args=[];if($scope==='creators')$sql.=" AND creator_enabled=1";elseif($scope==='vip')$sql.=" AND id IN (SELECT user_id FROM user_features WHERE vip_until>NOW())";
      v14_enqueue('broadcast_notification',['announcementId'=>$auditId,'actorUserId'=>(int)$admin['id'],'title'=>$title,'body'=>$body,'targetScope'=>$scope],'notifications');
      break;
    case 'announcement_toggle':$targetType='announcement';$pdo->prepare('UPDATE system_announcements SET is_active=IF(is_active=1,0,1) WHERE id=?')->execute([$id]);break;

    case 'content_visibility':
      $kind=(string)($d['kind']??'');$visibility=(string)($d['visibility']??'public');if(!in_array($visibility,['public','unlisted','private'],true))$visibility='private';
      if($kind==='vod'){$targetType='recording';$pdo->prepare('UPDATE recordings SET visibility=? WHERE id=?')->execute([$visibility,$id]);}
      elseif($kind==='clip'){$targetType='clip';$pdo->prepare('UPDATE clips SET visibility=? WHERE id=?')->execute([$visibility,$id]);}
      else json_response(['message'=>'Geçersiz içerik türü.'],400);break;
    case 'content_delete':
      $kind=(string)($d['kind']??'');if($kind==='vod'){$targetType='recording';$pdo->prepare('DELETE FROM recordings WHERE id=?')->execute([$id]);}elseif($kind==='clip'){$targetType='clip';$pdo->prepare('DELETE FROM clips WHERE id=?')->execute([$id]);}else json_response(['message'=>'Geçersiz içerik türü.'],400);break;
    case 'category_toggle':$targetType='category';$pdo->prepare('UPDATE categories SET is_active=IF(is_active=1,0,1) WHERE id=?')->execute([$id]);break;
    case 'category_delete':$targetType='category';$pdo->prepare('DELETE FROM categories WHERE id=?')->execute([$id]);break;
    case 'category_create':
      $targetType='category';$name=text_substr(trim((string)($d['name']??'')),0,80);if($name==='')json_response(['message'=>'Kategori adı gerekli.'],400);$slug=strtolower(trim((string)preg_replace('/[^a-zA-Z0-9]+/','-',$name),'-'));if($slug==='')$slug='kategori-'.time();$pdo->prepare('INSERT INTO categories (name,slug,icon,sort_order) VALUES (?,?,?,?)')->execute([$name,$slug,text_substr((string)($d['icon']??'✦'),0,12),max(0,(int)($d['sortOrder']??99))]);$auditId=(int)$pdo->lastInsertId();break;
    case 'setting_update':
      $targetType='site_setting';$key=trim((string)($d['key']??''));$value=(string)($d['value']??'');if(!preg_match('/^[a-z0-9_]{2,80}$/',$key))json_response(['message'=>'Geçersiz ayar anahtarı.'],400);$pdo->prepare('INSERT INTO site_settings (setting_key,setting_value,setting_type,is_public,updated_by_user_id) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),setting_type=VALUES(setting_type),is_public=VALUES(is_public),updated_by_user_id=VALUES(updated_by_user_id)')->execute([$key,$value,(string)($d['settingType']??'string'),!empty($d['isPublic'])?1:0,(int)$admin['id']]);$auditId=null;break;
    default: json_response(['message'=>'Bilinmeyen admin aksiyonu.'],400);
  }
  admin_audit((int)$admin['id'],$action,$targetType,$auditId,$d);
  json_response(['message'=>'İşlem tamamlandı.']);
}catch(Throwable $e){
  if($pdo->inTransaction())$pdo->rollBack();
  error_log('[BUFF admin action] '.$action.': '.$e->getMessage());
  json_response(['message'=>$e->getMessage()?:'Admin işlemi başarısız.'],500);
}
