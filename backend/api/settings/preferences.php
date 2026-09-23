<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();
$user = require_auth();
$userId = (int)$user['id'];

try {
    $data = request_json();
    $current = user_preferences($userId);
    $themeMode = strtolower((string)($data['themeMode'] ?? $current['theme_mode']));
    $accentTheme = strtolower((string)($data['accentTheme'] ?? $current['accent_theme']));
    $sidebarCollapsed = array_key_exists('sidebarCollapsed',$data)?(!empty($data['sidebarCollapsed'])?1:0):(int)$current['sidebar_collapsed'];
    $reduceMotion = array_key_exists('reduceMotion',$data)?(!empty($data['reduceMotion'])?1:0):(int)$current['reduce_motion'];
    $delay = (int)($data['defaultStreamDelay'] ?? $current['default_stream_delay']);

    if (!in_array($themeMode, ['light','dark','system'], true)) $themeMode = 'dark';
    if (!in_array($delay, [0,3,5,10,15,30], true)) $delay = 5;

    $features = user_features($userId);
    $tier = is_vip_feature($features) ? strtolower((string)($features['vip_tier'] ?? 'plus')) : '';
    $allowed = ['buff','indigo','graphite'];
    if (vip_tier_rank($tier) >= 1) $allowed = array_merge($allowed, ['ocean','forest']);
    if (vip_tier_rank($tier) >= 2) $allowed = array_merge($allowed, ['rose','amber']);
    if (vip_tier_rank($tier) >= 3) $allowed[] = 'violet';
    if (!in_array($accentTheme, $allowed, true)) {
        json_response(['message' => 'Bu renk paleti mevcut üyelik seviyende kullanılamıyor.'], 403);
    }

    $stmt = db()->prepare('INSERT INTO user_preferences (user_id, theme_mode, accent_theme, sidebar_collapsed, default_stream_delay, reduce_motion) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE theme_mode=VALUES(theme_mode), accent_theme=VALUES(accent_theme), sidebar_collapsed=VALUES(sidebar_collapsed), default_stream_delay=VALUES(default_stream_delay), reduce_motion=VALUES(reduce_motion)');
    $stmt->execute([$userId, $themeMode, $accentTheme, $sidebarCollapsed, $delay, $reduceMotion]);
    db()->prepare('UPDATE user_features SET accent_theme = ? WHERE user_id = ?')->execute([$accentTheme, $userId]);

    json_response(['message' => 'Görünüm ve yayın tercihleri kaydedildi.', 'preferences' => [
        'themeMode'=>$themeMode,'accentTheme'=>$accentTheme,'sidebarCollapsed'=>(bool)$sidebarCollapsed,'defaultStreamDelay'=>$delay,'reduceMotion'=>(bool)$reduceMotion
    ]]);
} catch (Throwable $e) {
    json_response(['message' => 'Tercihler kaydedilemedi.'], 500);
}
