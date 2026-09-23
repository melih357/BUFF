<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();

try {
    $user = require_auth();
    ensure_creator_stream_schema();
    $pdo = db();

    if (!db_table_exists('streams')) {
        json_response(['message' => 'Yayın kaydı bulunamadı.', 'redirect' => './index.php']);
    }

    $sets = [];
    if (db_column_exists('streams', 'is_live')) $sets[] = 'is_live = 0';
    if (db_column_exists('streams', 'ended_at')) $sets[] = 'ended_at = NOW()';
    if (db_column_exists('streams', 'updated_at')) $sets[] = 'updated_at = NOW()';
    if ($sets) {
        $stmt = $pdo->prepare('UPDATE streams SET ' . implode(', ', $sets) . ' WHERE user_id = ?');
        $stmt->execute([(int)$user['id']]);
    }

    $stmt = $pdo->prepare('SELECT id FROM streams WHERE user_id = ? LIMIT 1');
    $stmt->execute([(int)$user['id']]);
    $stream = $stmt->fetch();
    if ($stream) {
        try {
            v14_publish('stream.ended', [
                'streamId' => (int)$stream['id'],
                'creatorUserId' => (int)$user['id'],
            ], null, (int)$stream['id'], 'stream.' . (int)$stream['id']);
        } catch (Throwable $e) {}
        foreach (['signal_messages', 'stream_viewers'] as $table) {
            try {
                if (db_table_exists($table) && db_column_exists($table, 'stream_id')) {
                    $pdo->prepare("DELETE FROM `{$table}` WHERE stream_id = ?")->execute([(int)$stream['id']]);
                }
            } catch (Throwable $e) {}
        }
    }
    json_response(['message' => 'Yayın kapatıldı.', 'redirect' => './index.php']);
} catch (Throwable $e) {
    @file_put_contents(dirname(__DIR__, 3) . '/stream-errors.log', '[' . date('c') . '] STOP ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    $env = app_env();
    $debug = in_array(strtolower((string)($env['APP_DEBUG'] ?? '0')), ['1','true','yes'], true);
    json_response(['message' => 'Yayın kapatılamadı.' . ($debug ? ' Teknik ayrıntı: ' . $e->getMessage() : '')], 500);
}
