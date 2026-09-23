<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/bootstrap.php';
require_post();
$u=require_auth();
try{
    $pdo=db();
    $data=request_json();
    $bio=text_substr(trim((string)($data['bio']??'')),0,500);
    $social=$data['social']??[];
    if(!is_array($social))$social=[];
    $allowed=['youtube','instagram','x','tiktok','discord'];
    $clean=[];
    foreach($allowed as $k){
        $v=trim((string)($social[$k]??''));
        if($v==='')continue;
        if(!preg_match('~^https?://~i',$v))$v='https://'.$v;
        if(filter_var($v,FILTER_VALIDATE_URL))$clean[$k]=text_substr($v,0,220);
    }
    $layout=(string)($data['premiumLayout']??'standard');
    if(!in_array($layout,['standard','premium'],true))$layout='standard';
    $schedule=$data['schedule']??[];
    if(!is_array($schedule))$schedule=[];
    $normalized=[];
    foreach(array_slice($schedule,0,21) as $row){
        if(!is_array($row)||empty($row['enabled']))continue;
        $weekday=(int)($row['weekday']??-1);
        $start=trim((string)($row['startTime']??''));
        $end=trim((string)($row['endTime']??''));
        $title=text_substr(trim((string)($row['title']??'')),0,120);
        if($weekday<0||$weekday>6||!preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/',$start))continue;
        if($end!==''&&!preg_match('/^(?:[01]\\d|2[0-3]):[0-5]\\d$/',$end))$end='';
        $normalized[]=['weekday'=>$weekday,'start'=>$start.':00','end'=>$end!==''?$end.':00':null,'title'=>$title];
    }
    $pdo->beginTransaction();
    $st=$pdo->prepare('INSERT INTO channel_profiles (user_id,channel_bio,social_json,premium_layout) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE channel_bio=VALUES(channel_bio),social_json=VALUES(social_json),premium_layout=VALUES(premium_layout)');
    $st->execute([(int)$u['id'],$bio,json_encode($clean,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$layout]);
    $pdo->prepare('DELETE FROM channel_schedule WHERE user_id=?')->execute([(int)$u['id']]);
    if($normalized){
        $ins=$pdo->prepare('INSERT INTO channel_schedule (user_id,weekday,start_time,end_time,title,timezone,enabled) VALUES (?,?,?,?,?,?,1)');
        foreach($normalized as $row)$ins->execute([(int)$u['id'],$row['weekday'],$row['start'],$row['end'],$row['title'],'Europe/Istanbul']);
    }
    $pdo->commit();
    json_response(['message'=>'Kanal bilgileri ve yayın takvimi kaydedildi.']);
}catch(Throwable $e){
    if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
    json_response(['message'=>'Kanal güncellenemedi.'],500);
}
