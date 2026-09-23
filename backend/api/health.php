<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap.php';
try{
  $pdo=db();ensure_v14_schema($pdo);$dbName=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
  $required=['users','streams','recordings','clips','feed_posts','notifications','shared_sessions','job_queue','media_variants','realtime_events','finance_audit_logs','moderation_queue','user_loyalty','channel_loyalty','predictions','prediction_options','prediction_entries','community_goals','channel_schedule','content_interactions','stream_metric_samples','admin_roles','admin_role_assignments'];
  $tables=[];foreach($required as $t)$tables[$t]=db_table_exists($t);$missing=array_keys(array_filter($tables,static fn($v)=>!$v));
  $redis='fallback-mysql';$r=v14_redis();if($r){try{$redis=$r->ping()?'connected':'unavailable';}catch(Throwable $e){$redis='unavailable';}}
  $storage=v14_storage_config();$storageReady=$storage['provider']==='local'||($storage['endpoint']!==''&&$storage['bucket']!==''&&$storage['access']!==''&&$storage['secret']!=='');
  $ffmpeg=(string)(app_env()['FFMPEG_PATH']??'ffmpeg');
  $queuePending=0;try{$queuePending=(int)$pdo->query("SELECT COUNT(*) FROM job_queue WHERE status='pending'")->fetchColumn();}catch(Throwable $e){}
  $ok=!$missing&&$storageReady;
  json_response(['ok'=>$ok,'version'=>'14.4.0','database'=>'connected','databaseName'=>$dbName,'redis'=>$redis,'storage'=>['provider'=>$storage['provider'],'configured'=>$storageReady],'queue'=>['pending'=>$queuePending],'realtime'=>['nodeUrl'=>(string)(app_env()['REALTIME_PUBLIC_URL']??'http://127.0.0.1:3000/realtime')],'ffmpeg'=>['path'=>$ffmpeg],'tables'=>$tables,'missingTables'=>$missing,'repairHint'=>$ok?null:'KURULUM.bat / VERITABANI_ONAR.bat çalıştır ve .env servis ayarlarını kontrol et.'],$ok?200:500);
}catch(Throwable $e){json_response(['ok'=>false,'version'=>'14.4.0','database'=>'disconnected','message'=>$e->getMessage(),'repairHint'=>'.env veritabanı ayarlarını ve MySQL/MariaDB servisini kontrol et.'],500);} 
