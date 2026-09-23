<?php
declare(strict_types=1);

/**
 * BUFF V14 shared infrastructure layer.
 * This file intentionally has no side effects beyond function definitions.
 * `db()` calls ensure_v14_schema() after the legacy schema bootstrap completes.
 */

function v14_env_bool(string $key, bool $default = false): bool {
    $env = app_env();
    if (!array_key_exists($key, $env)) return $default;
    return in_array(strtolower(trim((string)$env[$key])), ['1','true','yes','on'], true);
}

function v14_base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function v14_base64url_decode(string $data): string|false {
    $pad = strlen($data) % 4;
    if ($pad) $data .= str_repeat('=', 4 - $pad);
    return base64_decode(strtr($data, '-_', '+/'), true);
}

function v14_safe_exec(PDO $pdo, string $sql): void {
    try { $pdo->exec($sql); }
    catch (Throwable $e) { error_log('[BUFF V14 schema] ' . $e->getMessage()); }
}

function v14_column_exists(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $stmt->execute([$table,$column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) { return false; }
}

function v14_add_column(PDO $pdo, string $table, string $column, string $definition): void {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table) || !preg_match('/^[A-Za-z0-9_]+$/', $column)) return;
    if (!v14_column_exists($pdo, $table, $column)) {
        v14_safe_exec($pdo, "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
    }
}

