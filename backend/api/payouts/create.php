<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();
$user = require_auth();
$userId = (int)$user['id'];

try {
    $data = request_json();
    $coins = (int)($data['earningCoins'] ?? 0);
    if ($coins < 1000) json_response(['message' => 'Minimum çekim tutarı 1.000 yayıncı coinidir.'], 400);
    $stmt = db()->prepare('SELECT id FROM bank_accounts WHERE user_id = ? AND is_default = 1 ORDER BY id DESC LIMIT 1');
    $stmt->execute([$userId]);
    $bank = $stmt->fetch();
    if (!$bank) json_response(['message' => 'Para çekmek için önce Ayarlar > Ödemeler bölümünden IBAN kaydetmelisin.'], 400);

    $rate = creator_coin_try_rate();
    $money = round($coins * $rate, 2);
    $reference = 'BUFF-WD-' . strtoupper(bin2hex(random_bytes(5)));

    $pdo = db();
    $pdo->beginTransaction();
    debit_creator_earnings($userId, $coins, 'IBAN para çekme talebi ' . $reference);
    $stmt = $pdo->prepare('INSERT INTO payout_requests (user_id, bank_account_id, earning_coins, money_amount, currency, reference_code) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, (int)$bank['id'], $coins, $money, 'TRY', $reference]);
    $pdo->commit();
    json_response(['message' => 'Para çekme talebi oluşturuldu.', 'referenceCode'=>$reference, 'moneyAmount'=>$money, 'currency'=>'TRY']);
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    $msg = $e->getMessage() === 'Yetersiz yayıncı kazancı.' ? $e->getMessage() : 'Para çekme talebi oluşturulamadı.';
    json_response(['message' => $msg], 400);
}
