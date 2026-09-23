<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/bootstrap.php';
$user = require_auth();
$oid = trim((string)($_GET['merchant_oid'] ?? ''));
if ($oid === '') json_response(['message' => 'Sipariş numarası gerekli.'], 400);
$stmt = db()->prepare('SELECT merchant_oid, coin_amount, amount_kurus, currency, status, failure_reason, paid_at, created_at FROM payment_orders WHERE merchant_oid = ? AND user_id = ? LIMIT 1');
$stmt->execute([$oid, (int)$user['id']]);
$row = $stmt->fetch();
if (!$row) json_response(['message' => 'Ödeme siparişi bulunamadı.'], 404);
json_response(['order' => [
    'merchantOid' => (string)$row['merchant_oid'],
    'coinAmount' => (int)$row['coin_amount'],
    'amount' => (int)$row['amount_kurus'] / 100,
    'currency' => (string)$row['currency'],
    'status' => (string)$row['status'],
    'failureReason' => $row['failure_reason'] ?: null,
    'paidAt' => $row['paid_at'] ?: null,
    'createdAt' => (string)$row['created_at'],
]]);
