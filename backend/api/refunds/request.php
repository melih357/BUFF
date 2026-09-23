<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();
$user = require_auth();

try {
    $data = request_json();
    $giftEventId = (int)($data['giftEventId'] ?? 0);
    $reason = trim((string)($data['reason'] ?? ''));
    if ($giftEventId < 1 || text_len($reason) < 8) {
        json_response(['message' => 'İade nedeni en az 8 karakter olmalıdır.'], 400);
    }

    $stmt = db()->prepare('SELECT ge.*, sender.display_name AS sender_name, recipient.display_name AS recipient_name, gc.name AS gift_name
                           FROM gift_events ge
                           JOIN users sender ON sender.id = ge.sender_user_id
                           JOIN users recipient ON recipient.id = ge.recipient_user_id
                           JOIN gift_catalog gc ON gc.id = ge.gift_id
                           WHERE ge.id = ? LIMIT 1');
    $stmt->execute([$giftEventId]);
    $gift = $stmt->fetch();
    if (!$gift || (int)$gift['sender_user_id'] !== (int)$user['id']) {
        json_response(['message' => 'Bu hediye için iade talebi oluşturamazsın.'], 403);
    }

    $settle = db()->prepare('SELECT status FROM gift_settlements WHERE gift_event_id = ? LIMIT 1');
    $settle->execute([$giftEventId]);
    $settlement = $settle->fetch();
    if ($settlement && (string)$settlement['status'] === 'refunded') {
        json_response(['message' => 'Bu hediye zaten iade edilmiş.'], 409);
    }

    $stmt = db()->prepare('INSERT INTO gift_refund_requests (gift_event_id, requester_user_id, recipient_user_id, reason) VALUES (?, ?, ?, ?)');
    $stmt->execute([$giftEventId, (int)$user['id'], (int)$gift['recipient_user_id'], text_substr($reason, 0, 500)]);
    $requestId = (int)db()->lastInsertId();

    create_notification(
        (int)$gift['recipient_user_id'],
        (int)$user['id'],
        'refund_request',
        'Hediye iade talebi',
        (string)$gift['sender_name'] . ', ' . (string)$gift['gift_name'] . ' hediyesi için iade istiyor.',
        './profile.php#refunds'
    );
    json_response(['message' => 'İade talebin yayıncıya iletildi.', 'requestId' => $requestId], 201);
} catch (PDOException $e) {
    if ((string)$e->getCode() === '23000') {
        json_response(['message' => 'Bu hediye için zaten bir iade talebin var.'], 409);
    }
    json_response(['message' => 'İade talebi oluşturulamadı.'], 500);
} catch (Throwable $e) {
    json_response(['message' => 'İade talebi oluşturulamadı.'], 500);
}
