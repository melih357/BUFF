<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
ensure_admin_control_schema();
require_post();
$reporter = require_auth();

try {
    $data = request_json();
    $streamId = (int)($data['streamId'] ?? 0);
    $targetUserId = (int)($data['targetUserId'] ?? 0);
    $chatMessageId = (int)($data['chatMessageId'] ?? 0);
    $feedPostId = (int)($data['postId'] ?? $data['feedPostId'] ?? 0);
    $scope = strtolower(trim((string)($data['scope'] ?? 'creator')));
    $category = trim((string)($data['category'] ?? 'other'));
    $description = trim((string)($data['description'] ?? ''));

    $allowedCategories = ['harassment', 'spam', 'fraud', 'hate', 'nudity', 'violence', 'copyright', 'other'];
    $allowedScopes = ['creator','user','stream','chat_message','community_post'];
    if (!in_array($category, $allowedCategories, true) || text_len($description) < 5) {
        json_response(['message' => 'Şikayet kategorisi ve en az 5 karakter açıklama zorunludur.'], 400);
    }
    if (!in_array($scope, $allowedScopes, true)) $scope = 'creator';

    $pdo = db();
    $stream = null;
    $targetSnapshot = null;

    if ($scope === 'chat_message') {
        if ($chatMessageId < 1) json_response(['message' => 'Şikayet edilecek chat mesajı seçilmedi.'], 400);
        $stmt = $pdo->prepare('SELECT cm.id,cm.stream_id,cm.user_id,cm.message,s.title stream_title FROM chat_messages cm JOIN streams s ON s.id=cm.stream_id WHERE cm.id=? LIMIT 1');
        $stmt->execute([$chatMessageId]);
        $row = $stmt->fetch();
        if (!$row) json_response(['message' => 'Şikayet edilecek chat mesajı bulunamadı.'], 404);
        $targetUserId = (int)$row['user_id'];
        $streamId = (int)$row['stream_id'];
        $stream = get_stream_by_id($streamId);
        $targetSnapshot = text_substr((string)$row['message'], 0, 255);
    } elseif ($scope === 'community_post') {
        if ($feedPostId < 1) json_response(['message' => 'Şikayet edilecek topluluk gönderisi seçilmedi.'], 400);
        $stmt = $pdo->prepare('SELECT p.id,p.user_id,p.body FROM feed_posts p WHERE p.id=? LIMIT 1');
        $stmt->execute([$feedPostId]);
        $row = $stmt->fetch();
        if (!$row) json_response(['message' => 'Şikayet edilecek topluluk gönderisi bulunamadı.'], 404);
        $targetUserId = (int)$row['user_id'];
        $streamId = 0;
        $targetSnapshot = text_substr(trim((string)$row['body']) ?: ('Topluluk gönderisi #' . $feedPostId), 0, 255);
    } elseif ($scope === 'stream') {
        if ($streamId < 1) json_response(['message' => 'Şikayet edilecek yayın seçilmedi.'], 400);
        $stream = get_stream_by_id($streamId);
        if (!$stream) json_response(['message' => 'Şikayet edilecek yayın bulunamadı.'], 404);
        $targetUserId = (int)$stream['user_id'];
        $targetSnapshot = (string)$stream['title'];
    } else {
        // creator/user reports may still carry a stream id, but the user is the target.
        if ($streamId > 0) {
            $stream = get_stream_by_id($streamId);
            if ($stream && $targetUserId < 1) $targetUserId = (int)$stream['user_id'];
        }
    }

    if ($targetUserId < 1) json_response(['message' => 'Şikayet edilecek kullanıcı belirlenemedi.'], 400);
    if ($targetUserId === (int)$reporter['id']) json_response(['message' => 'Kendi hesabını veya içeriğini şikayet edemezsin.'], 400);
    $target = fetch_user_by_id($targetUserId);
    if (!$target) json_response(['message' => 'Şikayet edilecek kullanıcı bulunamadı.'], 404);

    // Bir kullanıcı aynı kanal/kullanıcı için toplam en fazla 3 şikayet oluşturabilir.
    // Stream şikayetleri de kanal sahibine bağlandığı için aynı limit altında sayılır.
    $limitStmt = $pdo->prepare("SELECT COUNT(*) FROM reports WHERE reporter_user_id=? AND target_user_id=? AND report_scope IN ('creator','user','stream')");
    $limitStmt->execute([(int)$reporter['id'], $targetUserId]);
    if ((int)$limitStmt->fetchColumn() >= 3 && in_array($scope, ['creator','user','stream'], true)) {
        json_response(['message' => 'Aynı kanalı en fazla 3 kez şikayet edebilirsin. Önceki şikayetlerin yönetim ekibinde kayıtlı.'], 429);
    }

    $dupSql = 'SELECT id FROM reports WHERE reporter_user_id=? AND target_user_id=? AND category=? AND report_scope=? AND created_at >= (NOW() - INTERVAL 5 MINUTE)';
    $dupArgs = [(int)$reporter['id'], $targetUserId, $category, $scope];
    if ($scope === 'chat_message') { $dupSql .= ' AND chat_message_id=?'; $dupArgs[] = $chatMessageId; }
    if ($scope === 'community_post') { $dupSql .= ' AND feed_post_id=?'; $dupArgs[] = $feedPostId; }
    if ($scope === 'stream') { $dupSql .= ' AND stream_id=?'; $dupArgs[] = $streamId; }
    $dupSql .= ' ORDER BY id DESC LIMIT 1';
    $dup = $pdo->prepare($dupSql); $dup->execute($dupArgs);
    if ($dup->fetch()) json_response(['message' => 'Aynı hedef için kısa süre önce benzer bir şikayet gönderdin.'], 409);

    $stmt = $pdo->prepare('INSERT INTO reports (reporter_user_id,target_user_id,stream_id,chat_message_id,feed_post_id,report_scope,target_username_snapshot,stream_title_snapshot,target_snapshot,category,description) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        (int)$reporter['id'], $targetUserId, $streamId > 0 ? $streamId : null,
        $chatMessageId > 0 ? $chatMessageId : null, $feedPostId > 0 ? $feedPostId : null,
        $scope, (string)$target['username'], $stream ? (string)$stream['title'] : null,
        $targetSnapshot, $category, text_substr($description, 0, 500)
    ]);

    $scopeLabels = ['creator'=>'Yayıncı','user'=>'Kullanıcı','stream'=>'Yayın','chat_message'=>'Chat mesajı','community_post'=>'Topluluk gönderisi'];
    json_response([
        'message' => ($scopeLabels[$scope] ?? 'İçerik') . ' şikayetin kaydedildi ve inceleme sırasına alındı.',
        'reportId' => (int)$pdo->lastInsertId(),
        'targetUserId' => $targetUserId,
        'targetUsername' => (string)$target['username'],
        'scope' => $scope
    ], 201);
} catch (PDOException $e) {
    json_response(['message' => 'Şikayet kaydedilemedi. Veritabanı şemasını install.php ile güncelle.'], 500);
} catch (Throwable $e) {
    json_response(['message' => 'Şikayet kaydedilemedi.'], 500);
}
