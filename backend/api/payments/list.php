<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
$user = require_auth();
$stmt = db()->prepare('SELECT id, coin_amount, money_amount, currency, sender_iban_last4, reference_code, status, created_at FROM bank_payment_requests WHERE user_id = ? ORDER BY id DESC LIMIT 12');
$stmt->execute([(int)$user['id']]);
json_response(['requests' => array_map(static fn(array $r): array => [
    'id' => (int)$r['id'], 'coinAmount' => (int)$r['coin_amount'], 'moneyAmount' => (float)$r['money_amount'], 'currency' => (string)$r['currency'], 'maskedIban' => masked_iban_from_last4((string)$r['sender_iban_last4']), 'referenceCode' => (string)$r['reference_code'], 'status' => (string)$r['status'], 'createdAt' => (string)$r['created_at']
], $stmt->fetchAll())]);
