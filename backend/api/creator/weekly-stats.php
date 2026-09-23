<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
$user = require_auth();

try {
    $pdo = db();
    $tz = new DateTimeZone('Europe/Istanbul');
    $now = new DateTimeImmutable('now', $tz);
    $start = $now->modify('monday this week')->setTime(0, 0, 0);
    $end = $start->modify('+7 days');
    $startSql = $start->format('Y-m-d H:i:s');
    $endSql = $end->format('Y-m-d H:i:s');
    $uid = (int)$user['id'];

    $stream = $pdo->prepare("SELECT COUNT(*) stream_count, COALESCE(SUM(TIMESTAMPDIFF(SECOND, started_at, COALESCE(ended_at, NOW()))),0) total_stream_seconds FROM streams WHERE user_id=? AND started_at>=? AND started_at<?");
    $stream->execute([$uid, $startSql, $endSql]);
    $streamRow = $stream->fetch() ?: [];

    $watchSeconds = 0;
    if (db_table_exists('content_interactions')) {
        $st = $pdo->prepare("SELECT COALESCE(SUM(duration_seconds),0) FROM content_interactions WHERE channel_user_id=? AND interaction_type='watch' AND created_at>=? AND created_at<?");
        $st->execute([$uid, $startSql, $endSql]);
        $watchSeconds = (int)$st->fetchColumn();
    }

    $messages = 0;
    $uniqueChatters = 0;
    if (db_table_exists('chat_messages')) {
        $st = $pdo->prepare("SELECT COUNT(*) total_messages, COUNT(DISTINCT cm.user_id) unique_chatters FROM chat_messages cm JOIN streams s ON s.id=cm.stream_id WHERE s.user_id=? AND cm.created_at>=? AND cm.created_at<?");
        $st->execute([$uid, $startSql, $endSql]);
        $r = $st->fetch() ?: [];
        $messages = (int)($r['total_messages'] ?? 0);
        $uniqueChatters = (int)($r['unique_chatters'] ?? 0);
    }

    $giftCoins = 0;
    $giftCount = 0;
    if (db_table_exists('gift_events')) {
        $st = $pdo->prepare("SELECT COALESCE(SUM(total_price),0) coins, COUNT(*) gift_count FROM gift_events WHERE recipient_user_id=? AND created_at>=? AND created_at<?");
        $st->execute([$uid, $startSql, $endSql]);
        $r = $st->fetch() ?: [];
        $giftCoins = (int)($r['coins'] ?? 0);
        $giftCount = (int)($r['gift_count'] ?? 0);
    }

    $peakViewers = 0;
    $avgViewers = 0;
    $peakChatters = 0;
    if (db_table_exists('stream_metric_samples')) {
        $st = $pdo->prepare("SELECT COALESCE(MAX(viewer_count),0) peak_viewers, COALESCE(ROUND(AVG(viewer_count)),0) avg_viewers, COALESCE(MAX(chatter_count),0) peak_chatters FROM stream_metric_samples WHERE creator_user_id=? AND sampled_at>=? AND sampled_at<?");
        $st->execute([$uid, $startSql, $endSql]);
        $r = $st->fetch() ?: [];
        $peakViewers = (int)($r['peak_viewers'] ?? 0);
        $avgViewers = (int)($r['avg_viewers'] ?? 0);
        $peakChatters = (int)($r['peak_chatters'] ?? 0);
    }

    json_response([
      'period' => [
        'start' => $start->format('Y-m-d'),
        'end' => $end->modify('-1 day')->format('Y-m-d'),
        'label' => $start->format('d.m') . ' – ' . $end->modify('-1 day')->format('d.m'),
      ],
      'creator' => [
        'displayName' => (string)$user['display_name'],
        'username' => (string)$user['username'],
        'avatarUrl' => $user['avatar_url'] ?: null,
      ],
      'stats' => [
        'streamCount' => (int)($streamRow['stream_count'] ?? 0),
        'totalStreamSeconds' => (int)($streamRow['total_stream_seconds'] ?? 0),
        'watchSeconds' => $watchSeconds,
        'messageCount' => $messages,
        'uniqueChatters' => $uniqueChatters,
        'giftCoins' => $giftCoins,
        'giftCount' => $giftCount,
        'peakViewers' => $peakViewers,
        'avgViewers' => $avgViewers,
        'peakChatters' => $peakChatters,
      ],
    ]);
} catch (Throwable $e) {
    error_log('[BUFF weekly stats] ' . $e->getMessage());
    json_response(['message' => 'Haftalık yayın istatistikleri alınamadı.'], 500);
}
