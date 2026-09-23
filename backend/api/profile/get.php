<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
$user = require_auth();
$userId = (int)$user['id'];

$stmt = db()->prepare('SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY id DESC LIMIT 24');
$stmt->execute([$userId]);
$transactions = $stmt->fetchAll();

$stmt = db()->prepare('SELECT s.*, u.username, u.display_name, u.avatar_url FROM streams s JOIN users u ON u.id = s.user_id WHERE s.user_id = ? LIMIT 1');
$stmt->execute([$userId]);
$stream = $stmt->fetch();

$countStmt = db()->prepare('SELECT COUNT(*) AS c FROM gift_events WHERE recipient_user_id = ?');
$countStmt->execute([$userId]);
$receivedGiftCount = (int)(($countStmt->fetch()['c'] ?? 0));

$stmt = db()->prepare('SELECT ge.id, ge.quantity, ge.unit_price, ge.total_price, ge.audio_url, ge.audio_name, ge.created_at,
                              gc.name AS gift_name, gc.icon AS gift_icon,
                              sender.display_name AS sender_name, sender.username AS sender_username, sender.avatar_url AS sender_avatar_url,
                              gs.destination_mode, gs.status AS settlement_status
                       FROM gift_events ge
                       JOIN gift_catalog gc ON gc.id = ge.gift_id
                       JOIN users sender ON sender.id = ge.sender_user_id
                       LEFT JOIN gift_settlements gs ON gs.gift_event_id = ge.id
                       WHERE ge.recipient_user_id = ?
                       ORDER BY ge.id DESC
                       LIMIT 100');
$stmt->execute([$userId]);
$receivedGifts = $stmt->fetchAll();

$stmt = db()->prepare('SELECT ge.id, ge.quantity, ge.total_price, ge.audio_url, ge.audio_name, ge.created_at,
                              gc.name AS gift_name, gc.icon AS gift_icon,
                              recipient.display_name AS recipient_name, recipient.username AS recipient_username,
                              gr.id AS refund_request_id, gr.status AS refund_status, gr.reason AS refund_reason
                       FROM gift_events ge
                       JOIN gift_catalog gc ON gc.id = ge.gift_id
                       JOIN users recipient ON recipient.id = ge.recipient_user_id
                       LEFT JOIN gift_refund_requests gr ON gr.gift_event_id = ge.id AND gr.requester_user_id = ge.sender_user_id
                       WHERE ge.sender_user_id = ?
                       ORDER BY ge.id DESC
                       LIMIT 30');
$stmt->execute([$userId]);
$sentGifts = $stmt->fetchAll();

$followerStmt = db()->prepare('SELECT COUNT(*) AS c FROM follows WHERE followed_user_id = ?');
$followerStmt->execute([$userId]);
$followerCount = (int)(($followerStmt->fetch()['c'] ?? 0));
$followingStmt = db()->prepare('SELECT COUNT(*) AS c FROM follows WHERE follower_user_id = ?');
$followingStmt->execute([$userId]);
$followingCount = (int)(($followingStmt->fetch()['c'] ?? 0));

$features = user_features($userId);
$bankStmt = db()->prepare('SELECT id, account_holder, iban_last4 FROM bank_accounts WHERE user_id = ? AND is_default = 1 ORDER BY id DESC LIMIT 1');
$bankStmt->execute([$userId]);
$bank = $bankStmt->fetch();

$pendingStmt = db()->prepare("SELECT COALESCE(SUM(amount), 0) AS total FROM gift_settlements WHERE recipient_user_id = ? AND destination_mode = 'bank' AND status = 'bank_pending'");
$pendingStmt->execute([$userId]);
$bankPending = (int)($pendingStmt->fetch()['total'] ?? 0);

$refundStmt = db()->prepare("SELECT COUNT(*) AS c FROM gift_refund_requests WHERE recipient_user_id = ? AND status = 'pending'");
$refundStmt->execute([$userId]);
$pendingRefundCount = (int)($refundStmt->fetch()['c'] ?? 0);

$goalStmt = db()->prepare("SELECT id,title,target_amount,current_amount,status,created_at,completed_at FROM community_goals WHERE creator_user_id=? ORDER BY (status='active') DESC,id DESC LIMIT 1");
$goalStmt->execute([$userId]);
$profileGoal = $goalStmt->fetch();
$topSupportStmt = db()->prepare('SELECT sender.id AS user_id,sender.display_name,sender.username,sender.avatar_url,SUM(ge.total_price) AS total_coins,SUM(ge.quantity) AS gift_quantity,COUNT(*) AS event_count FROM gift_events ge JOIN users sender ON sender.id=ge.sender_user_id WHERE ge.recipient_user_id=? GROUP BY sender.id,sender.display_name,sender.username,sender.avatar_url ORDER BY total_coins DESC,event_count DESC LIMIT 10');
$topSupportStmt->execute([$userId]);
$topSupporters = $topSupportStmt->fetchAll();

json_response([
    'user' => public_user($user),
    'membership' => [
        'isOg' => (bool)$features['is_og'],
        'isVip' => is_vip_feature($features),
        'tier' => is_vip_feature($features) ? (string)($features['vip_tier'] ?? 'plus') : '',
        'vipUntil' => $features['vip_until'] ?? null,
        'chatColor' => (string)$features['chat_color'],
        'vipPrice' => 1000,
    ],
    'bankAccount' => $bank ? [
        'id' => (int)$bank['id'],
        'accountHolder' => (string)$bank['account_holder'],
        'maskedIban' => masked_iban_from_last4((string)$bank['iban_last4']),
    ] : null,
    'bankPendingEarnings' => $bankPending,
    'pendingRefundCount' => $pendingRefundCount,
    'transactions' => array_map(static function (array $row): array {
        return [
            'id' => (int)$row['id'],
            'amount' => (int)$row['amount'],
            'type' => (string)$row['type'],
            'description' => (string)$row['description'],
            'createdAt' => (string)$row['created_at'],
        ];
    }, $transactions),
    'receivedGiftCount' => $receivedGiftCount,
    'followerCount' => $followerCount,
    'followingCount' => $followingCount,
    'receivedGifts' => array_map(static function (array $row): array {
        return [
            'id' => (int)$row['id'],
            'giftName' => (string)$row['gift_name'],
            'giftIcon' => (string)$row['gift_icon'],
            'quantity' => (int)$row['quantity'],
            'unitPrice' => (int)$row['unit_price'],
            'totalPrice' => (int)$row['total_price'],
            'senderName' => (string)$row['sender_name'],
            'senderUsername' => (string)$row['sender_username'],
            'senderAvatarUrl' => $row['sender_avatar_url'] ?: null,
            'audioUrl' => $row['audio_url'] ?: null,
            'audioName' => $row['audio_name'] ?: null,
            'destinationMode' => (string)($row['destination_mode'] ?? 'wallet'),
            'settlementStatus' => (string)($row['settlement_status'] ?? 'wallet_credited'),
            'createdAt' => (string)$row['created_at'],
        ];
    }, $receivedGifts),
    'sentGifts' => array_map(static function (array $row): array {
        return [
            'id' => (int)$row['id'],
            'giftName' => (string)$row['gift_name'],
            'giftIcon' => (string)$row['gift_icon'],
            'quantity' => (int)$row['quantity'],
            'totalPrice' => (int)$row['total_price'],
            'recipientName' => (string)$row['recipient_name'],
            'recipientUsername' => (string)$row['recipient_username'],
            'audioUrl' => $row['audio_url'] ?: null,
            'audioName' => $row['audio_name'] ?: null,
            'refundRequestId' => $row['refund_request_id'] ? (int)$row['refund_request_id'] : null,
            'refundStatus' => $row['refund_status'] ?: null,
            'refundReason' => $row['refund_reason'] ?: null,
            'createdAt' => (string)$row['created_at'],
        ];
    }, $sentGifts),
    'communityGoal' => $profileGoal ? [
        'id' => (int)$profileGoal['id'],
        'title' => (string)$profileGoal['title'],
        'targetAmount' => (int)$profileGoal['target_amount'],
        'currentAmount' => (int)$profileGoal['current_amount'],
        'status' => (string)$profileGoal['status'],
        'progress' => (int)$profileGoal['target_amount'] > 0 ? min(100, round(((int)$profileGoal['current_amount']) * 100 / ((int)$profileGoal['target_amount']), 1)) : 0,
    ] : null,
    'topSupporters' => array_map(static function(array $row): array { return [
        'userId'=>(int)$row['user_id'],'displayName'=>(string)$row['display_name'],'username'=>(string)$row['username'],'avatarUrl'=>$row['avatar_url']?:null,
        'totalCoins'=>(int)$row['total_coins'],'giftQuantity'=>(int)$row['gift_quantity'],'eventCount'=>(int)$row['event_count']
    ]; }, $topSupporters),
    'stream' => $stream ? map_stream($stream) : null,
]);
