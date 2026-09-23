<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap.php';
header('Content-Type: text/plain; version=0.0.4; charset=utf-8');
try{
  $pdo=db();
  $safe=function(string $sql) use($pdo): float {try{return (float)$pdo->query($sql)->fetchColumn();}catch(Throwable $e){return 0.0;}};
  $activeStreams=$safe("SELECT COUNT(*) FROM streams WHERE is_live=1");
  $activeViewers=$safe("SELECT COUNT(*) FROM stream_viewers WHERE last_seen_at>=NOW()-INTERVAL 20 SECOND");
  $pendingJobs=$safe("SELECT COUNT(*) FROM job_queue WHERE status='pending'");
  $failedJobs=$safe("SELECT COUNT(*) FROM job_queue WHERE status='failed'");
  $openReports=$safe("SELECT COUNT(*) FROM reports WHERE status IN ('open','pending')");
  $users=$safe("SELECT COUNT(*) FROM users WHERE account_status='active'");
  $latency=$safe("SELECT COALESCE(AVG(duration_ms),0) FROM api_telemetry WHERE created_at>=NOW()-INTERVAL 5 MINUTE");
  $errors=$safe("SELECT COUNT(*) FROM api_telemetry WHERE status_code>=500 AND created_at>=NOW()-INTERVAL 5 MINUTE");
  $lines=[
    '# HELP buff_php_active_streams Active live streams','# TYPE buff_php_active_streams gauge','buff_php_active_streams '.$activeStreams,
    '# HELP buff_php_active_viewers Active viewers seen in last 20 seconds','# TYPE buff_php_active_viewers gauge','buff_php_active_viewers '.$activeViewers,
    '# HELP buff_php_active_users Active accounts','# TYPE buff_php_active_users gauge','buff_php_active_users '.$users,
    '# HELP buff_php_job_queue_pending Pending async jobs','# TYPE buff_php_job_queue_pending gauge','buff_php_job_queue_pending '.$pendingJobs,
    '# HELP buff_php_job_queue_failed Failed async jobs','# TYPE buff_php_job_queue_failed gauge','buff_php_job_queue_failed '.$failedJobs,
    '# HELP buff_php_open_reports Open moderation reports','# TYPE buff_php_open_reports gauge','buff_php_open_reports '.$openReports,
    '# HELP buff_php_api_avg_duration_ms Average API duration in last 5 minutes','# TYPE buff_php_api_avg_duration_ms gauge','buff_php_api_avg_duration_ms '.$latency,
    '# HELP buff_php_api_5xx_5m API 5xx count in last 5 minutes','# TYPE buff_php_api_5xx_5m gauge','buff_php_api_5xx_5m '.$errors,
  ];echo implode("\n",$lines)."\n";
}catch(Throwable $e){http_response_code(500);echo "# BUFF metrics error\n";}
