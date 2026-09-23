<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
function buff_group_supports(array $rows,int $limit=24): array{
    $out=[];
    foreach($rows as $row){
        $key=((int)$row['sender_user_id']).'|'.((int)$row['gift_id']);
        $lastIndex=count($out)-1;
        if($lastIndex>=0 && ($out[$lastIndex]['_key']??'')===$key){
            $out[$lastIndex]['quantity']+=(int)$row['quantity'];
            $out[$lastIndex]['totalPrice']+=(int)$row['total_price'];
            $out[$lastIndex]['eventCount']++;
            continue;
        }
        $out[]=[
            '_key'=>$key,'senderId'=>(int)$row['sender_user_id'],'senderName'=>(string)$row['display_name'],
            'senderUsername'=>(string)$row['username'],'senderAvatarUrl'=>$row['avatar_url']?:null,
            'giftName'=>(string)$row['gift_name'],'giftIcon'=>(string)$row['gift_icon'],
            'quantity'=>(int)$row['quantity'],'totalPrice'=>(int)$row['total_price'],'eventCount'=>1,
            'createdAt'=>(string)$row['created_at']
        ];
        if(count($out)>=$limit)break;
    }
    foreach($out as &$r)unset($r['_key']);
    return $out;
}
try{
    $username=trim((string)($_GET['username']??''));$id=(int)($_GET['id']??0);
    if($username===''){
        if($id<1){$me=current_user();$id=(int)($me['id']??0);}
        if($id<1)json_response(['message'=>'Kanal bulunamadı.'],404);
        $st=db()->prepare('SELECT * FROM users WHERE id=? LIMIT 1');$st->execute([$id]);
    }else{$st=db()->prepare('SELECT * FROM users WHERE username=? LIMIT 1');$st->execute([$username]);}
    $u=$st->fetch();if(!$u)json_response(['message'=>'Kanal bulunamadı.'],404);$uid=(int)$u['id'];
    $viewer=current_user();$canView=v17_can_view_profile($uid,$viewer);
    if(!$canView){$counts=v17_query('SELECT COUNT(*) FROM follows WHERE followed_user_id=?',[$uid])->fetchColumn();json_response(['channel'=>['user'=>v17_person($u),'canView'=>false,'bio'=>'Bu hesap gizli. Paylaşımları görmek için takip isteği gönder.','followerCount'=>(int)$counts,'bannerUrl'=>null,'social'=>[],'schedule'=>[],'goal'=>null,'leaderboard'=>[],'recentSupports'=>[],'live'=>null,'recordings'=>[],'clips'=>[],'posts'=>[]]]);}
    $cp=db()->prepare('SELECT * FROM channel_profiles WHERE user_id=? LIMIT 1');$cp->execute([$uid]);$profile=$cp->fetch()?:[];
    $fc=db()->prepare('SELECT COUNT(*) FROM follows WHERE followed_user_id=?');$fc->execute([$uid]);
    $live=db()->prepare('SELECT * FROM streams WHERE user_id=? AND is_live=1 LIMIT 1');$live->execute([$uid]);$stream=$live->fetch();
    $rec=db()->prepare("SELECT id,title,file_url,thumbnail_url,duration_seconds,view_count,created_at FROM recordings WHERE user_id=? AND visibility='public' AND status='ready' ORDER BY id DESC LIMIT 12");$rec->execute([$uid]);
    $clipAccess=v17_profile_sql('created_by_user_id',(int)($viewer['id']??0));
    $clips=db()->prepare("SELECT id,title,file_url,thumbnail_url,duration_seconds,view_count,created_at FROM clips WHERE streamer_user_id=? AND visibility='public' AND {$clipAccess} ORDER BY id DESC LIMIT 12");$clips->execute([$uid]);
    $posts=db()->prepare('SELECT id,body,image_url,video_url,poll_json,is_pinned,created_at FROM feed_posts WHERE user_id=? AND visibility=\'public\' AND clip_id IS NULL ORDER BY is_pinned DESC,id DESC LIMIT 20');$posts->execute([$uid]);
    $social=[];if(!empty($profile['social_json']))$social=json_decode((string)$profile['social_json'],true)?:[];
    $scheduleStmt=db()->prepare('SELECT weekday,start_time,end_time,title,timezone FROM channel_schedule WHERE user_id=? AND enabled=1 ORDER BY weekday,start_time');$scheduleStmt->execute([$uid]);
    $goalStmt=db()->prepare("SELECT id,title,target_amount,current_amount,status,created_at,completed_at FROM community_goals WHERE creator_user_id=? ORDER BY (status='active') DESC,id DESC LIMIT 1");$goalStmt->execute([$uid]);$goal=$goalStmt->fetch();
    $leaders=db()->prepare('SELECT sender.id AS user_id,sender.display_name,sender.username,sender.avatar_url,SUM(ge.total_price) AS total_coins,SUM(ge.quantity) AS gift_quantity,COUNT(*) AS event_count FROM gift_events ge JOIN users sender ON sender.id=ge.sender_user_id WHERE ge.recipient_user_id=? GROUP BY sender.id,sender.display_name,sender.username,sender.avatar_url ORDER BY total_coins DESC,event_count DESC LIMIT 10');$leaders->execute([$uid]);
    $supportStmt=db()->prepare('SELECT ge.sender_user_id,ge.gift_id,ge.quantity,ge.total_price,ge.created_at,gc.name AS gift_name,gc.icon AS gift_icon,sender.display_name,sender.username,sender.avatar_url FROM gift_events ge JOIN gift_catalog gc ON gc.id=ge.gift_id JOIN users sender ON sender.id=ge.sender_user_id WHERE ge.recipient_user_id=? ORDER BY ge.id DESC LIMIT 100');$supportStmt->execute([$uid]);
    $goalMapped=null;if($goal){$target=max(1,(int)$goal['target_amount']);$goalMapped=['id'=>(int)$goal['id'],'title'=>(string)$goal['title'],'targetAmount'=>$target,'currentAmount'=>(int)$goal['current_amount'],'status'=>(string)$goal['status'],'progress'=>min(100,round(((int)$goal['current_amount'])*100/$target,1)),'createdAt'=>(string)$goal['created_at']];}
    json_response(['channel'=>[
        'user'=>v17_person($u),'canView'=>true,'bannerUrl'=>$profile['banner_url']??null,'bio'=>(string)($profile['channel_bio']??$u['bio']??''),'social'=>$social,
        'schedule'=>array_map(static fn($r)=>['weekday'=>(int)$r['weekday'],'startTime'=>substr((string)$r['start_time'],0,5),'endTime'=>$r['end_time']?substr((string)$r['end_time'],0,5):null,'title'=>(string)$r['title'],'timezone'=>(string)$r['timezone']],$scheduleStmt->fetchAll()),
        'goal'=>$goalMapped,
        'leaderboard'=>array_map(static fn($r)=>['userId'=>(int)$r['user_id'],'displayName'=>(string)$r['display_name'],'username'=>(string)$r['username'],'avatarUrl'=>$r['avatar_url']?:null,'totalCoins'=>(int)$r['total_coins'],'giftQuantity'=>(int)$r['gift_quantity'],'eventCount'=>(int)$r['event_count']],$leaders->fetchAll()),
        'recentSupports'=>buff_group_supports($supportStmt->fetchAll(),30),
        'premiumLayout'=>(string)($profile['premium_layout']??'standard'),'followerCount'=>(int)$fc->fetchColumn(),
        'live'=>$stream?map_stream(array_merge($stream,['username'=>$u['username'],'display_name'=>$u['display_name'],'avatar_url'=>$u['avatar_url']])):null,
        'recordings'=>v17_legacy_media($rec->fetchAll(),'recording'),'clips'=>v17_legacy_media($clips->fetchAll(),'clip'),'posts'=>v17_legacy_media($posts->fetchAll(),'post')
    ]]);
}catch(Throwable $e){json_response(['message'=>'Kanal verisi alınamadı.'],500);}
