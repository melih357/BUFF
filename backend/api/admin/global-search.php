<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
$admin=require_admin();
ensure_admin_control_schema();
$q=trim((string)($_GET['q']??''));
if(text_len($q)<2) json_response(['results'=>[]]);
$like='%'.text_substr($q,0,100).'%';
$pdo=db();$out=[];
try{
  if(admin_can((int)$admin['id'],'users.read')){
    $st=$pdo->prepare('SELECT id,username,email,display_name FROM users WHERE username LIKE ? OR email LIKE ? OR display_name LIKE ? OR CAST(id AS CHAR)=? ORDER BY id DESC LIMIT 6');
    $st->execute([$like,$like,$like,$q]);foreach($st->fetchAll() as $r)$out[]=['kind'=>'user','view'=>'users','id'=>(int)$r['id'],'title'=>$r['display_name'].' (@'.$r['username'].')','subtitle'=>$r['email']];
  }
  if(admin_can((int)$admin['id'],'content.read') && db_table_exists('cms_titles')){
    $st=$pdo->prepare('SELECT id,title,content_type,genre FROM cms_titles WHERE title LIKE ? OR genre LIKE ? OR CAST(id AS CHAR)=? ORDER BY id DESC LIMIT 6');
    $st->execute([$like,$like,$q]);foreach($st->fetchAll() as $r)$out[]=['kind'=>'content','view'=>'content_titles','id'=>(int)$r['id'],'title'=>$r['title'],'subtitle'=>strtoupper((string)$r['content_type']).' • '.($r['genre']?:'Tür yok')];
  }
  if(admin_can((int)$admin['id'],'billing.read')&&db_table_exists('payment_orders')){
    $st=$pdo->prepare("SELECT p.id,p.amount_kurus,p.status,u.username FROM payment_orders p JOIN users u ON u.id=p.user_id WHERE CAST(p.id AS CHAR)=? OR u.username LIKE ? OR COALESCE(p.merchant_oid,'') LIKE ? ORDER BY p.id DESC LIMIT 6");
    $st->execute([$q,$like,$like]);foreach($st->fetchAll() as $r)$out[]=['kind'=>'payment','view'=>'payments','id'=>(int)$r['id'],'title'=>'Ödeme #'.$r['id'].' • @'.$r['username'],'subtitle'=>$r['status'].' • '.number_format(((int)$r['amount_kurus'])/100,2,',','.').' ₺'];
  }
}catch(Throwable $e){error_log('[BUFF admin global search] '.$e->getMessage());}
json_response(['results'=>array_slice($out,0,15)]);
