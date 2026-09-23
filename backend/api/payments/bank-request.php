<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();
$user = require_auth();

$packages = [
    500 => 49.90,
    1500 => 129.90,
    5000 => 399.90,
    12000 => 849.90,
];
try {
    $data = request_json();
    $coins = (int)($data['coinAmount'] ?? 0);
    $holder = trim((string)($data['accountHolder'] ?? ''));
    $iban = normalize_iban((string)($data['iban'] ?? ''));
    if (!isset($packages[$coins])) json_response(['message' => 'Geçersiz coin paketi.'], 400);
    if (text_len($holder) < 3 || !valid_iban($iban)) json_response(['message' => 'Hesap sahibi ve geçerli IBAN zorunludur.'], 400);
    $reference = 'BUFF-' . strtoupper(bin2hex(random_bytes(5)));
    $stmt = db()->prepare('INSERT INTO bank_payment_requests (user_id, coin_amount, money_amount, sender_account_holder, sender_iban_encrypted, sender_iban_last4, reference_code) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([(int)$user['id'], $coins, $packages[$coins], text_substr($holder, 0, 120), encrypt_sensitive($iban), substr($iban, -4), $reference]);
    json_response(['message' => 'Banka transferi talebi oluşturuldu.', 'request' => [
        'id' => (int)db()->lastInsertId(), 'coinAmount' => $coins, 'moneyAmount' => $packages[$coins], 'currency' => 'TRY', 'referenceCode' => $reference, 'status' => 'pending_verification'
    ]], 201);
} catch (Throwable $e) {
    json_response(['message' => 'Banka transferi talebi oluşturulamadı.'], 500);
}
