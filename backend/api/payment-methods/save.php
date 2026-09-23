<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();
$user = require_auth();
try {
    $data = request_json();
    // Security invariant: frontend may send metadata only. Never accept/store PAN or CVV.
    if (isset($data['cardNumber']) || isset($data['cvv']) || isset($data['cvc'])) {
        json_response(['message'=>'Güvenlik nedeniyle kart numarası/CVV sunucuya gönderilmemelidir.'], 400);
    }
    $holder = trim((string)($data['cardHolder'] ?? ''));
    $brand = strtolower(trim((string)($data['brand'] ?? 'card')));
    $last4 = preg_replace('/\D+/', '', (string)($data['last4'] ?? '')) ?? '';
    $month = (int)($data['expMonth'] ?? 0);
    $year = (int)($data['expYear'] ?? 0);
    $phone = trim((string)($data['phone'] ?? ''));
    $address = trim((string)($data['address'] ?? ''));
    if (text_len($holder) < 3 || text_len($holder) > 120) json_response(['message'=>'Kart üzerindeki ad soyad geçersiz.'],400);
    if (!preg_match('/^\d{4}$/', $last4)) json_response(['message'=>'Kartın son 4 hanesi doğrulanamadı.'],400);
    if ($month < 1 || $month > 12 || $year < (int)date('Y') || $year > (int)date('Y') + 20) json_response(['message'=>'Kart son kullanma tarihi geçersiz.'],400);
    if ($phone !== '' && !preg_match('/^[0-9+()\s-]{7,24}$/', $phone)) json_response(['message'=>'Geçerli bir telefon numarası girin.'],400);
    if ($address !== '' && text_len($address) < 8) json_response(['message'=>'Adres en az 8 karakter olmalı.'],400);
    $allowedBrands = ['visa','mastercard','amex','troy','card'];
    if (!in_array($brand, $allowedBrands, true)) $brand = 'card';
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE payment_methods SET is_default=0 WHERE user_id=?')->execute([(int)$user['id']]);
    $token = 'pm_' . bin2hex(random_bytes(18));
    $stmt = $pdo->prepare('INSERT INTO payment_methods (user_id,provider,provider_token,card_holder,brand,last4,exp_month,exp_year,phone,billing_address,is_default) VALUES (?,?,?,?,?,?,?,?,?,?,1)');
    $stmt->execute([(int)$user['id'],'local-token',$token,text_substr($holder,0,120),$brand,$last4,$month,$year,$phone !== '' ? text_substr($phone,0,24) : null,$address !== '' ? text_substr($address,0,400) : null]);
    if ($phone !== '' || $address !== '') {
        $pdo->prepare('UPDATE users SET phone=COALESCE(NULLIF(?,\'\'),phone), billing_address=COALESCE(NULLIF(?,\'\'),billing_address) WHERE id=?')->execute([$phone,$address,(int)$user['id']]);
    }
    $id=(int)$pdo->lastInsertId();
    $pdo->commit();
    json_response(['message'=>'Kart güvenli şekilde kaydedildi. Tam kart numarası ve CVV saklanmadı.','method'=>['id'=>$id,'brand'=>$brand,'last4'=>$last4,'masked'=>'•••• •••• •••• '.$last4,'cardHolder'=>$holder,'expMonth'=>$month,'expYear'=>$year,'isDefault'=>true]]);
} catch(Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    json_response(['message'=>'Kart kaydedilemedi.'],500);
}
