<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
$me=current_user();$streamId=(int)($_GET['streamId']??0);if($streamId<1)json_response(['message'=>'Geçersiz yayın.'],400);
try{
 $stmt=db()->prepare("SELECT * FROM predictions WHERE stream_id=? AND status IN ('open','resolved') ORDER BY id DESC LIMIT 5");$stmt->execute([$streamId]);$preds=[];
 foreach($stmt->fetchAll() as $p){
   $o=db()->prepare('SELECT po.id,po.label,po.sort_order,COALESCE(SUM(pe.points),0) point_pool,COUNT(pe.id) entry_count FROM prediction_options po LEFT JOIN prediction_entries pe ON pe.option_id=po.id WHERE po.prediction_id=? GROUP BY po.id ORDER BY po.sort_order,po.id');$o->execute([(int)$p['id']]);
   $mine=null;if($me){$m=db()->prepare('SELECT option_id,points,payout_points FROM prediction_entries WHERE prediction_id=? AND user_id=? LIMIT 1');$m->execute([(int)$p['id'],(int)$me['id']]);$mr=$m->fetch();if($mr)$mine=['optionId'=>(int)$mr['option_id'],'points'=>(int)$mr['points'],'payoutPoints'=>(int)$mr['payout_points']];}
   $preds[]=['id'=>(int)$p['id'],'question'=>$p['question'],'status'=>$p['status'],'winningOptionId'=>$p['winning_option_id']?(int)$p['winning_option_id']:null,'closesAt'=>$p['closes_at'],'createdAt'=>$p['created_at'],'mine'=>$mine,'options'=>array_map(static fn($r)=>['id'=>(int)$r['id'],'label'=>$r['label'],'pointPool'=>(int)$r['point_pool'],'entryCount'=>(int)$r['entry_count']],$o->fetchAll())];
 }
 json_response(['predictions'=>$preds,'loyalty'=>$me?v14_loyalty_snapshot((int)$me['id']):null]);
}catch(Throwable $e){json_response(['message'=>'Tahminler yüklenemedi.'],500);} 
