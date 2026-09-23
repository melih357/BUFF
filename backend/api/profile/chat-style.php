<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();
$user = require_auth();
$features = user_features((int)$user['id']);
if (!is_vip_feature($features)) {
    json_response(['message' => 'Renkli chat yazısı VIP üyeliğe özeldir.'], 403);
}
$data = request_json();
$color = strtoupper(trim((string)($data['color'] ?? '')));
$allowed = ['#E7ECFF', '#7AF0C7', '#8FC7FF', '#C9A7FF', '#FF9BCB', '#FFD166', '#FF8B7A'];
if (!in_array($color, $allowed, true)) {
    json_response(['message' => 'Geçersiz chat rengi.'], 400);
}
$stmt = db()->prepare('UPDATE user_features SET chat_color = ? WHERE user_id = ?');
$stmt->execute([$color, (int)$user['id']]);
json_response(['message' => 'Chat rengin güncellendi.', 'color' => $color]);
