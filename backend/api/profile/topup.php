<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();
$user = require_auth();

$packs = [500 => 'Mini Paket', 1500 => 'Destek Paketi', 5000 => 'Yayıncı Paketi'];
try {
    $data = request_json();
    $amount = (int)($data['amount'] ?? 0);
    if (!isset($packs[$amount])) {
        json_response(['message' => 'Geçersiz bakiye paketi.'], 400);
    }
    credit_wallet((int)$user['id'], $amount, 'topup', $packs[$amount] . ' bakiye yüklemesi');
    $updated = fetch_user_by_id((int)$user['id']);
    json_response(['message' => 'Bakiye eklendi.', 'user' => public_user($updated)]);
} catch (Throwable $e) {
    json_response(['message' => 'Bakiye yüklenemedi.'], 500);
}
