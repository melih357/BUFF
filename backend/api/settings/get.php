<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
$user = require_auth();
$userId = (int)$user['id'];
$features = user_features($userId);
$prefs = user_preferences($userId);

$stmt = db()->prepare('SELECT id, account_holder, iban_last4 FROM bank_accounts WHERE user_id = ? AND is_default = 1 ORDER BY id DESC LIMIT 1');
$stmt->execute([$userId]);
$bank = $stmt->fetch();

$cardStmt = db()->prepare('SELECT id, card_holder, brand, last4, exp_month, exp_year, phone, billing_address, is_default FROM payment_methods WHERE user_id = ? ORDER BY is_default DESC, id DESC');
$cardStmt->execute([$userId]);
$paymentMethods = array_map(static fn(array $row): array => [
    'id' => (int)$row['id'],
    'cardHolder' => (string)$row['card_holder'],
    'brand' => (string)$row['brand'],
    'last4' => (string)$row['last4'],
    'masked' => '•••• •••• •••• ' . (string)$row['last4'],
    'expMonth' => (int)$row['exp_month'],
    'expYear' => (int)$row['exp_year'],
    'phone' => $row['phone'] ?: null,
    'address' => $row['billing_address'] ?: null,
    'isDefault' => (bool)$row['is_default'],
], $cardStmt->fetchAll());

$tier = is_vip_feature($features) ? strtolower((string)($features['vip_tier'] ?? 'plus')) : '';
$themeMatrix = [
    'base' => ['buff','indigo','graphite'],
    'plus' => ['buff','indigo','graphite','ocean','forest'],
    'pro' => ['buff','indigo','graphite','ocean','forest','rose','amber'],
    'premium' => ['buff','indigo','graphite','ocean','forest','rose','amber','violet'],
];
$allowed = $themeMatrix[$tier ?: 'base'] ?? $themeMatrix['base'];

json_response([
    'user' => public_user($user),
    'preferences' => [
        'themeMode' => (string)$prefs['theme_mode'],
        'accentTheme' => (string)$prefs['accent_theme'],
        'sidebarCollapsed' => (bool)$prefs['sidebar_collapsed'],
        'defaultStreamDelay' => (int)$prefs['default_stream_delay'],
        'reduceMotion' => (bool)$prefs['reduce_motion'],
    ],
    'membership' => [
        'tier' => $tier,
        'isVip' => is_vip_feature($features),
        'vipUntil' => $features['vip_until'] ?? null,
        'allowedAccentThemes' => $allowed,
    ],
    'bankAccount' => $bank ? [
        'id' => (int)$bank['id'],
        'accountHolder' => (string)$bank['account_holder'],
        'maskedIban' => masked_iban_from_last4((string)$bank['iban_last4']),
    ] : null,
    'paymentMethods' => $paymentMethods,
    'billingContact' => [
        'phone' => (string)($user['phone'] ?? ''),
        'address' => (string)($user['billing_address'] ?? ''),
    ],
    'creatorRate' => creator_coin_try_rate(),
]);
