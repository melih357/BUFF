<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
$user = require_auth();
$features = user_features((int)$user['id']);
$tier = is_vip_feature($features) ? strtolower((string)($features['vip_tier'] ?? 'plus')) : '';
json_response(['membership' => [
    'isOg' => (bool)$features['is_og'],
    'isVip' => is_vip_feature($features),
    'tier' => $tier,
    'vipUntil' => $features['vip_until'] ?? null,
    'chatColor' => (string)$features['chat_color'],
    'plans' => [
        ['id'=>'plus','name'=>'Plus','priceTry'=>99.90,'benefits'=>['VIP rozeti','Özel stickerlar','Ocean ve Forest tema renkleri']],
        ['id'=>'pro','name'=>'Pro','priceTry'=>199.90,'benefits'=>['Plus özellikleri','Daha fazla chat rengi','Rose ve Amber tema renkleri','Pro profil halkası']],
        ['id'=>'premium','name'=>'Premium','priceTry'=>349.90,'benefits'=>['Pro özellikleri','Tüm tema paletleri','Premium profil efekti','Öncelikli destek etiketi']],
    ],
]]);
