<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
$me=require_auth();ensure_v16_schema(db());$id=(int)($_GET['id']??0);
if($id<1||!v16_room_member($id,(int)$me['id']))json_response(['message'=>'Sohbet odası bulunamadı.'],404);
$st=db()->prepare('SELECT * FROM message_rooms WHERE id=? LIMIT 1');$st->execute([$id]);$room=$st->fetch();if(!$room)json_response(['message'=>'Sohbet odası bulunamadı.'],404);
json_response(['room'=>v16_room_payload($room,(int)$me['id'])]);
