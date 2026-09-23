<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
$admin=require_admin();
ensure_admin_control_schema();
$type=(string)($_GET['type']??'users');
$permissionMap=[
  'users'=>'users.read','sessions'=>'users.read','memberships'=>'billing.read','user_memberships'=>'billing.read','payments'=>'billing.read',
  'streams'=>'streams.read','stream_keys'=>'streams.read','epg'=>'streams.read','transcoding'=>'content.read','drm'=>'system.read','reports'=>'reports.read','site_complaints'=>'support.read','support'=>'support.read',
  'content_titles'=>'content.read','assets'=>'content.read','seasons'=>'content.read','episodes'=>'content.read','collections'=>'content.read','categories'=>'content.read','vod_clips'=>'content.read',
  'security'=>'system.read','analytics'=>'analytics.read','revenue'=>'analytics.read','roles'=>'roles.read','role_users'=>'roles.read','announcements'=>'notifications.read','settings'=>'system.read','logs'=>'system.read'
];
if(isset($permissionMap[$type]) && !admin_can((int)$admin['id'],$permissionMap[$type])) json_response(['message'=>'Bu modülü görüntüleme yetkin yok.'],403);
$q=trim((string)($_GET['q']??''));
$like='%'.text_substr($q,0,100).'%';
$pdo=db();
$rows=[];
try {
  switch($type){
    case 'users':
      $st=$pdo->prepare("SELECT u.id,u.public_id,u.username,u.email,u.display_name,u.account_mode,u.creator_enabled,u.is_admin,u.account_status,u.wallet_balance,u.creator_earnings,u.created_at,
        COALESCE((SELECT COUNT(*) FROM user_sessions us WHERE us.user_id=u.id AND us.last_seen_at>=NOW()-INTERVAL 30 DAY),0) device_count,
        COALESCE((SELECT COUNT(*) FROM reports r WHERE r.target_user_id=u.id),0) report_count,
        COALESCE((SELECT GROUP_CONCAT(r.name ORDER BY r.id SEPARATOR ', ') FROM admin_role_assignments a JOIN admin_roles r ON r.id=a.role_id WHERE a.user_id=u.id AND (a.expires_at IS NULL OR a.expires_at>NOW())), '') admin_role_summary,
        (SELECT MIN(a.expires_at) FROM admin_role_assignments a WHERE a.user_id=u.id AND a.expires_at>NOW()) admin_expires_at,
        EXISTS(SELECT 1 FROM admin_role_assignments a WHERE a.user_id=u.id AND a.expires_at IS NULL) admin_permanent,
        (SELECT f.vip_tier FROM user_features f WHERE f.user_id=u.id LIMIT 1) vip_tier,
        (SELECT f.vip_until FROM user_features f WHERE f.user_id=u.id LIMIT 1) vip_until
        FROM users u WHERE (?='' OR u.public_id=? OR u.username LIKE ? OR u.email LIKE ? OR u.display_name LIKE ?) ORDER BY u.id DESC LIMIT 200");
      $st->execute([$q,$q,$like,$like,$like]);$rows=$st->fetchAll();break;
    case 'sessions':
      $rows=admin_table_rows('user_sessions',"SELECT s.id,s.user_id,s.ip_address,s.user_agent,s.last_seen_at,s.created_at,u.username,u.display_name FROM user_sessions s JOIN users u ON u.id=s.user_id ORDER BY s.last_seen_at DESC LIMIT 200");break;
    case 'streams':
      $viewerExpr=db_table_exists('stream_viewers')?"(SELECT COUNT(*) FROM stream_viewers sv WHERE sv.stream_id=s.id AND sv.last_seen_at>=NOW()-INTERVAL 20 SECOND)":"0";
      $rows=admin_table_rows('streams',"SELECT s.id,s.title,s.category,s.is_live,s.gift_total,s.started_at,s.ended_at,s.delay_seconds,s.adult_only,u.id user_id,u.username,u.display_name,{$viewerExpr} viewers FROM streams s JOIN users u ON u.id=s.user_id ORDER BY s.is_live DESC,s.updated_at DESC LIMIT 200");break;
    case 'stream_keys':
      $rows=admin_table_rows('stream_keys',"SELECT k.id,k.user_id,k.stream_key,k.status,k.created_at,k.last_used_at,u.username,u.display_name FROM stream_keys k JOIN users u ON u.id=k.user_id ORDER BY k.id DESC LIMIT 200");break;
    case 'epg':
      $rows=admin_table_rows('epg_events',"SELECT e.*,s.title stream_title FROM epg_events e LEFT JOIN streams s ON s.id=e.stream_id ORDER BY e.start_at DESC LIMIT 250");break;
    case 'transcoding':
      $rows=admin_table_rows('transcode_jobs',"SELECT j.*,a.label asset_label FROM transcode_jobs j LEFT JOIN cms_assets a ON a.id=j.asset_id ORDER BY j.id DESC LIMIT 250");break;
    case 'drm':
      $rows=admin_table_rows('drm_policies',"SELECT * FROM drm_policies ORDER BY id DESC LIMIT 200");break;
    case 'reports':
      $rows=admin_table_rows('reports',"SELECT r.*,ru.username reporter_username,COALESCE(tu.username,r.target_username_snapshot) target_username FROM reports r JOIN users ru ON ru.id=r.reporter_user_id LEFT JOIN users tu ON tu.id=r.target_user_id ORDER BY r.id DESC LIMIT 200");break;
    case 'site_complaints':
      $rows=admin_table_rows('support_tickets',"SELECT s.*,u.username,u.display_name FROM support_tickets s JOIN users u ON u.id=s.user_id WHERE s.ticket_type='complaint' ORDER BY s.id DESC LIMIT 200");break;
    case 'support':
      $rows=admin_table_rows('support_tickets',"SELECT s.*,u.username,u.display_name FROM support_tickets s JOIN users u ON u.id=s.user_id WHERE s.ticket_type<>'complaint' ORDER BY s.id DESC LIMIT 200");break;
    case 'payments':
      $rows=admin_table_rows('payment_orders',"SELECT p.*,u.username,u.display_name FROM payment_orders p JOIN users u ON u.id=p.user_id ORDER BY p.id DESC LIMIT 200");break;
    case 'memberships':
      $rows=admin_table_rows('membership_plans',"SELECT * FROM membership_plans ORDER BY price_kurus,id");break;
    case 'user_memberships':
      $rows=admin_table_rows('user_features',"SELECT f.user_id,f.vip_tier,f.vip_until,u.username,u.display_name,u.email FROM user_features f JOIN users u ON u.id=f.user_id WHERE f.vip_until IS NOT NULL ORDER BY f.vip_until DESC LIMIT 200");break;
    case 'content_titles':
      $rows=admin_table_rows('cms_titles',"SELECT * FROM cms_titles WHERE (?='' OR title LIKE ? OR genre LIKE ? OR director LIKE ?) ORDER BY id DESC LIMIT 200",[$q,$like,$like,$like]);break;
    case 'assets':
      $rows=admin_table_rows('cms_assets',"SELECT a.*,t.title FROM cms_assets a LEFT JOIN cms_titles t ON t.id=a.title_id ORDER BY a.id DESC LIMIT 200");break;
    case 'seasons':
      $rows=admin_table_rows('cms_seasons',"SELECT s.*,t.title FROM cms_seasons s JOIN cms_titles t ON t.id=s.title_id ORDER BY t.id DESC,s.season_number");break;
    case 'episodes':
      $rows=admin_table_rows('cms_episodes',"SELECT e.*,s.season_number,t.title series_title FROM cms_episodes e JOIN cms_seasons s ON s.id=e.season_id JOIN cms_titles t ON t.id=s.title_id ORDER BY t.id DESC,s.season_number,e.episode_number LIMIT 300");break;
    case 'collections':
      $rows=admin_table_rows('cms_collections',"SELECT c.*,COUNT(i.id) item_count FROM cms_collections c LEFT JOIN cms_collection_items i ON i.collection_id=c.id GROUP BY c.id ORDER BY c.sort_order,c.id");break;
    case 'vod_clips':
      $parts=[];
      if(db_table_exists('recordings'))$parts[]="SELECT 'vod' kind,r.id,r.title,r.visibility,r.duration_seconds,r.created_at,u.username FROM recordings r JOIN users u ON u.id=r.user_id";
      if(db_table_exists('clips'))$parts[]="SELECT 'clip' kind,c.id,c.title,c.visibility,c.duration_seconds,c.created_at,u.username FROM clips c JOIN users u ON u.id=c.streamer_user_id";
      $rows=$parts?$pdo->query(implode(' UNION ALL ',$parts).' ORDER BY created_at DESC LIMIT 200')->fetchAll():[];break;
    case 'categories':
      $rows=admin_table_rows('categories','SELECT * FROM categories ORDER BY sort_order,id');break;
    case 'roles':
      $rows=admin_table_rows('admin_roles',"SELECT r.*,COUNT(a.id) member_count FROM admin_roles r LEFT JOIN admin_role_assignments a ON a.role_id=r.id GROUP BY r.id ORDER BY r.id");break;
    case 'role_users':
      $rows=admin_table_rows('admin_role_assignments',"SELECT a.id,a.user_id,a.role_id,a.granted_by_user_id,a.expires_at,a.created_at,u.username,u.display_name,u.email,u.account_status,r.code role_code,r.name role_name,gu.username granted_by_username,IF(a.expires_at IS NOT NULL AND a.expires_at<=NOW(),1,0) is_expired FROM admin_role_assignments a JOIN users u ON u.id=a.user_id JOIN admin_roles r ON r.id=a.role_id LEFT JOIN users gu ON gu.id=a.granted_by_user_id ORDER BY a.id DESC");break;
    case 'announcements':
      $rows=admin_table_rows('system_announcements',"SELECT * FROM system_announcements ORDER BY id DESC LIMIT 200");break;
    case 'security':
      $rows=admin_table_rows('ip_blocks',"SELECT * FROM ip_blocks ORDER BY id DESC LIMIT 200");break;
    case 'logs':
      $rows=admin_table_rows('admin_audit_logs',"SELECT l.*,u.username admin_username FROM admin_audit_logs l LEFT JOIN users u ON u.id=l.admin_user_id ORDER BY l.id DESC LIMIT 300");break;
    case 'analytics':
      $rows=[];
      $rows[]=['metric'=>'Toplam kullanıcı','value'=>(int)admin_scalar('users','SELECT COUNT(*) FROM users',0),'detail'=>'Tüm kayıtlı hesaplar'];
      $rows[]=['metric'=>'Aktif yayın','value'=>(int)admin_scalar('streams','SELECT COUNT(*) FROM streams WHERE is_live=1',0),'detail'=>'Şu anda canlı'];
      $rows[]=['metric'=>'Toplam yayın','value'=>(int)admin_scalar('streams','SELECT COUNT(*) FROM streams',0),'detail'=>'Tüm zamanlar'];
      $rows[]=['metric'=>'Toplam izlenme oturumu','value'=>(int)admin_scalar('stream_viewers','SELECT COUNT(*) FROM stream_viewers',0),'detail'=>'Viewer heartbeat kayıtları'];
      $rows[]=['metric'=>'Hazır VOD','value'=>(int)admin_scalar('recordings',"SELECT COUNT(*) FROM recordings WHERE status='ready'",0),'detail'=>'Tekrar izlenebilir kayıtlar'];
      $rows[]=['metric'=>'Açık şikayet','value'=>(int)admin_scalar('reports',"SELECT COUNT(*) FROM reports WHERE status='open'",0),'detail'=>'Moderasyon bekleyen'];
      $rows[]=['metric'=>'Web oturumu','value'=>(int)admin_scalar('user_sessions',"SELECT COUNT(*) FROM user_sessions WHERE LOWER(user_agent) NOT REGEXP 'android|iphone|ipad|smart-tv|smarttv|tizen|webos'",0),'detail'=>'User-Agent sınıflandırması'];
      $rows[]=['metric'=>'Mobil oturum','value'=>(int)admin_scalar('user_sessions',"SELECT COUNT(*) FROM user_sessions WHERE LOWER(user_agent) REGEXP 'android|iphone|ipad'",0),'detail'=>'Android / iOS'];
      $rows[]=['metric'=>'Smart TV oturumu','value'=>(int)admin_scalar('user_sessions',"SELECT COUNT(*) FROM user_sessions WHERE LOWER(user_agent) REGEXP 'smart-tv|smarttv|tizen|webos'",0),'detail'=>'Tizen / webOS / Smart TV'];
      $rows[]=['metric'=>'Aktif VIP','value'=>(int)admin_scalar('user_features',"SELECT COUNT(*) FROM user_features WHERE vip_until>NOW()",0),'detail'=>'Plus / Pro / Premium'];
      $rows[]=['metric'=>'Coğrafi dağılım','value'=>'—','detail'=>'GeoIP sağlayıcısı bağlandığında ülke/şehir kırılımı gösterilir'];
      break;
    case 'revenue':
      $rows=[];
      if(db_table_exists('payment_orders')){
        $rows=$pdo->query("SELECT DATE_FORMAT(COALESCE(paid_at,created_at),'%Y-%m') period,COUNT(*) payment_count,COALESCE(SUM(amount_kurus),0) revenue_kurus FROM payment_orders WHERE status IN ('paid','success') GROUP BY DATE_FORMAT(COALESCE(paid_at,created_at),'%Y-%m') ORDER BY period DESC LIMIT 24")->fetchAll();
      }break;
    case 'settings':
      $rows=admin_table_rows('site_settings','SELECT * FROM site_settings ORDER BY setting_key');break;
    default: json_response(['message'=>'Geçersiz admin kaynağı.'],400);
  }
  json_response(['type'=>$type,'rows'=>$rows]);
}catch(Throwable $e){
  error_log('[BUFF admin list] '.$type.': '.$e->getMessage());
  json_response(['message'=>'Admin modülü yüklenemedi: '.$e->getMessage(),'type'=>$type,'rows'=>[]],500);
}
