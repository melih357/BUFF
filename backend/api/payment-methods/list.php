<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
$user = require_auth();
$stmt = db()->prepare('SELECT id, provider, card_holder, brand, last4, exp_month, exp_year, phone, billing_address, is_default, created_at FROM payment_methods WHERE user_id=? ORDER BY is_default DESC, id DESC');
$stmt->execute([(int)$user['id']]);
$methods = array_map(static function(array $row): array {
    return [
        'id'=>(int)$row['id'],
        'provider'=>(string)$row['provider'],
        'cardHolder'=>(string)$row['card_holder'],
        'brand'=>(string)$row['brand'],
        'last4'=>(string)$row['last4'],
        'masked'=>'•••• •••• •••• ' . (string)$row['last4'],
        'expMonth'=>(int)$row['exp_month'],
        'expYear'=>(int)$row['exp_year'],
        'phone'=>$row['phone'] ?: null,
        'address'=>$row['billing_address'] ?: null,
        'isDefault'=>(bool)$row['is_default'],
        'createdAt'=>$row['created_at'],
    ];
}, $stmt->fetchAll());
json_response(['methods'=>$methods]);
