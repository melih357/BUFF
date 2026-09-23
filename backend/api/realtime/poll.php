<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
$user=require_auth();
$after=max(0,(int)($_GET['after_id']??0));
$streamId=max(0,(int)($_GET['stream_id']??0));
$prime=isset($_GET['prime']) && (string)$_GET['prime']==='1';
try{
  ensure_v14_schema(db());if($streamId>0)v17_stream($streamId,$user);
  if($prime){
    $sql='SELECT COALESCE(MAX(id),0) FROM realtime_events WHERE (user_id IS NULL OR user_id=?)';
    $params=[(int)$user['id']];
    if($streamId>0){$sql.=' AND (stream_id IS NULL OR stream_id=?)';$params[]=$streamId;}else{$sql.=' AND stream_id IS NULL';}
    $st=db()->prepare($sql);$st->execute($params);
    json_response(['events'=>[],'lastId'=>(int)$st->fetchColumn(),'transport'=>'db-poll-prime']);
  }
  $sql='SELECT id,user_id,stream_id,topic,event_type,payload_json,created_at FROM realtime_events WHERE id>? AND (user_id IS NULL OR user_id=?)';
  $params=[$after,(int)$user['id']];
  if($streamId>0){$sql.=' AND (stream_id IS NULL OR stream_id=?)';$params[]=$streamId;}else{$sql.=' AND stream_id IS NULL';}
  $sql.=' ORDER BY id ASC LIMIT 100';
  $st=db()->prepare($sql);$st->execute($params);$rows=[];
  foreach($st->fetchAll() as $r){$rows[]=['id'=>(int)$r['id'],'event'=>(string)$r['event_type'],'topic'=>(string)$r['topic'],'streamId'=>$r['stream_id']!==null?(int)$r['stream_id']:null,'payload'=>json_decode((string)($r['payload_json']??'{}'),true)?:[],'createdAt'=>(string)$r['created_at']];}
  json_response(['events'=>$rows,'lastId'=>$rows?end($rows)['id']:$after,'transport'=>'db-poll']);
}catch(Throwable $e){json_response(['message'=>app_debug()?$e->getMessage():'Realtime olayları alınamadı.'],500);}
