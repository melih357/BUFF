<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();
$user = require_auth();
$data = request_json();
$lang = strtolower(trim((string)($data['language'] ?? 'tr')));
$allowed = ['tr', 'en', 'de', 'ar', 'es'];
if (!in_array($lang, $allowed, true)) {
    json_response(['message' => 'Desteklenmeyen dil.'], 400);
}
$stmt = db()->prepare('INSERT INTO user_features (user_id, preferred_language) VALUES (?, ?) ON DUPLICATE KEY UPDATE preferred_language = VALUES(preferred_language)');
$stmt->execute([(int)$user['id'], $lang]);
json_response(['message' => 'Dil tercihi kaydedildi.', 'language' => $lang]);
