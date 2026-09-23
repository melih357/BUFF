<?php
declare(strict_types=1);require_once dirname(__DIR__,2).'/bootstrap.php';require_post();$u=require_auth();
try{
 $d=request_json();$streamId=(int)($d['streamId']??0);$q=text_substr(trim((string)($d['question']??'')),0,180);$opts=$d['options']??[];$seconds=max(15,min(3600,(int)($d['durationSeconds']??180)));
 $stream=get_stream_by_id($streamId);if(!$stream||(int)$stream['user_id']!==(int)$u['id'])json_response(['message'=>'Bu yayın için tahmin oluşturamazsın.'],403);
 if($q===''||!is_array($opts)||count($opts)<2||count($opts)>6)json_response(['message'=>'Soru ve 2-6 seçenek gerekli.'],400);
 $pdo=db();$pdo->beginTransaction();$pdo->prepare("UPDATE predictions SET status='cancelled' WHERE stream_id=? AND status='open'")->execute([$streamId]);
 $st=$pdo->prepare("INSERT INTO predictions (stream_id,creator_user_id,question,status,closes_at) VALUES (?,?,?,'open',DATE_ADD(NOW(),INTERVAL ? SECOND))");$st->execute([$streamId,(int)$u['id'],$q,$seconds]);$id=(int)$pdo->lastInsertId();
 $ins=$pdo->prepare('INSERT INTO prediction_options (prediction_id,label,sort_order) VALUES (?,?,?)');$i=0;foreach($opts as $label){$label=text_substr(trim((string)$label),0,100);if($label==='')continue;$ins->execute([$id,$label,$i++]);}if($i<2)throw new RuntimeException('En az iki geçerli seçenek gerekli.');$pdo->commit();
 v14_publish('prediction.created',['predictionId'=>$id,'question'=>$q],null,$streamId,'stream.'.$streamId);json_response(['message'=>'Tahmin açıldı.','predictionId'=>$id],201);
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();json_response(['message'=>'Tahmin oluşturulamadı: '.$e->getMessage()],500);} 
