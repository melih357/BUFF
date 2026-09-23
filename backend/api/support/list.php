<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
ensure_admin_control_schema();
$user = require_auth();
$stmt = db()->prepare('SELECT id,ticket_type,subject,message,status,admin_reply,replied_at,created_at,updated_at FROM support_tickets WHERE user_id=? ORDER BY id DESC LIMIT 30');
$stmt->execute([(int)$user['id']]);
json_response(['tickets'=>array_map(static fn(array $r):array=>[
  'id'=>(int)$r['id'],'type'=>(string)$r['ticket_type'],'subject'=>(string)$r['subject'],'message'=>(string)$r['message'],
  'status'=>(string)$r['status'],'adminReply'=>$r['admin_reply']?:null,'repliedAt'=>$r['replied_at']?:null,
  'createdAt'=>(string)$r['created_at'],'updatedAt'=>(string)$r['updated_at']
],$stmt->fetchAll())]);
