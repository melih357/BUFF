<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
$admin=require_admin();
ensure_admin_control_schema();
try {
    $metrics = [
      'users'=>(int)admin_scalar('users','SELECT COUNT(*) FROM users',0),
      'liveStreams'=>(int)admin_scalar('streams','SELECT COUNT(*) FROM streams WHERE is_live=1',0),
      'activeUsers'=>(int)admin_scalar('user_sessions',"SELECT COUNT(DISTINCT user_id) FROM user_sessions WHERE last_seen_at>=NOW()-INTERVAL 15 MINUTE",0),
      'openReports'=>(int)admin_scalar('reports',"SELECT COUNT(*) FROM reports WHERE status='open'",0),
      'openSupport'=>(int)admin_scalar('support_tickets',"SELECT COUNT(*) FROM support_tickets WHERE status='open'",0),
      'todayRevenueKurus'=>(int)admin_scalar('payment_orders',"SELECT COALESCE(SUM(amount_kurus),0) FROM payment_orders WHERE status IN ('paid','success') AND DATE(COALESCE(paid_at,created_at))=CURDATE()",0),
      'recordings'=>(int)admin_scalar('recordings',"SELECT COUNT(*) FROM recordings WHERE status='ready'",0),
      'cmsTitles'=>(int)admin_scalar('cms_titles','SELECT COUNT(*) FROM cms_titles',0),
    ];
    $recentReports=admin_table_rows('reports',"SELECT r.id,r.report_scope,r.category,r.status,r.created_at,r.admin_reply,ru.username reporter,COALESCE(tu.username,r.target_username_snapshot) target FROM reports r JOIN users ru ON ru.id=r.reporter_user_id LEFT JOIN users tu ON tu.id=r.target_user_id ORDER BY r.id DESC LIMIT 6");
    $recentSupport=admin_table_rows('support_tickets',"SELECT s.id,s.ticket_type,s.subject,s.status,s.created_at,s.admin_reply,u.username FROM support_tickets s JOIN users u ON u.id=s.user_id ORDER BY s.id DESC LIMIT 6");
    $topStreams=admin_table_rows('streams',"SELECT s.id,s.title,s.is_live,s.gift_total,s.started_at,u.username,u.display_name FROM streams s JOIN users u ON u.id=s.user_id ORDER BY s.is_live DESC,s.gift_total DESC,s.id DESC LIMIT 6");
    $rootPath=dirname(__DIR__,3);
    $health=[
      'database'=>'ok','php'=>PHP_VERSION,
      'serverTime'=>date('Y-m-d H:i:s'),
      'environment'=>(string)(app_env()['APP_ENV']??'local'),
      'memoryUsageMb'=>round(memory_get_usage(true)/1048576,1),
      'diskFreeGb'=>@disk_free_space($rootPath)!==false?round((float)disk_free_space($rootPath)/1073741824,1):null,
      'cdn'=>'not_configured',
      'tables'=>[
        'users'=>db_table_exists('users'),'streams'=>db_table_exists('streams'),'reports'=>db_table_exists('reports'),
        'support'=>db_table_exists('support_tickets'),'notifications'=>db_table_exists('notifications')
      ]
    ];
    json_response(compact('metrics','recentReports','recentSupport','topStreams','health'));
} catch(Throwable $e){
    error_log('[BUFF admin overview] '.$e->getMessage());
    json_response(['message'=>'Dashboard yüklenemedi: '.$e->getMessage()],500);
}
