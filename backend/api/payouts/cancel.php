<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();
$user = require_auth();
try {
    $data = request_json();
    $id = (int)($data['id'] ?? 0);
    $pdo = db();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT * FROM payout_requests WHERE id = ? AND user_id = ? FOR UPDATE");
    $stmt->execute([$id, (int)$user['id']]);
    $row = $stmt->fetch();
    if (!$row || (string)$row['status'] !== 'pending') {
        $pdo->rollBack();
        json_response(['message' => 'Bu çekim talebi iptal edilemez.'], 409);
    }
    $coins = (int)$row['earning_coins'];
    $pdo->prepare("UPDATE payout_requests SET status='cancelled' WHERE id = ?")->execute([$id]);
    $pdo->prepare('UPDATE users SET creator_earnings = creator_earnings + ? WHERE id = ?')->execute([$coins, (int)$user['id']]);
    $pdo->prepare('INSERT INTO wallet_transactions (user_id, amount, type, description) VALUES (?, ?, ?, ?)')->execute([(int)$user['id'], $coins, 'creator_payout_cancel', 'İptal edilen para çekme talebi']);
    $pdo->commit();
    json_response(['message' => 'Çekim talebi iptal edildi ve yayıncı kazancın geri yüklendi.']);
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    json_response(['message' => 'Çekim talebi iptal edilemedi.'], 500);
}
