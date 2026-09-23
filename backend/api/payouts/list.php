<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
$user = require_auth();
$stmt = db()->prepare('SELECT pr.*, ba.account_holder, ba.iban_last4 FROM payout_requests pr JOIN bank_accounts ba ON ba.id = pr.bank_account_id WHERE pr.user_id = ? ORDER BY pr.id DESC LIMIT 30');
$stmt->execute([(int)$user['id']]);
json_response(['payouts' => array_map(static fn(array $r): array => [
    'id'=>(int)$r['id'],'earningCoins'=>(int)$r['earning_coins'],'moneyAmount'=>(float)$r['money_amount'],'currency'=>(string)$r['currency'],'status'=>(string)$r['status'],'referenceCode'=>(string)$r['reference_code'],'accountHolder'=>(string)$r['account_holder'],'maskedIban'=>masked_iban_from_last4((string)$r['iban_last4']),'createdAt'=>(string)$r['created_at']
], $stmt->fetchAll()), 'rate'=>creator_coin_try_rate()]);
