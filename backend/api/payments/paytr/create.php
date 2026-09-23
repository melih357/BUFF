<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/bootstrap.php';
require_post();
$user = require_auth();

$packages = v17_packages();
$membershipPlans = [
    'plus' => ['amount_kurus' => 9990, 'label' => 'Plus Üyelik', 'days' => 30],
    'pro' => ['amount_kurus' => 19990, 'label' => 'Pro Üyelik', 'days' => 30],
    'premium' => ['amount_kurus' => 34990, 'label' => 'Premium Üyelik', 'days' => 30],
];

function paytr_client_ip(): string {
    $env = app_env();
    $forwarded = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($forwarded !== '') {
        $parts = array_map('trim', explode(',', $forwarded));
        if (filter_var($parts[0], FILTER_VALIDATE_IP)) return $parts[0];
    }
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (($ip === '127.0.0.1' || $ip === '::1' || $ip === '') && !empty($env['PAYTR_TEST_USER_IP'])) {
        $ip = (string)$env['PAYTR_TEST_USER_IP'];
    }
    return $ip;
}

try {
    $data = request_json();
    $coins = (int)($data['coinAmount'] ?? 0);
    $membershipTier = strtolower(trim((string)($data['membershipTier'] ?? '')));
    $userPhone = trim((string)($data['phone'] ?? ''));
    $userAddress = trim((string)($data['address'] ?? ''));
    $orderType = $membershipTier !== '' ? 'membership' : 'coin';
    if ($orderType === 'coin' && !isset($packages[$coins])) json_response(['message' => 'Geçersiz coin paketi.'], 400);
    if ($orderType === 'membership' && !isset($membershipPlans[$membershipTier])) json_response(['message' => 'Geçersiz üyelik paketi.'], 400);
    if (!preg_match('/^[0-9+()\s-]{7,24}$/', $userPhone)) json_response(['message' => 'Geçerli bir telefon numarası girin.'], 400);
    if (text_len($userAddress) < 8) json_response(['message' => 'Fatura/iletişim adresi en az 8 karakter olmalı.'], 400);

    // İletişim/fatura bilgileri kullanıcı hesabında kalır. Kart PAN/CVV burada hiçbir zaman alınmaz veya saklanmaz.
    db()->prepare('UPDATE users SET phone=?, billing_address=? WHERE id=?')
        ->execute([text_substr($userPhone,0,24), text_substr($userAddress,0,400), (int)$user['id']]);

    $env = app_env();
    $merchantId = trim((string)($env['PAYTR_MERCHANT_ID'] ?? ''));
    $merchantKey = trim((string)($env['PAYTR_MERCHANT_KEY'] ?? ''));
    $merchantSalt = trim((string)($env['PAYTR_MERCHANT_SALT'] ?? ''));
    $baseUrl = rtrim((string)($env['PUBLIC_BASE_URL'] ?? ''), '/');
    if ($merchantId === '' || $merchantKey === '' || $merchantSalt === '' || $baseUrl === '') {
        json_response(['message' => 'PayTR ayarları henüz tamamlanmadı. .env dosyasındaki merchant bilgilerini doldurun.'], 503);
    }

    $ip = paytr_client_ip();
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        json_response(['message' => 'Geçerli kullanıcı IP adresi belirlenemedi. Lokal test için PAYTR_TEST_USER_IP ayarlayın.'], 400);
    }

    $amountKurus = $orderType === 'membership' ? (int)$membershipPlans[$membershipTier]['amount_kurus'] : $packages[$coins];
    $amountTl = number_format($amountKurus / 100, 2, '.', '');
    $merchantOid = 'BUFF' . (int)$user['id'] . date('ymdHis') . strtoupper(bin2hex(random_bytes(4)));
    $basketLabel = $orderType === 'membership' ? ('BUFF ' . $membershipPlans[$membershipTier]['label']) : ('BUFF ' . $coins . ' Coin');
    $basket = base64_encode(json_encode([[$basketLabel, $amountTl, 1]], JSON_UNESCAPED_UNICODE));
    $noInstallment = '0';
    $maxInstallment = '0';
    $currency = 'TL';
    $testMode = (string)($env['PAYTR_TEST_MODE'] ?? '1');
    $debugOn = (string)($env['PAYTR_DEBUG_ON'] ?? '1');
    $email = (string)$user['email'];
    $hashStr = $merchantId . $ip . $merchantOid . $email . $amountKurus . $basket . $noInstallment . $maxInstallment . $currency . $testMode;
    $token = base64_encode(hash_hmac('sha256', $hashStr . $merchantSalt, $merchantKey, true));

    $orderMeta = $orderType === 'membership' ? json_encode(['membershipTier' => $membershipTier, 'days' => (int)$membershipPlans[$membershipTier]['days']], JSON_UNESCAPED_UNICODE) : null;
    $stmt = db()->prepare('INSERT INTO payment_orders (user_id, provider, order_type, merchant_oid, coin_amount, order_meta_json, amount_kurus, currency, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([(int)$user['id'], 'paytr', $orderType, $merchantOid, $orderType === 'coin' ? $coins : 0, $orderMeta, $amountKurus, $currency, 'token_requested']);

    $post = [
        'merchant_id' => $merchantId,
        'user_ip' => $ip,
        'merchant_oid' => $merchantOid,
        'email' => $email,
        'payment_amount' => $amountKurus,
        'paytr_token' => $token,
        'user_basket' => $basket,
        'debug_on' => $debugOn,
        'no_installment' => $noInstallment,
        'max_installment' => $maxInstallment,
        'user_name' => (string)$user['display_name'],
        'user_address' => text_substr($userAddress, 0, 400),
        'user_phone' => text_substr($userPhone, 0, 24),
        'merchant_ok_url' => $baseUrl . '/frontend/payment.php?result=success&mode=' . rawurlencode($orderType) . ($orderType === 'membership' ? '&membership=' . rawurlencode($membershipTier) : '&coins=' . rawurlencode((string)$coins)),
        'merchant_fail_url' => $baseUrl . '/frontend/payment.php?result=failed&mode=' . rawurlencode($orderType) . ($orderType === 'membership' ? '&membership=' . rawurlencode($membershipTier) : '&coins=' . rawurlencode((string)$coins)),
        'timeout_limit' => '30',
        'currency' => $currency,
        'test_mode' => $testMode,
        'lang' => 'tr',
    ];

    $ch = curl_init('https://www.paytr.com/odeme/api/get-token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($post),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($body === false || $curlError !== '') {
        db()->prepare('UPDATE payment_orders SET status = ?, failure_reason = ? WHERE merchant_oid = ?')->execute(['token_failed', text_substr($curlError, 0, 255), $merchantOid]);
        json_response(['message' => 'Ödeme sağlayıcısına bağlanılamadı.'], 502);
    }

    $result = json_decode((string)$body, true);
    if ($httpCode < 200 || $httpCode >= 300 || !is_array($result) || ($result['status'] ?? '') !== 'success' || empty($result['token'])) {
        $reason = is_array($result) ? (string)($result['reason'] ?? 'Token alınamadı') : 'Geçersiz sağlayıcı yanıtı';
        db()->prepare('UPDATE payment_orders SET status = ?, failure_reason = ? WHERE merchant_oid = ?')->execute(['token_failed', text_substr($reason, 0, 255), $merchantOid]);
        json_response(['message' => 'PayTR ödeme formu açılamadı: ' . $reason], 502);
    }

    db()->prepare('UPDATE payment_orders SET status = ?, provider_reference = ? WHERE merchant_oid = ?')->execute(['pending_payment', (string)$result['token'], $merchantOid]);
    json_response([
        'message' => 'Güvenli ödeme formu hazır.',
        'merchantOid' => $merchantOid,
        'orderType' => $orderType,
        'membershipTier' => $orderType === 'membership' ? $membershipTier : null,
        'iframeUrl' => 'https://www.paytr.com/odeme/guvenli/' . rawurlencode((string)$result['token']),
    ]);
} catch (Throwable $e) {
    json_response(['message' => 'Gerçek ödeme oturumu oluşturulamadı.'], 500);
}
