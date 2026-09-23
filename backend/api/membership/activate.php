<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();
require_auth();
json_response([
    'message' => 'VIP üyelikleri coin ile etkinleştirilemez. Plus, Pro ve Premium satın alımları kartla güvenli ödeme ekranından yapılır.',
    'paymentUrl' => './frontend/settings.php#membership'
], 409);
