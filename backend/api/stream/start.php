<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();

try {
    $pdo = db();
    ensure_creator_stream_schema();
    $user = require_auth();

    // Eski şemalarda creator alanları eksik olabilir. Varsa güncelle, yoksa yayını bloke etme.
    try {
        $sets = [];
        if (db_column_exists('users', 'account_mode')) $sets[] = "account_mode='creator'";
        if (db_column_exists('users', 'creator_enabled')) $sets[] = 'creator_enabled=1';
        if ($sets) {
            $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id=?')->execute([(int)$user['id']]);
            $user = fetch_user_by_id((int)$user['id']) ?? $user;
        }
    } catch (Throwable $e) {
        error_log('[BUFF stream creator mode] ' . $e->getMessage());
    }

    $data = request_json();
    $title = trim((string)($data['title'] ?? ''));
    $category = trim((string)($data['category'] ?? ''));
    $description = trim((string)($data['description'] ?? ''));
    $tags = normalize_tags((string)($data['tags'] ?? ''));
    $language = strtoupper(trim((string)($data['language'] ?? 'TR')));

    $prefs = ['default_stream_delay' => 5];
    try { $prefs = user_preferences((int)$user['id']); } catch (Throwable $e) {}
    $delaySeconds = (int)($data['delaySeconds'] ?? $prefs['default_stream_delay'] ?? 5);
    $adultOnly = !empty($data['adultOnly']) ? 1 : 0;
    $matureLanguage = !empty($data['matureLanguage']) ? 1 : 0;

    if ($title === '' || $category === '') {
        json_response(['message' => 'Yayın başlığı ve kategori zorunludur.'], 400);
    }
    if (!in_array($language, ['TR', 'EN', 'DE', 'AR', 'ES'], true)) $language = 'TR';
    if (!in_array($delaySeconds, [0, 3, 5, 10, 15, 30], true)) $delaySeconds = 5;
    if ($matureLanguage && !$adultOnly) {
        json_response(['message' => 'Yoğun küfür / sert dil işaretliyse +18 etiketi zorunludur.'], 400);
    }

    if (!db_table_exists('streams')) {
        throw new RuntimeException('streams tablosu oluşturulamadı. Veritabanı onarımını çalıştır.');
    }

    $streamColumns = [];
    $columnsStmt = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='streams'");
    foreach ($columnsStmt->fetchAll() as $row) $streamColumns[(string)$row['COLUMN_NAME']] = true;

    foreach (['id','user_id','title','category','is_live'] as $required) {
        if (empty($streamColumns[$required])) {
            throw new RuntimeException("streams.{$required} alanı bulunamadı. VERITABANI_ONAR.bat dosyasını çalıştır.");
        }
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT id FROM streams WHERE user_id = ? LIMIT 1');
    $stmt->execute([(int)$user['id']]);
    $existing = $stmt->fetch();

    $valuesByColumn = [
        'title' => $title,
        'category' => $category,
        'description' => text_substr($description, 0, 300),
        'tags' => $tags,
        'language' => $language,
        'delay_seconds' => $delaySeconds,
        'adult_only' => $adultOnly,
        'mature_language' => $matureLanguage,
        'is_live' => 1,
    ];

    if ($existing) {
        $sets = [];
        $params = [];
        foreach ($valuesByColumn as $column => $value) {
            if (!empty($streamColumns[$column])) {
                $sets[] = "`{$column}` = ?";
                $params[] = $value;
            }
        }
        if (!empty($streamColumns['started_at'])) $sets[] = '`started_at` = NOW()';
        if (!empty($streamColumns['ended_at'])) $sets[] = '`ended_at` = NULL';
        if (!empty($streamColumns['updated_at'])) $sets[] = '`updated_at` = NOW()';
        $params[] = (int)$user['id'];
        $stmt = $pdo->prepare('UPDATE streams SET ' . implode(', ', $sets) . ' WHERE user_id = ?');
        $stmt->execute($params);
        $streamId = (int)$existing['id'];
    } else {
        $cols = ['user_id'];
        $placeholders = ['?'];
        $params = [(int)$user['id']];
        foreach ($valuesByColumn as $column => $value) {
            if (!empty($streamColumns[$column])) {
                $cols[] = $column;
                $placeholders[] = '?';
                $params[] = $value;
            }
        }
        if (!empty($streamColumns['started_at'])) {
            $cols[] = 'started_at';
            $placeholders[] = 'NOW()';
        }
        if (!empty($streamColumns['ended_at'])) {
            $cols[] = 'ended_at';
            $placeholders[] = 'NULL';
        }
        $quotedCols = array_map(fn($c) => "`{$c}`", $cols);
        $stmt = $pdo->prepare('INSERT INTO streams (' . implode(',', $quotedCols) . ') VALUES (' . implode(',', $placeholders) . ')');
        $stmt->execute($params);
        $streamId = (int)$pdo->lastInsertId();
    }

    // Yardımcı tablolar eski/yarım kurulmuşsa canlı yayını engellemesin.
    try {
        if (db_table_exists('stream_chat_settings')) {
            $pdo->prepare('INSERT INTO stream_chat_settings (stream_id, slow_mode_seconds) VALUES (?, 1) ON DUPLICATE KEY UPDATE slow_mode_seconds = slow_mode_seconds')->execute([$streamId]);
        }
    } catch (Throwable $e) { error_log('[BUFF stream chat settings] ' . $e->getMessage()); }

    foreach (['signal_messages', 'stream_viewers', 'chat_messages'] as $table) {
        try {
            if (db_table_exists($table) && db_column_exists($table, 'stream_id')) {
                $pdo->prepare("DELETE FROM `{$table}` WHERE stream_id = ?")->execute([$streamId]);
            }
        } catch (Throwable $e) { error_log('[BUFF stream cleanup] ' . $table . ': ' . $e->getMessage()); }
    }

    try {
        if (db_table_exists('chat_restrictions') && db_column_exists('chat_restrictions', 'stream_id') && db_column_exists('chat_restrictions', 'expires_at')) {
            $pdo->prepare('DELETE FROM chat_restrictions WHERE stream_id = ? AND expires_at IS NOT NULL AND expires_at <= NOW()')->execute([$streamId]);
        }
    } catch (Throwable $e) { error_log('[BUFF chat restriction cleanup] ' . $e->getMessage()); }

    $pdo->commit();

    // Bildirim hatası yayın başlatmayı bozmasın.
    try {
        if (db_table_exists('follows') && db_table_exists('notifications')) {
            $followers = $pdo->prepare('SELECT follower_user_id FROM follows WHERE followed_user_id = ?');
            $followers->execute([(int)$user['id']]);
            foreach ($followers->fetchAll() as $follower) {
                try {
                    create_notification((int)$follower['follower_user_id'], (int)$user['id'], 'live', 'Takip ettiğin kanal yayında', (string)$user['display_name'] . ' canlı yayına başladı.', './watch.php?id=' . $streamId);
                } catch (Throwable $e) {}
            }
        }
    } catch (Throwable $e) { error_log('[BUFF follower notify] ' . $e->getMessage()); }

    // Behavior-aware notifications: highly engaged viewers who are not necessarily followers
    // can opt into platform notifications and receive a targeted live alert.
    try {
        $engaged=$pdo->prepare("SELECT ci.user_id,SUM(ci.weight) score FROM content_interactions ci WHERE ci.channel_user_id=? AND ci.created_at>=NOW()-INTERVAL 60 DAY AND ci.user_id<>? GROUP BY ci.user_id HAVING score>=8 ORDER BY score DESC LIMIT 100");
        $engaged->execute([(int)$user['id'],(int)$user['id']]);
        foreach($engaged->fetchAll() as $row){
            $uid=(int)$row['user_id'];
            $follow=$pdo->prepare('SELECT 1 FROM follows WHERE follower_user_id=? AND followed_user_id=? LIMIT 1');$follow->execute([$uid,(int)$user['id']]);if($follow->fetchColumn())continue;
            try{create_notification($uid,(int)$user['id'],'live_recommended','Sık izlediğin kanal yayında',(string)$user['display_name'].' canlı yayına başladı.','./watch.php?id='.$streamId);}catch(Throwable $e){}
        }
    } catch(Throwable $e){ error_log('[BUFF behavior live notify] '.$e->getMessage()); }
    v14_publish('stream.started',['streamId'=>$streamId,'creatorUserId'=>(int)$user['id'],'title'=>$title,'category'=>$category],null,$streamId,'stream.'.$streamId);

    $stream = get_stream_by_id($streamId);
    if (!$stream) {
        throw new RuntimeException('Yayın kaydı oluşturuldu ancak tekrar okunamadı.');
    }

    json_response([
        'message' => 'Yayın başladı.',
        'stream' => map_stream($stream),
        'delaySeconds' => $delaySeconds,
        'payoutNote' => 'Hediye gelirleri Yayıncı Kazancı bakiyesinde birikir; Ayarlar > Ödemeler bölümünden çekim talebi oluşturabilirsin.',
    ]);
} catch (Throwable $e) {
    try {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    } catch (Throwable $_) {}

    $logLine = '[' . date('c') . '] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
    @file_put_contents(dirname(__DIR__, 3) . '/stream-errors.log', $logLine, FILE_APPEND);
    error_log('[BUFF stream/start] ' . $e->getMessage());

    $env = app_env();
    $debug = in_array(strtolower((string)($env['APP_DEBUG'] ?? '0')), ['1','true','yes'], true);
    json_response([
        'message' => 'Yayın başlatılamadı.' . ($debug ? ' Teknik ayrıntı: ' . $e->getMessage() : ''),
        'errorCode' => 'STREAM_START_FAILED'
    ], 500);
}
