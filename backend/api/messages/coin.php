<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
require_post();$me=require_auth();ensure_v16_schema(db());
$pdo=db();
try{
 $d=request_json();$roomId=(int)($d['roomId']??0);$recipientId=(int)($d['recipientUserId']??0);$amount=(int)($d['amount']??0);$note=text_substr(trim((string)($d['note']??'')),0,160);
 if($amount<1)json_response(['message'=>'Coin miktarı en az 1 olmalı.'],400);
 if($roomId<1||!v16_room_member($roomId,(int)$me['id'])||!v16_room_member($roomId,$recipientId))json_response(['message'=>'Geçersiz sohbet.'],403);
 if(!v16_mutual_follow((int)$me['id'],$recipientId))json_response(['message'=>'Coin göndermek için karşılıklı takip etmeniz gerekiyor.'],403);
 $key=v17_key($d);$cached=v17_begin((int)$me['id'],$key,'friend.coin',[$roomId,$recipientId,$amount,$note]);if($cached){$cached['walletBalance']=(int)fetch_user_by_id((int)$me['id'])['wallet_balance'];json_response($cached);}v17_query('SELECT id FROM users WHERE id IN (?,?) ORDER BY id FOR UPDATE',[(int)$me['id'],$recipientId])->fetchAll();
 $st=$pdo->prepare('SELECT wallet_balance FROM users WHERE id=? FOR UPDATE');$st->execute([(int)$me['id']]);$balance=(int)($st->fetchColumn()?:0);
 if($balance<$amount){$pdo->rollBack();json_response(['message'=>'Yeterli coin yok.'],409);}
 $st=$pdo->prepare('SELECT id,display_name FROM users WHERE id=? AND account_status=\'active\' FOR UPDATE');$st->execute([$recipientId]);$recipient=$st->fetch();if(!$recipient){$pdo->rollBack();json_response(['message'=>'Alıcı bulunamadı.'],404);}
 $pdo->prepare('UPDATE users SET wallet_balance=wallet_balance-? WHERE id=?')->execute([$amount,(int)$me['id']]);
 $pdo->prepare('UPDATE users SET wallet_balance=wallet_balance+? WHERE id=?')->execute([$amount,$recipientId]);
 $pdo->prepare('INSERT INTO coin_transfers (sender_user_id,recipient_user_id,room_id,amount,note) VALUES (?,?,?,?,?)')->execute([(int)$me['id'],$recipientId,$roomId,$amount,$note]);$tid=(int)$pdo->lastInsertId();
 $pdo->prepare('INSERT INTO wallet_transactions (user_id,amount,type,description,related_user_id) VALUES (?,?,?,?,?)')->execute([(int)$me['id'],-$amount,'friend_coin_sent','Arkadaşa coin gönderildi',$recipientId]);
 $pdo->prepare('INSERT INTO wallet_transactions (user_id,amount,type,description,related_user_id) VALUES (?,?,?,?,?)')->execute([$recipientId,$amount,'friend_coin_received','Arkadaştan coin alındı',(int)$me['id']]);
 $pdo->prepare("INSERT INTO room_messages (room_id,user_id,message_type,body) VALUES (?,?,'coin',?)")->execute([$roomId,(int)$me['id'],$amount.' coin gönderdi'.($note!==''?' • '.$note:'')]);

 v14_finance_audit('friend_coin_transfer','coin_transfer',$tid,(int)$me['id'],['recipientUserId'=>$recipientId,'amount'=>$amount,'roomId'=>$roomId]);
 create_notification($recipientId,(int)$me['id'],'coin_transfer','Coin hediyesi',(string)$me['display_name'].' sana '.$amount.' coin gönderdi.','./messages.php?room='.$roomId);
 v14_publish('message.coin',['roomId'=>$roomId,'transferId'=>$tid,'amount'=>$amount,'senderUserId'=>(int)$me['id'],'recipientUserId'=>$recipientId],null,null,'room.'.$roomId);
 $response=['message'=>'Coin gönderildi.','transferId'=>$tid,'walletBalance'=>$balance-$amount];v17_finish((int)$me['id'],$key,$response);json_response($response);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('[BUFF coin transfer] '.$e->getMessage());json_response(['message'=>'Coin gönderilemedi.'],500);}