function ensure_v14_schema(PDO $pdo): void {
    static $done = false;
    if ($done) return;

    v14_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `shared_sessions` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `user_id` BIGINT UNSIGNED NOT NULL,
      `jti` VARCHAR(80) NOT NULL,
      `token_hash` CHAR(64) NOT NULL,
      `expires_at` DATETIME NOT NULL,
      `revoked_at` DATETIME DEFAULT NULL,
      `ip_address` VARCHAR(64) DEFAULT NULL,
      `user_agent` VARCHAR(255) DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_shared_sessions_jti` (`jti`),
      KEY `idx_shared_sessions_user` (`user_id`,`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    v14_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `job_queue` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `queue_name` VARCHAR(50) NOT NULL DEFAULT 'default',
      `job_type` VARCHAR(80) NOT NULL,
      `payload_json` LONGTEXT NOT NULL,
      `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
      `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
      `available_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `locked_at` DATETIME DEFAULT NULL,
      `locked_by` VARCHAR(100) DEFAULT NULL,
      `last_error` TEXT DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_job_queue_pick` (`status`,`available_at`,`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    v14_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `media_variants` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `owner_type` VARCHAR(30) NOT NULL,
      `owner_id` BIGINT UNSIGNED NOT NULL,
      `quality` VARCHAR(20) NOT NULL,
      `protocol` VARCHAR(12) NOT NULL,
      `file_url` VARCHAR(1000) NOT NULL,
      `storage_key` VARCHAR(500) DEFAULT NULL,
      `bitrate_kbps` INT UNSIGNED DEFAULT NULL,
      `width` INT UNSIGNED DEFAULT NULL,
      `height` INT UNSIGNED DEFAULT NULL,
      `status` VARCHAR(20) NOT NULL DEFAULT 'ready',
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_media_variant` (`owner_type`,`owner_id`,`quality`,`protocol`),
      KEY `idx_media_owner` (`owner_type`,`owner_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    v14_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `realtime_events` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `user_id` BIGINT UNSIGNED DEFAULT NULL,
      `stream_id` BIGINT UNSIGNED DEFAULT NULL,
      `topic` VARCHAR(100) NOT NULL,
      `event_type` VARCHAR(80) NOT NULL,
      `payload_json` LONGTEXT DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_realtime_user` (`user_id`,`id`),
      KEY `idx_realtime_stream` (`stream_id`,`id`),
      KEY `idx_realtime_topic` (`topic`,`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    v14_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `finance_audit_logs` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `event_type` VARCHAR(80) NOT NULL,
      `ref_type` VARCHAR(50) DEFAULT NULL,
      `ref_id` BIGINT UNSIGNED DEFAULT NULL,
      `user_id` BIGINT UNSIGNED DEFAULT NULL,
      `payload_ciphertext` LONGTEXT NOT NULL,
      `payload_iv` VARCHAR(64) NOT NULL,
      `payload_tag` VARCHAR(64) NOT NULL,
      `prev_hash` CHAR(64) DEFAULT NULL,
      `row_hash` CHAR(64) NOT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_finance_ref` (`ref_type`,`ref_id`),
      KEY `idx_finance_user` (`user_id`,`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    v14_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `moderation_queue` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `entity_type` VARCHAR(40) NOT NULL,
      `entity_id` BIGINT UNSIGNED DEFAULT NULL,
      `user_id` BIGINT UNSIGNED DEFAULT NULL,
      `provider` VARCHAR(30) NOT NULL DEFAULT 'builtin',
      `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
      `risk_score` DECIMAL(6,4) NOT NULL DEFAULT 0,
      `labels_json` LONGTEXT DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `reviewed_at` DATETIME DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_moderation_status` (`status`,`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    v14_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `user_loyalty` (
      `user_id` BIGINT UNSIGNED NOT NULL,
      `points` BIGINT UNSIGNED NOT NULL DEFAULT 0,
      `xp` BIGINT UNSIGNED NOT NULL DEFAULT 0,
      `level` INT UNSIGNED NOT NULL DEFAULT 1,
      `daily_grant_date` DATE DEFAULT NULL,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    v14_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `channel_loyalty` (
      `user_id` BIGINT UNSIGNED NOT NULL,
      `channel_user_id` BIGINT UNSIGNED NOT NULL,
      `watch_seconds` BIGINT UNSIGNED NOT NULL DEFAULT 0,
      `message_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
      `gift_amount` BIGINT UNSIGNED NOT NULL DEFAULT 0,
      `clip_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
      `xp` BIGINT UNSIGNED NOT NULL DEFAULT 0,
      `level` INT UNSIGNED NOT NULL DEFAULT 1,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`user_id`,`channel_user_id`),
      KEY `idx_channel_loyalty_rank` (`channel_user_id`,`level`,`xp`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    v14_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `stream_metric_samples` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `stream_id` BIGINT UNSIGNED NOT NULL,
      `creator_user_id` BIGINT UNSIGNED NOT NULL,
      `viewer_count` INT UNSIGNED NOT NULL DEFAULT 0,
      `chatter_count` INT UNSIGNED NOT NULL DEFAULT 0,
      `sampled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_stream_metric_creator` (`creator_user_id`,`sampled_at`),
      KEY `idx_stream_metric_stream` (`stream_id`,`sampled_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    v14_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `predictions` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `stream_id` BIGINT UNSIGNED NOT NULL,
      `creator_user_id` BIGINT UNSIGNED NOT NULL,
      `question` VARCHAR(180) NOT NULL,
      `status` VARCHAR(20) NOT NULL DEFAULT 'open',
      `winning_option_id` BIGINT UNSIGNED DEFAULT NULL,
      `closes_at` DATETIME DEFAULT NULL,
      `resolved_at` DATETIME DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_prediction_stream` (`stream_id`,`status`,`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    v14_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `prediction_options` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `prediction_id` BIGINT UNSIGNED NOT NULL,
      `label` VARCHAR(100) NOT NULL,
      `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
      PRIMARY KEY (`id`),
      KEY `idx_prediction_options` (`prediction_id`,`sort_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    v14_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `prediction_entries` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `prediction_id` BIGINT UNSIGNED NOT NULL,
      `option_id` BIGINT UNSIGNED NOT NULL,
      `user_id` BIGINT UNSIGNED NOT NULL,
      `points` BIGINT UNSIGNED NOT NULL,
      `payout_points` BIGINT UNSIGNED NOT NULL DEFAULT 0,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_prediction_user` (`prediction_id`,`user_id`),
      KEY `idx_prediction_entries_option` (`option_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    v14_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `channel_schedule` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `user_id` BIGINT UNSIGNED NOT NULL,
      `weekday` TINYINT UNSIGNED NOT NULL,
      `start_time` TIME NOT NULL,
      `end_time` TIME DEFAULT NULL,
      `title` VARCHAR(120) NOT NULL DEFAULT '',
      `timezone` VARCHAR(60) NOT NULL DEFAULT 'Europe/Istanbul',
      `enabled` TINYINT(1) NOT NULL DEFAULT 1,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_channel_schedule_user` (`user_id`,`weekday`,`start_time`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    v14_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `community_goals` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `stream_id` BIGINT UNSIGNED NOT NULL,
      `creator_user_id` BIGINT UNSIGNED NOT NULL,
      `title` VARCHAR(160) NOT NULL,
      `target_amount` BIGINT UNSIGNED NOT NULL,
      `current_amount` BIGINT UNSIGNED NOT NULL DEFAULT 0,
      `status` VARCHAR(20) NOT NULL DEFAULT 'active',
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `completed_at` DATETIME DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_goal_stream` (`stream_id`,`status`,`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    v14_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `content_interactions` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `user_id` BIGINT UNSIGNED NOT NULL,
      `channel_user_id` BIGINT UNSIGNED DEFAULT NULL,
      `content_type` VARCHAR(30) NOT NULL,
      `content_id` BIGINT UNSIGNED DEFAULT NULL,
      `interaction_type` VARCHAR(30) NOT NULL,
      `weight` DECIMAL(8,3) NOT NULL DEFAULT 1,
      `duration_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_interactions_user` (`user_id`,`created_at`),
      KEY `idx_interactions_channel` (`channel_user_id`,`interaction_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    v14_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `clip_gifts` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `clip_id` BIGINT UNSIGNED NOT NULL,
      `sender_user_id` BIGINT UNSIGNED NOT NULL,
      `streamer_user_id` BIGINT UNSIGNED NOT NULL,
      `clipper_user_id` BIGINT UNSIGNED NOT NULL,
      `amount` BIGINT UNSIGNED NOT NULL,
      `streamer_share` BIGINT UNSIGNED NOT NULL,
      `clipper_share` BIGINT UNSIGNED NOT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_clip_gifts_clip` (`clip_id`,`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    v14_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `api_telemetry` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `route` VARCHAR(190) NOT NULL,
      `method` VARCHAR(10) NOT NULL,
      `status_code` SMALLINT UNSIGNED NOT NULL,
      `duration_ms` DECIMAL(12,3) NOT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_telemetry_route` (`route`,`created_at`),
      KEY `idx_telemetry_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Existing media/content tables gain V14 metadata without destructive migrations.
    foreach ([
      ['recordings','hls_url','VARCHAR(1000) DEFAULT NULL'],
      ['recordings','dash_url','VARCHAR(1000) DEFAULT NULL'],
      ['recordings','processing_status',"VARCHAR(20) NOT NULL DEFAULT 'ready'"],
      ['recordings','storage_key','VARCHAR(500) DEFAULT NULL'],
      ['recordings','storage_provider',"VARCHAR(30) NOT NULL DEFAULT 'local'"],
      ['clips','hls_url','VARCHAR(1000) DEFAULT NULL'],
      ['clips','dash_url','VARCHAR(1000) DEFAULT NULL'],
      ['clips','processing_status',"VARCHAR(20) NOT NULL DEFAULT 'ready'"],
      ['clips','storage_key','VARCHAR(500) DEFAULT NULL'],
      ['clips','storage_provider',"VARCHAR(30) NOT NULL DEFAULT 'local'"],
      ['feed_posts','visibility',"VARCHAR(30) NOT NULL DEFAULT 'public'"],
      ['feed_posts','membership_tier_required','VARCHAR(20) DEFAULT NULL'],
      ['feed_posts','clip_id','BIGINT UNSIGNED DEFAULT NULL'],
      ['notifications','metadata_json','LONGTEXT DEFAULT NULL'],
    ] as [$table,$column,$definition]) {
        v14_add_column($pdo,$table,$column,$definition);
    }

    v14_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `buff_install_state` (`id` TINYINT UNSIGNED NOT NULL PRIMARY KEY,`version` VARCHAR(20) NOT NULL,`installed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    try {
        $stmt = $pdo->prepare("INSERT INTO buff_install_state (id,version) VALUES (1,'14.4.0') ON DUPLICATE KEY UPDATE version=VALUES(version),updated_at=CURRENT_TIMESTAMP");
        $stmt->execute();
    } catch (Throwable $e) { error_log('[BUFF V14 install state] '.$e->getMessage()); }
    $done = true;
}

function v14_redis(): ?Redis {
    static $client = false;
    if ($client instanceof Redis) return $client;
    if ($client === null) return null;
    if (!class_exists('Redis') || !v14_env_bool('REDIS_ENABLED', false)) { $client = null; return null; }
    $env = app_env();
    try {
        $redis = new Redis();
        $redis->connect((string)($env['REDIS_HOST'] ?? '127.0.0.1'), (int)($env['REDIS_PORT'] ?? 6379), 1.0);
        $password = (string)($env['REDIS_PASSWORD'] ?? '');
        if ($password !== '') $redis->auth($password);
        $redis->select((int)($env['REDIS_DB'] ?? 0));
        $redis->setOption(Redis::OPT_PREFIX, (string)($env['REDIS_PREFIX'] ?? 'buff:'));
        $client = $redis;
        return $redis;
    } catch (Throwable $e) {
        error_log('[BUFF Redis] '.$e->getMessage());
        $client = null;
        return null;
    }
}

function v14_jwt_secret(): string {
    $env = app_env();
    $secret = trim((string)($env['JWT_SECRET'] ?? ''));
    if ($secret === '') $secret = (string)($env['APP_ENCRYPTION_KEY'] ?? 'BUFF-local-development-key');
    return $secret;
}

function v14_jwt_encode(array $payload): string {
    $header = ['alg'=>'HS256','typ'=>'JWT'];
    $parts = [
        v14_base64url_encode(json_encode($header, JSON_UNESCAPED_SLASHES)),
        v14_base64url_encode(json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)),
    ];
    $sig = hash_hmac('sha256', implode('.', $parts), v14_jwt_secret(), true);
    $parts[] = v14_base64url_encode($sig);
    return implode('.', $parts);
}

function v14_jwt_decode(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$h,$p,$s] = $parts;
    $expected = v14_base64url_encode(hash_hmac('sha256', $h.'.'.$p, v14_jwt_secret(), true));
    if (!hash_equals($expected,$s)) return null;
    $json = v14_base64url_decode($p);
    if ($json === false) return null;
    $payload = json_decode($json,true);
    if (!is_array($payload)) return null;
    if ((int)($payload['exp'] ?? 0) < time()) return null;
    if (($payload['iss'] ?? '') !== 'buff-v14') return null;
    return $payload;
}

function v14_request_token(): ?string {
    $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/^Bearer\s+(.+)$/i',$auth,$m)) return trim($m[1]);
    $cookie = trim((string)($_COOKIE['buff_token'] ?? ''));
    return $cookie !== '' ? $cookie : null;
}

function v14_create_shared_session(int $userId, bool $admin = false): array {
    ensure_v14_schema(db());
    $env = app_env();
    $ttl = max(900, (int)($env['JWT_TTL_SECONDS'] ?? 604800));
    $now = time();
    $jti = bin2hex(random_bytes(20));
    $payload = ['iss'=>'buff-v14','sub'=>$userId,'jti'=>$jti,'iat'=>$now,'exp'=>$now+$ttl,'adm'=>$admin ? 1 : 0];
    $token = v14_jwt_encode($payload);
    $hash = hash('sha256',$token);
    $expires = date('Y-m-d H:i:s',$now+$ttl);
    $stmt = db()->prepare('INSERT INTO shared_sessions (user_id,jti,token_hash,expires_at,ip_address,user_agent) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$userId,$jti,$hash,$expires,text_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''),0,64),text_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,255)]);
    if ($r = v14_redis()) {
        try { $r->setex('session:'.$jti,$ttl,json_encode(['userId'=>$userId,'admin'=>$admin,'tokenHash'=>$hash],JSON_UNESCAPED_SLASHES)); } catch (Throwable $e) {}
    }
    $secure = v14_env_bool('COOKIE_SECURE', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'));
    setcookie('buff_token',$token,[
        'expires'=>$now+$ttl,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax'
    ]);
    return ['token'=>$token,'expiresAt'=>$expires,'jti'=>$jti];
}

function v14_revoke_token(?string $token): void {
    if (!$token) return;
    $payload = v14_jwt_decode($token);
    if (!$payload) return;
    $jti = (string)($payload['jti'] ?? '');
    if ($jti === '') return;
    try { db()->prepare('UPDATE shared_sessions SET revoked_at=NOW() WHERE jti=?')->execute([$jti]); } catch (Throwable $e) {}
    if ($r = v14_redis()) { try { $r->del('session:'.$jti); } catch (Throwable $e) {} }
}

function v14_token_user(): ?array {
    $token = v14_request_token();
    if (!$token) return null;
    $payload = v14_jwt_decode($token);
    if (!$payload) return null;
    $jti = (string)($payload['jti'] ?? '');
    $uid = (int)($payload['sub'] ?? 0);
    if ($jti === '' || $uid < 1) return null;
    $valid = false;
    if ($r = v14_redis()) {
        try {
            $raw = $r->get('session:'.$jti);
            if ($raw) {
                $cached = json_decode((string)$raw,true);
                $valid = is_array($cached) && (int)($cached['userId'] ?? 0) === $uid && hash_equals((string)($cached['tokenHash'] ?? ''),hash('sha256',$token));
            }
        } catch (Throwable $e) {}
    }
    if (!$valid) {
        try {
            $stmt = db()->prepare('SELECT user_id,token_hash FROM shared_sessions WHERE jti=? AND revoked_at IS NULL AND expires_at>NOW() LIMIT 1');
            $stmt->execute([$jti]); $row=$stmt->fetch();
            $valid = $row && (int)$row['user_id']===$uid && hash_equals((string)$row['token_hash'],hash('sha256',$token));
        } catch (Throwable $e) { $valid=false; }
    }
    return $valid ? fetch_user_by_id($uid) : null;
}

function v14_storage_config(): array {
    $e=app_env();
    return [
      'provider'=>strtolower((string)($e['STORAGE_PROVIDER'] ?? 'local')),
      'endpoint'=>rtrim((string)($e['OBJECT_STORAGE_ENDPOINT'] ?? ''),'/'),
      'bucket'=>(string)($e['OBJECT_STORAGE_BUCKET'] ?? ''),
      'region'=>(string)($e['OBJECT_STORAGE_REGION'] ?? 'auto'),
      'access'=>(string)($e['OBJECT_STORAGE_ACCESS_KEY'] ?? ''),
      'secret'=>(string)($e['OBJECT_STORAGE_SECRET_KEY'] ?? ''),
      'public'=>rtrim((string)($e['OBJECT_STORAGE_PUBLIC_BASE_URL'] ?? ''),'/'),
      'pathStyle'=>v14_env_bool('OBJECT_STORAGE_PATH_STYLE', true),
    ];
}

function v14_s3_sign_headers(string $method,string $url,string $key,string $contentHash,string $contentType,array $cfg): array {
    $host=(string)parse_url($url,PHP_URL_HOST);
    $path=(string)(parse_url($url,PHP_URL_PATH) ?: '/');
    $amzDate=gmdate('Ymd\THis\Z'); $date=gmdate('Ymd');
    $canonicalHeaders='content-type:'.$contentType."\n".'host:'.$host."\n".'x-amz-content-sha256:'.$contentHash."\n".'x-amz-date:'.$amzDate."\n";
    $signedHeaders='content-type;host;x-amz-content-sha256;x-amz-date';
    $canonicalRequest=$method."\n".$path."\n\n".$canonicalHeaders."\n".$signedHeaders."\n".$contentHash;
    $scope=$date.'/'.$cfg['region'].'/s3/aws4_request';
    $stringToSign='AWS4-HMAC-SHA256'."\n".$amzDate."\n".$scope."\n".hash('sha256',$canonicalRequest);
    $kDate=hash_hmac('sha256',$date,'AWS4'.$cfg['secret'],true);
    $kRegion=hash_hmac('sha256',$cfg['region'],$kDate,true);
    $kService=hash_hmac('sha256','s3',$kRegion,true);
    $kSigning=hash_hmac('sha256','aws4_request',$kService,true);
    $signature=hash_hmac('sha256',$stringToSign,$kSigning);
    $auth='AWS4-HMAC-SHA256 Credential='.$cfg['access'].'/'.$scope.', SignedHeaders='.$signedHeaders.', Signature='.$signature;
    return ['Content-Type: '.$contentType,'Host: '.$host,'x-amz-content-sha256: '.$contentHash,'x-amz-date: '.$amzDate,'Authorization: '.$auth];
}

function v14_storage_url(string $key,array $cfg): string {
    if ($cfg['public']!=='') return $cfg['public'].'/'.ltrim($key,'/');
    if ($cfg['provider']==='local') return '../uploads/'.ltrim($key,'/');
    return $cfg['endpoint'].'/'.$cfg['bucket'].'/'.ltrim($key,'/');
}

function v14_storage_put_path(string $sourcePath,string $folder,string $filename,string $contentType='application/octet-stream',bool $move=false): array {
    $cfg=v14_storage_config();
    $key=trim($folder,'/').'/'.ltrim($filename,'/');
    if ($cfg['provider']==='local' || $cfg['endpoint']==='' || $cfg['bucket']==='' || $cfg['access']==='' || $cfg['secret']==='') {
        $dir=ensure_upload_dir(trim($folder,'/')); $target=$dir.'/'.$filename;
        if (realpath($sourcePath)!==realpath($target)) {
            $ok=$move ? @rename($sourcePath,$target) : @copy($sourcePath,$target);
            if (!$ok) throw new RuntimeException('Medya yerel depolamaya yazılamadı.');
        }
        return ['provider'=>'local','key'=>$key,'path'=>'uploads/'.$key,'url'=>public_upload_url(trim($folder,'/'),$filename)];
    }
    if (!extension_loaded('curl')) throw new RuntimeException('Object storage için PHP cURL eklentisi gerekli.');
    $encoded=implode('/',array_map('rawurlencode',explode('/',$key)));
    $url=$cfg['endpoint'].'/'.$cfg['bucket'].'/'.$encoded;
    $hash=hash_file('sha256',$sourcePath); $headers=v14_s3_sign_headers('PUT',$url,$key,$hash,$contentType,$cfg);
    $fh=fopen($sourcePath,'rb'); if(!$fh) throw new RuntimeException('Medya dosyası okunamadı.');
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_UPLOAD=>true,CURLOPT_INFILE=>$fh,CURLOPT_INFILESIZE=>filesize($sourcePath),CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>180]);
    $resp=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); $err=curl_error($ch); curl_close($ch); fclose($fh);
    if ($code<200 || $code>=300) throw new RuntimeException('Object storage yükleme hatası HTTP '.$code.($err!==''?' '.$err:''));
    if ($move) @unlink($sourcePath);
    return ['provider'=>$cfg['provider'],'key'=>$key,'path'=>$key,'url'=>v14_storage_url($key,$cfg)];
}

function v14_store_upload(array $file,string $folder,string $prefix,string $extension,string $contentType): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new RuntimeException('Dosya yüklenemedi.');
    $name=$prefix.safe_upload_name($extension);
    return v14_storage_put_path((string)$file['tmp_name'],$folder,$name,$contentType,true);
}

function v14_enqueue(string $type,array $payload=[],string $queue='default',int $delaySeconds=0): int {
    ensure_v14_schema(db());
    $available=max(0,$delaySeconds);
    $stmt=db()->prepare('INSERT INTO job_queue (queue_name,job_type,payload_json,available_at) VALUES (?,?,?,DATE_ADD(NOW(),INTERVAL ? SECOND))');
    $stmt->execute([$queue,$type,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$available]);
    $id=(int)db()->lastInsertId();
    if ($r=v14_redis()) { try { $r->rPush('queue:'.$queue,(string)$id); } catch(Throwable $e){} }
    return $id;
}

function v14_publish(string $eventType,array $payload=[],?int $userId=null,?int $streamId=null,string $topic='global'): void {
    $eventId = 0;
    try {
        $stmt=db()->prepare('INSERT INTO realtime_events (user_id,stream_id,topic,event_type,payload_json) VALUES (?,?,?,?,?)');
        $stmt->execute([$userId,$streamId,$topic,$eventType,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
        $eventId = (int)db()->lastInsertId();
    } catch(Throwable $e){}
    if (!db()->inTransaction() && ($r=v14_redis())) {
        try { $r->publish('realtime',json_encode(['id'=>$eventId,'event'=>$eventType,'payload'=>$payload,'userId'=>$userId,'streamId'=>$streamId,'topic'=>$topic],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); } catch(Throwable $e){}
    }
}

function v14_moderate_text(string $text): array {
    $normalized=mb_strtolower(trim($text),'UTF-8');
    if ($normalized==='') return ['allow'=>true,'score'=>0.0,'labels'=>[]];
    $labels=[]; $score=0.0;
    $blocked=['çocuk pornosu','tecavüz et','öldür kendini','nazi propagandası'];
    foreach($blocked as $needle){ if(str_contains($normalized,$needle)){ $labels[]='severe'; $score=max($score,0.99); } }
    $profanity=['amk','orospu','piç','siktir','fuck','bitch'];
    $hits=0; foreach($profanity as $w) if(str_contains($normalized,$w)) $hits++;
    if($hits>0){$labels[]='profanity';$score=max($score,min(0.85,0.25+$hits*0.18));}
    if(preg_match('/(.)\1{10,}/u',$normalized) || preg_match('/https?:\/\//i',$normalized) && substr_count($normalized,'http')>=3){$labels[]='spam';$score=max($score,0.8);}
    $allow=$score<0.95;
    if (v14_env_bool('AI_MODERATION_ENABLED',false)) {
        try { v14_enqueue('moderate_text',['text'=>$text,'builtinScore'=>$score,'labels'=>$labels],'moderation'); } catch(Throwable $e){}
    }
    return ['allow'=>$allow,'score'=>$score,'labels'=>array_values(array_unique($labels))];
}

function v14_queue_media_moderation(string $entityType,int $entityId,?int $userId,string $url): void {
    try {
        $stmt=db()->prepare('INSERT INTO moderation_queue (entity_type,entity_id,user_id,provider,status,labels_json) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$entityType,$entityId,$userId,v14_env_bool('AI_MODERATION_ENABLED',false)?'external':'builtin','pending',json_encode(['url'=>$url])]);
        v14_enqueue('moderate_media',['moderationId'=>(int)db()->lastInsertId(),'entityType'=>$entityType,'entityId'=>$entityId,'url'=>$url],'moderation');
    } catch(Throwable $e){}
}

function v14_finance_audit(string $eventType,?string $refType,?int $refId,?int $userId,array $payload): void {
    try {
        $key=hash('sha256',(string)(app_env()['APP_ENCRYPTION_KEY'] ?? 'buff-dev-key'),true);
        $iv=random_bytes(12); $tag='';
        $plain=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $cipher=openssl_encrypt($plain,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'BUFF-V14');
        if($cipher===false) return;
        $prev=(string)(db()->query('SELECT row_hash FROM finance_audit_logs ORDER BY id DESC LIMIT 1')->fetchColumn() ?: '');
        $rowHash=hash('sha256',$prev.'|'.$eventType.'|'.($refType??'').'|'.($refId??0).'|'.($userId??0).'|'.base64_encode($cipher).'|'.base64_encode($iv).'|'.base64_encode($tag));
        $stmt=db()->prepare('INSERT INTO finance_audit_logs (event_type,ref_type,ref_id,user_id,payload_ciphertext,payload_iv,payload_tag,prev_hash,row_hash) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$eventType,$refType,$refId,$userId,base64_encode($cipher),base64_encode($iv),base64_encode($tag),$prev!==''?$prev:null,$rowHash]);
    } catch(Throwable $e){ error_log('[BUFF finance audit] '.$e->getMessage()); }
}

function v14_loyalty_snapshot(int $userId): array {
    ensure_v14_schema(db());
    db()->prepare('INSERT IGNORE INTO user_loyalty (user_id) VALUES (?)')->execute([$userId]);
    $stmt=db()->prepare('SELECT * FROM user_loyalty WHERE user_id=?');$stmt->execute([$userId]);$row=$stmt->fetch()?:[];
    $today=date('Y-m-d'); $grant=(int)(app_env()['DAILY_LOYALTY_POINTS'] ?? 100);
    if(($row['daily_grant_date']??null)!==$today && $grant>0){
        db()->prepare('UPDATE user_loyalty SET points=points+?,xp=xp+?,daily_grant_date=? WHERE user_id=?')->execute([$grant,max(1,(int)floor($grant/10)),$today,$userId]);
        $stmt->execute([$userId]);$row=$stmt->fetch()?:$row;
    }
    return ['points'=>(int)($row['points']??0),'xp'=>(int)($row['xp']??0),'level'=>(int)($row['level']??1),'dailyGrantDate'=>$row['daily_grant_date']??null];
}

function v14_add_loyalty(int $userId,int $points,int $xp=0): array {
    db()->prepare('INSERT IGNORE INTO user_loyalty (user_id) VALUES (?)')->execute([$userId]);
    $pdo=db();$pdo->prepare('UPDATE user_loyalty SET points=GREATEST(points+?,0),xp=GREATEST(xp+?,0) WHERE user_id=?')->execute([$points,$xp,$userId]);
    $stmt=$pdo->prepare('SELECT xp FROM user_loyalty WHERE user_id=?');$stmt->execute([$userId]);$totalXp=(int)$stmt->fetchColumn();
    $level=max(1,(int)floor(sqrt($totalXp/100))+1);$pdo->prepare('UPDATE user_loyalty SET level=? WHERE user_id=?')->execute([$level,$userId]);
    return v14_loyalty_snapshot($userId);
}

function v14_channel_loyalty_add(int $userId,int $channelUserId,array $delta): void {
    if($userId<1||$channelUserId<1||$userId===$channelUserId)return;
    $watch=max(0,(int)($delta['watchSeconds']??0));$messages=max(0,(int)($delta['messages']??0));$gift=max(0,(int)($delta['giftAmount']??0));$clips=max(0,(int)($delta['clips']??0));
    $xp=max(0,(int)($delta['xp']??($messages*2+$clips*15+(int)floor($watch/60)+(int)floor($gift/50))));
    $stmt=db()->prepare('INSERT INTO channel_loyalty (user_id,channel_user_id,watch_seconds,message_count,gift_amount,clip_count,xp) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE watch_seconds=watch_seconds+VALUES(watch_seconds),message_count=message_count+VALUES(message_count),gift_amount=gift_amount+VALUES(gift_amount),clip_count=clip_count+VALUES(clip_count),xp=xp+VALUES(xp)');
    $stmt->execute([$userId,$channelUserId,$watch,$messages,$gift,$clips,$xp]);
    $st=db()->prepare('SELECT xp FROM channel_loyalty WHERE user_id=? AND channel_user_id=?');$st->execute([$userId,$channelUserId]);$total=(int)$st->fetchColumn();$level=max(1,(int)floor(sqrt($total/80))+1);
    db()->prepare('UPDATE channel_loyalty SET level=? WHERE user_id=? AND channel_user_id=?')->execute([$level,$userId,$channelUserId]);
}

function v14_is_paid_member(int $userId,?string $minTier=null): bool {
    try {
        $f=user_features($userId); if(!is_vip_feature($f)) return false;
        if($minTier===null||$minTier==='')return true;
        $rank=['plus'=>1,'pro'=>2,'premium'=>3];
        return ($rank[strtolower((string)($f['vip_tier']??''))]??0)>=($rank[strtolower($minTier)]??1);
    }catch(Throwable $e){return false;}
}

function v14_record_interaction(int $userId,?int $channelUserId,string $contentType,?int $contentId,string $type,float $weight=1.0,int $duration=0): void {
    if($userId<1)return;
    try{
        $stmt=db()->prepare('INSERT INTO content_interactions (user_id,channel_user_id,content_type,content_id,interaction_type,weight,duration_seconds) VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([$userId,$channelUserId,$contentType,$contentId,$type,$weight,max(0,$duration)]);
    }catch(Throwable $e){}
}

function v14_start_timer(): float { return microtime(true); }
function v14_telemetry(string $route,string $method,int $status,float $startedAt): void {
    try{
        $ms=max(0,(microtime(true)-$startedAt)*1000);
        $stmt=db()->prepare('INSERT INTO api_telemetry (route,method,status_code,duration_ms) VALUES (?,?,?,?)');
        $stmt->execute([text_substr($route,0,190),text_substr($method,0,10),$status,$ms]);
    }catch(Throwable $e){}
}
