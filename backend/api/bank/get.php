<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
$user = require_auth();
$stmt = db()->prepare('SELECT id, account_holder, iban_last4, is_default, created_at FROM bank_accounts WHERE user_id = ? ORDER BY is_default DESC, id DESC LIMIT 1');
$stmt->execute([(int)$user['id']]);
$row = $stmt->fetch();
json_response(['bankAccount' => $row ? [
    'id' => (int)$row['id'],
    'accountHolder' => (string)$row['account_holder'],
    'maskedIban' => masked_iban_from_last4((string)$row['iban_last4']),
    'last4' => (string)$row['iban_last4'],
] : null]);
