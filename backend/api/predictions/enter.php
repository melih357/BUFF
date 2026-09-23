<?php
declare(strict_types=1);require_once dirname(__DIR__,2).'/bootstrap.php';require_post();$u=require_auth();
try{
 $d=request_json();$predictionId=(int)($d['predictionId']??0);$optionId=(int)($d['optionId']??0);$points=max(1,min(100000,(int)($d['points']??0)));
 $pdo=db();$pdo->beginTransaction();$st=$pdo->prepare("SELECT * FROM predictions WHERE id=? AND status='open' AND (closes_at IS NULL OR closes_at>NOW()) FOR UPDATE");$st->execute([$predictionId]);$p=$st->fetch();if(!$p){$pdo->rollBack();json_response(['message'=>'Bu tahmin kapalı.'],409);}
 $o=$pdo->prepare('SELECT id FROM prediction_options WHERE id=? AND prediction_id=?');$o->execute([$optionId,$predictionId]);if(!$o->fetchColumn()){$pdo->rollBack();json_response(['message'=>'Geçersiz seçenek.'],400);}
 $pdo->prepare('INSERT IGNORE INTO user_loyalty (user_id) VALUES (?)')->execute([(int)$u['id']]);$bal=$pdo->prepare('SELECT points FROM user_loyalty WHERE user_id=? FOR UPDATE');$bal->execute([(int)$u['id']]);$available=(int)$bal->fetchColumn();if($available<$points){$pdo->rollBack();json_response(['message'=>'Yeterli sadakat puanın yok.'],400);}
 try{$pdo->prepare('INSERT INTO prediction_entries (prediction_id,option_id,user_id,points) VALUES (?,?,?,?)')->execute([$predictionId,$optionId,(int)$u['id'],$points]);}catch(PDOException $e){$pdo->rollBack();json_response(['message'=>'Bu tahmine zaten katıldın.'],409);}
 $pdo->prepare('UPDATE user_loyalty SET points=points-? WHERE user_id=?')->execute([$points,(int)$u['id']]);$pdo->commit();
 v14_publish('prediction.entry',['predictionId'=>$predictionId,'optionId'=>$optionId,'userId'=>(int)$u['id'],'points'=>$points],null,(int)$p['stream_id'],'stream.'.(int)$p['stream_id']);json_response(['message'=>'Tahminin alındı.','loyalty'=>v14_loyalty_snapshot((int)$u['id'])]);
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();json_response(['message'=>'Tahmine katılım başarısız.'],500);} 
