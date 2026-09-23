<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
ensure_admin_control_schema();
$user = require_auth();
$stmt = db()->prepare('SELECT id,report_scope,target_username_snapshot,stream_title_snapshot,category,description,status,admin_reply,replied_at,created_at FROM reports WHERE reporter_user_id = ? ORDER BY id DESC LIMIT 30');
$stmt->execute([(int)$user['id']]);
json_response(['reports' => array_map(static fn(array $r): array => [
    'id'=>(int)$r['id'],'scope'=>(string)$r['report_scope'],'targetUsername'=>$r['target_username_snapshot']?:null,'streamTitle'=>$r['stream_title_snapshot']?:null,
    'category'=>(string)$r['category'],'description'=>(string)$r['description'],'status'=>(string)$r['status'],
    'adminReply'=>$r['admin_reply']?:null,'repliedAt'=>$r['replied_at']?:null,'createdAt'=>(string)$r['created_at']
], $stmt->fetchAll())]);
