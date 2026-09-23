<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();
$user=require_auth();
$data=request_json();
$id=(int)($data['id']??0);
if($id<1) json_response(['message'=>'Geçersiz kart kaydı.'],400);
$stmt=db()->prepare('DELETE FROM payment_methods WHERE id=? AND user_id=?');
$stmt->execute([$id,(int)$user['id']]);
$check=db()->prepare('SELECT id FROM payment_methods WHERE user_id=? AND is_default=1 LIMIT 1');
$check->execute([(int)$user['id']]);
if(!$check->fetchColumn()){
    $next=db()->prepare('SELECT id FROM payment_methods WHERE user_id=? ORDER BY id DESC LIMIT 1');
    $next->execute([(int)$user['id']]);
    $nextId=(int)($next->fetchColumn() ?: 0);
    if($nextId>0) db()->prepare('UPDATE payment_methods SET is_default=1 WHERE id=? AND user_id=?')->execute([$nextId,(int)$user['id']]);
}
json_response(['message'=>'Kayıtlı kart kaldırıldı.']);
