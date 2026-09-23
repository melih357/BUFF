<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
ensure_admin_control_schema();
require_post();
$user = require_auth();

$data = request_json();
$type = strtolower(trim((string)($data['type'] ?? 'request')));
$subject = trim((string)($data['subject'] ?? ''));
$message = trim((string)($data['message'] ?? ''));
if (!in_array($type, ['complaint','request','suggestion','technical','payment'], true)) $type = 'request';
if (text_len($subject) < 3 || text_len($message) < 10) json_response(['message' => 'Konu ve açıklama alanlarını daha ayrıntılı doldur.'], 400);
$stmt = db()->prepare('INSERT INTO support_tickets (user_id, ticket_type, subject, message) VALUES (?, ?, ?, ?)');
$stmt->execute([(int)$user['id'], $type, text_substr($subject,0,140), text_substr($message,0,2000)]);
json_response(['message' => 'Talebin BUFF destek kutusuna iletildi.', 'ticketId' => (int)db()->lastInsertId()], 201);
