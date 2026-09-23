<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
$user = require_auth();

$incoming = db()->prepare('SELECT gr.id, gr.reason, gr.status, gr.response_note, gr.created_at, ge.id AS gift_event_id, ge.total_price,
                                  gc.name AS gift_name, gc.icon AS gift_icon, sender.display_name AS sender_name, sender.username AS sender_username
                           FROM gift_refund_requests gr
                           JOIN gift_events ge ON ge.id = gr.gift_event_id
                           JOIN gift_catalog gc ON gc.id = ge.gift_id
                           JOIN users sender ON sender.id = ge.sender_user_id
                           WHERE gr.recipient_user_id = ? ORDER BY gr.id DESC LIMIT 30');
$incoming->execute([(int)$user['id']]);
$outgoing = db()->prepare('SELECT gr.id, gr.reason, gr.status, gr.response_note, gr.created_at, ge.id AS gift_event_id, ge.total_price,
                                  gc.name AS gift_name, gc.icon AS gift_icon, recipient.display_name AS recipient_name, recipient.username AS recipient_username
                           FROM gift_refund_requests gr
                           JOIN gift_events ge ON ge.id = gr.gift_event_id
                           JOIN gift_catalog gc ON gc.id = ge.gift_id
                           JOIN users recipient ON recipient.id = ge.recipient_user_id
                           WHERE gr.requester_user_id = ? ORDER BY gr.id DESC LIMIT 30');
$outgoing->execute([(int)$user['id']]);
json_response(['incoming' => $incoming->fetchAll(), 'outgoing' => $outgoing->fetchAll()]);
