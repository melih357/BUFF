<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();
$user = require_auth();

try {
    $data = request_json();
    $requestId = (int)($data['requestId'] ?? 0);
    $decision = strtolower(trim((string)($data['decision'] ?? '')));
    $note = trim((string)($data['note'] ?? ''));
    if ($requestId < 1 || !in_array($decision, ['accept', 'reject'], true)) {
        json_response(['message' => 'Geçersiz iade kararı.'], 400);
    }

    $pdo = db();
    $stmt = $pdo->prepare('SELECT gr.*, ge.stream_id, ge.total_price, ge.sender_user_id, ge.recipient_user_id, gc.name AS gift_name,
                                  sender.display_name AS sender_name
                           FROM gift_refund_requests gr
                           JOIN gift_events ge ON ge.id = gr.gift_event_id
                           JOIN gift_catalog gc ON gc.id = ge.gift_id
                           JOIN users sender ON sender.id = ge.sender_user_id
                           WHERE gr.id = ? LIMIT 1');
    $stmt->execute([$requestId]);
    $req = $stmt->fetch();
    if (!$req || (int)$req['recipient_user_id'] !== (int)$user['id']) {
        json_response(['message' => 'Bu iade talebini yönetemezsin.'], 403);
    }
    if ((string)$req['status'] !== 'pending') {
        json_response(['message' => 'Bu iade talebi daha önce sonuçlandırılmış.'], 409);
    }

    if ($decision === 'reject') {
        $stmt = $pdo->prepare('UPDATE gift_refund_requests SET status = ?, response_note = ?, reviewed_at = NOW() WHERE id = ?');
        $stmt->execute(['rejected', text_substr($note, 0, 500), $requestId]);
        create_notification((int)$req['requester_user_id'], (int)$user['id'], 'refund_rejected', 'İade talebi reddedildi', 'Hediye iade talebin yayıncı tarafından reddedildi.', './profile.php#sent-gifts');
        v14_finance_audit('refund_rejected','gift_refund_request',$requestId,(int)$user['id'],['giftEventId'=>(int)$req['gift_event_id'],'amount'=>(int)$req['total_price']]);
        json_response(['message' => 'İade talebi reddedildi.']);
    }

    $pdo->beginTransaction();
    $settleStmt = $pdo->prepare('SELECT * FROM gift_settlements WHERE gift_event_id = ? FOR UPDATE');
    $settleStmt->execute([(int)$req['gift_event_id']]);
    $settlement = $settleStmt->fetch();
    if (!$settlement) {
        $pdo->prepare('INSERT INTO gift_settlements (gift_event_id, recipient_user_id, amount, destination_mode, status) VALUES (?, ?, ?, ?, ?)')
            ->execute([(int)$req['gift_event_id'], (int)$user['id'], (int)$req['total_price'], 'creator_earnings', 'available']);
        $settlement = ['destination_mode' => 'creator_earnings', 'status' => 'available', 'amount' => (int)$req['total_price']];
    }
    if ((string)$settlement['status'] === 'refunded') {
        $pdo->rollBack();
        json_response(['message' => 'Bu hediye zaten iade edilmiş.'], 409);
    }

    $amount = (int)$req['total_price'];
    if ((string)$settlement['destination_mode'] === 'creator_earnings' && in_array((string)$settlement['status'], ['available', 'payout_pending'], true)) {
        $balanceStmt = $pdo->prepare('SELECT creator_earnings FROM users WHERE id = ? FOR UPDATE');
        $balanceStmt->execute([(int)$user['id']]);
        $balance = (int)($balanceStmt->fetch()['creator_earnings'] ?? 0);
        if ($balance < $amount) {
            $pdo->rollBack();
            json_response(['message' => 'İadeyi kabul etmek için çekilebilir yayıncı kazancında hediye tutarı kadar bakiye bulunmalı.'], 409);
        }
        debit_creator_earnings((int)$user['id'], $amount, 'Hediye iadesi: ' . (string)$req['gift_name']);
    } elseif ((string)$settlement['destination_mode'] === 'wallet' && (string)$settlement['status'] === 'wallet_credited') {
        $balanceStmt = $pdo->prepare('SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE');
        $balanceStmt->execute([(int)$user['id']]);
        $balance = (int)($balanceStmt->fetch()['wallet_balance'] ?? 0);
        if ($balance < $amount) {
            $pdo->rollBack();
            json_response(['message' => 'İadeyi kabul etmek için cüzdanında hediye tutarı kadar bakiye bulunmalı.'], 409);
        }
        debit_wallet((int)$user['id'], $amount, 'gift_refund_out', 'Hediye iadesi: ' . (string)$req['gift_name'], (int)$req['requester_user_id']);
    }

    credit_wallet((int)$req['requester_user_id'], $amount, 'gift_refund_in', 'İade edilen hediye: ' . (string)$req['gift_name'], (int)$user['id']);
    $pdo->prepare('UPDATE gift_settlements SET status = ? WHERE gift_event_id = ?')->execute(['refunded', (int)$req['gift_event_id']]);
    $pdo->prepare('UPDATE gift_refund_requests SET status = ?, response_note = ?, reviewed_at = NOW() WHERE id = ?')->execute(['accepted', text_substr($note, 0, 500), $requestId]);
    $pdo->prepare('UPDATE users SET total_gifts_received = GREATEST(total_gifts_received - ?, 0) WHERE id = ?')->execute([$amount, (int)$user['id']]);
    $pdo->prepare('UPDATE streams SET gift_total = GREATEST(gift_total - ?, 0) WHERE id = ?')->execute([$amount, (int)$req['stream_id']]);
    $pdo->commit();

    create_notification((int)$req['requester_user_id'], (int)$user['id'], 'refund_accepted', 'İade talebi kabul edildi', $amount . ' coin cüzdanına geri eklendi.', './profile.php#sent-gifts');
    v14_finance_audit('refund_accepted','gift_refund_request',$requestId,(int)$user['id'],['giftEventId'=>(int)$req['gift_event_id'],'amount'=>$amount,'requesterUserId'=>(int)$req['requester_user_id']]);
    json_response(['message' => 'İade kabul edildi ve gönderene coin geri verildi.']);
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    json_response(['message' => 'İade kararı uygulanamadı.'], 500);
}
