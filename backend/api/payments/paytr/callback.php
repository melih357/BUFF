<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/bootstrap.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $env = app_env();
    $merchantKey = trim((string)($env['PAYTR_MERCHANT_KEY'] ?? ''));
    $merchantSalt = trim((string)($env['PAYTR_MERCHANT_SALT'] ?? ''));
    if ($merchantKey === '' || $merchantSalt === '') throw new RuntimeException('PayTR callback anahtarları eksik.');

    $oid = trim((string)($_POST['merchant_oid'] ?? ''));
    $status = trim((string)($_POST['status'] ?? ''));
    $totalAmount = trim((string)($_POST['total_amount'] ?? '0'));
    $receivedHash = trim((string)($_POST['hash'] ?? ''));
    if ($oid === '' || $status === '' || $receivedHash === '') throw new RuntimeException('Eksik callback alanı.');

    $expectedHash = base64_encode(hash_hmac('sha256', $oid . $merchantSalt . $status . $totalAmount, $merchantKey, true));
    if (!hash_equals($expectedHash, $receivedHash)) throw new RuntimeException('Callback hash doğrulaması başarısız.');

    $pdo = db();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT * FROM payment_orders WHERE merchant_oid = ? FOR UPDATE');
    $stmt->execute([$oid]);
    $order = $stmt->fetch();
    if (!$order) throw new RuntimeException('Sipariş bulunamadı.');

    if (in_array((string)$order['status'], ['paid', 'failed', 'amount_mismatch'], true)) {
        $pdo->commit();
        echo 'OK';
        exit;
    }

    $payload = json_encode($_POST, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($status === 'success') {
        $paymentAmount = (int)($_POST['payment_amount'] ?? 0);
        if ((int)$order['amount_kurus'] !== $paymentAmount) {
            $stmt = $pdo->prepare('UPDATE payment_orders SET status = ?, failure_reason = ?, callback_payload = ? WHERE id = ?');
            $stmt->execute(['amount_mismatch', 'Callback temel ödeme tutarı sipariş tutarıyla eşleşmedi.', $payload, (int)$order['id']]);
        } else {
            if ((string)($order['order_type'] ?? 'coin') === 'membership') {
                $meta = json_decode((string)($order['order_meta_json'] ?? ''), true) ?: [];
                $tier = strtolower((string)($meta['membershipTier'] ?? 'plus'));
                $days = max(1, (int)($meta['days'] ?? 30));
                $featureStmt = $pdo->prepare('SELECT vip_until FROM user_features WHERE user_id = ? LIMIT 1');
                $featureStmt->execute([(int)$order['user_id']]);
                $feature = $featureStmt->fetch();
                $current = ($feature && !empty($feature['vip_until']) && strtotime((string)$feature['vip_until']) > time()) ? new DateTimeImmutable((string)$feature['vip_until']) : new DateTimeImmutable('now');
                $until = $current->modify('+' . $days . ' days')->format('Y-m-d H:i:s');
                $stmt = $pdo->prepare('UPDATE user_features SET vip_until = ?, vip_tier = ? WHERE user_id = ?');
                $stmt->execute([$until, $tier, (int)$order['user_id']]);
                $stmt = $pdo->prepare('INSERT INTO wallet_transactions (user_id, amount, type, description) VALUES (?, ?, ?, ?)');
                $stmt->execute([(int)$order['user_id'], 0, 'membership_purchase', 'PayTR ' . strtoupper($tier) . ' üyelik satın alma']);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?');
                $stmt->execute([(int)$order['coin_amount'], (int)$order['user_id']]);
                $stmt = $pdo->prepare('INSERT INTO wallet_transactions (user_id, amount, type, description) VALUES (?, ?, ?, ?)');
                $stmt->execute([(int)$order['user_id'], (int)$order['coin_amount'], 'real_coin_purchase', 'PayTR coin satın alma']);
            }
            $stmt = $pdo->prepare('UPDATE payment_orders SET status = ?, paid_at = NOW(), callback_payload = ?, failure_reason = NULL WHERE id = ?');
            $stmt->execute(['paid', $payload, (int)$order['id']]);
        }
    } else {
        $reason = trim((string)($_POST['failed_reason_msg'] ?? 'Ödeme başarısız.'));
        $stmt = $pdo->prepare('UPDATE payment_orders SET status = ?, failure_reason = ?, callback_payload = ? WHERE id = ?');
        $stmt->execute(['failed', text_substr($reason, 0, 255), $payload, (int)$order['id']]);
    }
    $pdo->commit();
    v14_finance_audit('paytr_callback','payment_order',(int)$order['id'],(int)$order['user_id'],[
        'merchantOid'=>$oid,'status'=>$status,'orderStatus'=>$status==='success'?'paid_or_mismatch':'failed','amountKurus'=>(int)$order['amount_kurus']
    ]);
    echo 'OK';
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo 'PAYTR notification failed';
}
