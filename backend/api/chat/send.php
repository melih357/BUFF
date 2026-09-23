<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();
$user = require_auth();

function find_chat_target(string $raw): ?array {
    $username = ltrim(trim($raw), '@');
    if ($username === '') return null;
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function is_stream_mod_user(int $streamOwnerId, int $userId): bool {
    $stmt = db()->prepare('SELECT 1 FROM stream_moderators WHERE streamer_user_id = ? AND moderator_user_id = ? LIMIT 1');
    $stmt->execute([$streamOwnerId, $userId]);
    return (bool)$stmt->fetchColumn();
}

function handle_slash_command(array $user, array $stream, string $message): array {
    $parts = preg_split('/\s+/', trim($message)) ?: [];
    $command = strtolower((string)array_shift($parts));
    $streamId = (int)$stream['id'];
    $ownerId = (int)$stream['user_id'];
    $actorId = (int)$user['id'];
    $role = stream_role($streamId, $actorId);

    if ($command === '/help') {
        $commands = ['/help'];
        if (in_array($role, ['owner', 'moderator'], true)) {
            $commands = array_merge($commands, ['/mute @kullanici 10', '/unmute @kullanici', '/ban @kullanici', '/unban @kullanici', '/clear', '/slow 5']);
        }
        if ($role === 'owner') {
            $commands = array_merge($commands, ['/mod @kullanici', '/unmod @kullanici']);
        }
        return ['ok' => true, 'command' => true, 'message' => 'Komutlar: ' . implode(' • ', $commands)];
    }

    if (!in_array($role, ['owner', 'moderator'], true)) {
        throw new RuntimeException('Bu slash komutu için moderatör yetkisi gerekir.');
    }

    if ($command === '/clear') {
        db()->prepare('DELETE FROM chat_messages WHERE stream_id = ?')->execute([$streamId]);
        return ['ok' => true, 'command' => true, 'message' => 'Chat temizlendi.'];
    }

    if ($command === '/slow') {
        $seconds = max(0, min(120, (int)($parts[0] ?? 5)));
        $stmt = db()->prepare('INSERT INTO stream_chat_settings (stream_id, slow_mode_seconds) VALUES (?, ?) ON DUPLICATE KEY UPDATE slow_mode_seconds = VALUES(slow_mode_seconds)');
        $stmt->execute([$streamId, $seconds]);
        return ['ok' => true, 'command' => true, 'message' => $seconds > 0 ? "Slow mode {$seconds} saniye olarak ayarlandı." : 'Slow mode kapatıldı.'];
    }

    if (in_array($command, ['/mod', '/unmod'], true)) {
        if ($role !== 'owner') {
            throw new RuntimeException('Moderatör atama komutlarını yalnızca yayıncı kullanabilir.');
        }
        $target = find_chat_target((string)($parts[0] ?? ''));
        if (!$target) throw new RuntimeException('Kullanıcı bulunamadı.');
        if ((int)$target['id'] === $ownerId) throw new RuntimeException('Kendini moderatör olarak değiştiremezsin.');
        if ($command === '/mod') {
            $stmt = db()->prepare('INSERT IGNORE INTO stream_moderators (streamer_user_id, moderator_user_id) VALUES (?, ?)');
            $stmt->execute([$ownerId, (int)$target['id']]);
            create_notification((int)$target['id'], $ownerId, 'moderator', 'Moderatör oldun', (string)$user['display_name'] . ' seni kanal moderatörü yaptı.', './profile.php');
            return ['ok' => true, 'command' => true, 'message' => '@' . $target['username'] . ' moderatör yapıldı.'];
        }
        db()->prepare('DELETE FROM stream_moderators WHERE streamer_user_id = ? AND moderator_user_id = ?')->execute([$ownerId, (int)$target['id']]);
        return ['ok' => true, 'command' => true, 'message' => '@' . $target['username'] . ' moderatörlükten çıkarıldı.'];
    }

    if (in_array($command, ['/mute', '/unmute', '/ban', '/unban'], true)) {
        $target = find_chat_target((string)($parts[0] ?? ''));
        if (!$target) throw new RuntimeException('Kullanıcı bulunamadı.');
        $targetId = (int)$target['id'];
        if ($targetId === $ownerId) throw new RuntimeException('Yayıncıya moderasyon uygulanamaz.');
        if ($role === 'moderator' && is_stream_mod_user($ownerId, $targetId)) {
            throw new RuntimeException('Başka bir moderatöre bu işlemi uygulayamazsın.');
        }

        if ($command === '/unmute' || $command === '/unban') {
            $type = $command === '/unmute' ? 'mute' : 'ban';
            db()->prepare('DELETE FROM chat_restrictions WHERE stream_id = ? AND user_id = ? AND restriction_type = ?')->execute([$streamId, $targetId, $type]);
            return ['ok' => true, 'command' => true, 'message' => '@' . $target['username'] . ' için ' . $type . ' kaldırıldı.'];
        }

        $type = $command === '/mute' ? 'mute' : 'ban';
        $expiresAt = null;
        if ($type === 'mute') {
            $minutes = max(1, min(1440, (int)($parts[1] ?? 10)));
            $expiresAt = (new DateTimeImmutable('now'))->modify('+' . $minutes . ' minutes')->format('Y-m-d H:i:s');
        }
        db()->prepare('DELETE FROM chat_restrictions WHERE stream_id = ? AND user_id = ? AND restriction_type = ?')->execute([$streamId, $targetId, $type]);
        $stmt = db()->prepare('INSERT INTO chat_restrictions (stream_id, user_id, restriction_type, expires_at, created_by_user_id) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$streamId, $targetId, $type, $expiresAt, $actorId]);
        return ['ok' => true, 'command' => true, 'message' => '@' . $target['username'] . ($type === 'ban' ? ' chatten yasaklandı.' : ' geçici olarak susturuldu.')];
    }

    throw new RuntimeException('Bilinmeyen slash komutu. /help yazarak komutları görebilirsin.');
}

try {
    $data = request_json();
    $streamId = (int)($data['streamId'] ?? 0);
    $message = trim((string)($data['message'] ?? ''));
    $stickerCode = trim((string)($data['stickerCode'] ?? ''));
    if ($streamId < 1 || ($message === '' && $stickerCode === '')) {
        json_response(['message' => 'Mesaj veya sticker seçimi gerekli.'], 400);
    }
    $stream = v17_stream($streamId,$user);
    if (!$stream || !(bool)$stream['is_live']) {
        json_response(['message' => 'Bu yayın artık canlı değil.'], 404);
    }

    if ($message !== '' && str_starts_with($message, '/')) {
        try {
            $result = handle_slash_command($user, $stream, $message);
            json_response($result);
        } catch (RuntimeException $e) {
            json_response(['message' => $e->getMessage()], 403);
        }
    }

    $role = stream_role($streamId, (int)$user['id']);
    if ($role === 'viewer') {
        $stmt = db()->prepare("SELECT restriction_type, expires_at FROM chat_restrictions WHERE stream_id = ? AND user_id = ? AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY id DESC");
        $stmt->execute([$streamId, (int)$user['id']]);
        foreach ($stmt->fetchAll() as $restriction) {
            if ((string)$restriction['restriction_type'] === 'ban') json_response(['message' => 'Bu yayının chatinden yasaklandın.'], 403);
            if ((string)$restriction['restriction_type'] === 'mute') json_response(['message' => 'Bu chat için geçici olarak susturuldun.'], 403);
        }
    }

    $features = user_features((int)$user['id']);
    $isVip = is_vip_feature($features);
    $allowedStickers = ['party_parrot', 'gg_fire', 'logi_love', 'hype_train', 'king_crown', 'space_cat'];
    $messageType = 'text';
    if ($stickerCode !== '') {
        if (!$isVip) json_response(['message' => 'Özel sticker göndermek için VIP üyelik gerekir.'], 403);
        if (!in_array($stickerCode, $allowedStickers, true)) json_response(['message' => 'Geçersiz sticker.'], 400);
        $messageType = 'sticker';
        $message = '[sticker:' . $stickerCode . ']';
    }

    $message = text_substr($message, 0, 300);
    if ($messageType === 'text') {
        $moderation = v14_moderate_text($message);
        if (!$moderation['allow']) json_response(['message' => 'Mesaj otomatik güvenlik filtresine takıldı.'], 422);
    }
    if ($role === 'viewer') {
        $settings = db()->prepare('SELECT slow_mode_seconds FROM stream_chat_settings WHERE stream_id = ? LIMIT 1');
        $settings->execute([$streamId]);
        $slow = max(1, (int)(($settings->fetch()['slow_mode_seconds'] ?? 1)));
        $rate = db()->prepare('SELECT created_at FROM chat_messages WHERE stream_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1');
        $rate->execute([$streamId, (int)$user['id']]);
        $last = $rate->fetch();
        if ($last) {
            $lastTime = strtotime((string)$last['created_at']);
            if ($lastTime && (time() - $lastTime) < $slow) {
                json_response(['message' => "Slow mode açık. {$slow} saniye beklemelisin."], 429);
            }
        }
    }

    $pdo = db();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('INSERT INTO chat_messages (stream_id, user_id, message) VALUES (?, ?, ?)');
    $stmt->execute([$streamId, (int)$user['id'], $message]);
    $messageId = (int)$pdo->lastInsertId();
    $color = $isVip ? (string)$features['chat_color'] : '#E7ECFF';
    $stmt = $pdo->prepare('INSERT INTO chat_message_meta (message_id, message_type, sticker_code, chat_color) VALUES (?, ?, ?, ?)');
    $stmt->execute([$messageId, $messageType, $stickerCode !== '' ? $stickerCode : null, $color]);
    $pdo->commit();
    v14_channel_loyalty_add((int)$user['id'], (int)$stream['user_id'], ['messages'=>1,'xp'=>2]);
    v14_add_loyalty((int)$user['id'], 1, 2);
    v14_record_interaction((int)$user['id'], (int)$stream['user_id'], 'stream', $streamId, 'chat', 1.2);
    v14_publish('chat.message', ['id'=>$messageId,'streamId'=>$streamId,'userId'=>(int)$user['id'],'message'=>$message,'messageType'=>$messageType], null, $streamId, 'stream.'.$streamId);
    json_response(['ok' => true, 'id' => $messageId]);
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    json_response(['message' => 'Mesaj gönderilemedi.'], 500);
}
