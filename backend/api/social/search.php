<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
$me=require_auth(); ensure_v16_schema(db());
$q=trim((string)($_GET['q']??''));
if($q==='') json_response(['users'=>[]]);
try{
  $myId=(int)$me['id'];$pdo=db(); $needle=text_substr($q,0,80); $like='%'.$needle.'%';
  $sql="SELECT u.id,u.public_id,u.username,u.display_name,u.avatar_url,u.bio,u.account_mode,EXISTS(SELECT 1 FROM follow_requests fr WHERE fr.requester_user_id={$myId} AND fr.target_user_id=u.id) pending,
    EXISTS(SELECT 1 FROM follows f WHERE f.follower_user_id=? AND f.followed_user_id=u.id) AS i_follow,
    EXISTS(SELECT 1 FROM follows f2 WHERE f2.follower_user_id=u.id AND f2.followed_user_id=?) AS follows_me
    FROM users u
    WHERE u.id<>? AND u.account_status='active' AND
      (u.public_id=? OR u.username LIKE ? OR u.display_name LIKE ?)
    ORDER BY (u.public_id=?) DESC,(u.username=?) DESC,u.display_name ASC LIMIT 20";
  $st=$pdo->prepare($sql);$st->execute([(int)$me['id'],(int)$me['id'],(int)$me['id'],$needle,$like,$like,$needle,ltrim($needle,'@')]);
  $users=array_map(static fn($u)=>[
    'id'=>(int)$u['id'],'publicId'=>(string)($u['public_id']??v16_public_id((int)$u['id'])),
    'username'=>(string)$u['username'],'displayName'=>(string)$u['display_name'],'avatarUrl'=>$u['avatar_url']?:null,
    'bio'=>(string)($u['bio']??''),'accountMode'=>(string)($u['account_mode']??'viewer'),
    'pending'=>(bool)$u['pending'],'following'=>(bool)$u['i_follow'],'followsMe'=>(bool)$u['follows_me'],'mutual'=>(bool)$u['i_follow']&&(bool)$u['follows_me']
  ],$st->fetchAll());
  json_response(['users'=>$users]);
}catch(Throwable $e){error_log('[BUFF social search] '.$e->getMessage());json_response(['message'=>'Kullanıcı araması yapılamadı.'],500);}
