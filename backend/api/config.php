<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/bootstrap.php';
$env=app_env();
$realtimeUrl=(string)($env['REALTIME_PUBLIC_URL']??'ws://localhost:3000/realtime');
$nodeApiUrl=(string)($env['NODE_PUBLIC_URL']??'http://localhost:3000');
$realtimeAvailable=true;

// Yerel geliştirmede Node realtime servisi çalışmıyorsa tarayıcıya kırık ws:// URL'si
// göndermeyelim. Böylece konsolda ERR_CONNECTION_REFUSED oluşmaz ve istemci sessizce
// MySQL/HTTP polling fallback'ine geçer. Harici production wss:// adresleri otomatik
// olarak devre dışı bırakılmaz; reverse proxy/edge katmanı tarafından yönetilebilir.
$parsed=@parse_url($nodeApiUrl);
$host=(string)($parsed['host']??'');
$port=(int)($parsed['port']??(($parsed['scheme']??'http')==='https'?443:80));
if (in_array(strtolower($host), ['localhost','127.0.0.1','::1'], true)) {
    $errno=0; $errstr='';
    $sock=@fsockopen($host,$port,$errno,$errstr,0.08);
    if (is_resource($sock)) fclose($sock); else $realtimeAvailable=false;
}
if (!$realtimeAvailable) $realtimeUrl='';

json_response([
  'version'=>'16.0.0',
  'realtimeUrl'=>$realtimeUrl,
  'realtimeAvailable'=>$realtimeAvailable,
  'nodeApiUrl'=>$nodeApiUrl,
  'storageProvider'=>(string)($env['STORAGE_PROVIDER']??'local'),
  'features'=>[
    'predictions'=>true,'communityGoals'=>true,'loyalty'=>true,'realtime'=>$realtimeAvailable,
    'realtimeFallback'=>true,'adaptiveStreaming'=>true,'memberFeed'=>true,'clipDiscovery'=>true,
    'messaging'=>true,'groups'=>true,'coinTransfer'=>true,'userClipUpload'=>true
  ]
]);
