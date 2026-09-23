<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();
$user = require_auth();

try {
    $data = request_json();
    $holder = trim((string)($data['accountHolder'] ?? ''));
    $iban = normalize_iban((string)($data['iban'] ?? ''));
    if (text_len($holder) < 3 || text_len($holder) > 120) {
        json_response(['message' => 'Hesap sahibinin adını girin.'], 400);
    }
    if (!valid_iban($iban)) {
        json_response(['message' => 'Geçerli bir IBAN girin.'], 400);
    }
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE bank_accounts SET is_default = 0 WHERE user_id = ?')->execute([(int)$user['id']]);
    $stmt = $pdo->prepare('INSERT INTO bank_accounts (user_id, account_holder, iban_encrypted, iban_last4, is_default) VALUES (?, ?, ?, ?, 1)');
    $stmt->execute([(int)$user['id'], text_substr($holder, 0, 120), encrypt_sensitive($iban), substr($iban, -4)]);
    $id = (int)$pdo->lastInsertId();
    $pdo->commit();
    json_response(['message' => 'Banka hesabı güvenli şekilde kaydedildi.', 'bankAccount' => [
        'id' => $id,
        'accountHolder' => $holder,
        'maskedIban' => masked_iban_from_last4(substr($iban, -4)),
        'last4' => substr($iban, -4),
    ]]);
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    json_response(['message' => 'Banka hesabı kaydedilemedi.'], 500);
}
