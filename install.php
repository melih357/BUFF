<?php
declare(strict_types=1);

@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
header('Content-Type: text/html; charset=utf-8');

function loadEnvSimple(string $path): array {
    if (!is_file($path)) throw new RuntimeException('.env dosyası bulunamadı.');
    $env = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim(trim($v), "\"'");
    }
    return $env;
}

function splitSql(string $sql): array {
    $out = [];
    $buf = '';
    $len = strlen($sql);
    $quote = null;
    $lineComment = false;
    $blockComment = false;
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $next = $i + 1 < $len ? $sql[$i + 1] : '';

        if ($lineComment) {
            if ($ch === "\n") { $lineComment = false; $buf .= $ch; }
            continue;
        }
        if ($blockComment) {
            if ($ch === '*' && $next === '/') { $blockComment = false; $i++; }
            continue;
        }
        if ($quote === null && $ch === '-' && $next === '-' && ($i + 2 >= $len || ctype_space($sql[$i + 2]))) {
            $lineComment = true; $i++; continue;
        }
        if ($quote === null && $ch === '/' && $next === '*') {
            $blockComment = true; $i++; continue;
        }

        if ($quote !== null) {
            $buf .= $ch;
            if ($ch === '\\' && $i + 1 < $len) { $buf .= $sql[++$i]; continue; }
            if ($ch === $quote) {
                // SQL single-quote escaping by doubled quote.
                if ($quote === "'" && $next === "'") { $buf .= $next; $i++; continue; }
                $quote = null;
            }
            continue;
        }

        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
            $buf .= $ch;
            continue;
        }
        if ($ch === ';') {
            $stmt = trim($buf);
            if ($stmt !== '') $out[] = $stmt;
            $buf = '';
            continue;
        }
        $buf .= $ch;
    }
    $stmt = trim($buf);
    if ($stmt !== '') $out[] = $stmt;
    return $out;
}

function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

$env = loadEnvSimple(__DIR__ . '/.env');
$host = $env['DB_HOST'] ?? '127.0.0.1';
$port = (int)($env['DB_PORT'] ?? 3306);
$user = $env['DB_USER'] ?? 'root';
$pass = $env['DB_PASSWORD'] ?? '';
$dbName = preg_replace('/[^a-zA-Z0-9_]/', '', $env['DB_DATABASE'] ?? 'buff_v126') ?: 'buff_v126';
$sqlFile = __DIR__ . '/database/BUFF_DATABASE_SETUP.sql';

$requiredTables = [
    'users','streams','stream_viewers','gift_catalog','gift_events','wallet_transactions','signal_messages','follows','chat_messages','notifications',
    'user_features','bank_accounts','stream_payout_preferences','stream_moderators','chat_restrictions','stream_chat_settings','chat_message_meta','reports',
    'gift_settlements','gift_refund_requests','bank_payment_requests','feed_posts','feed_post_likes','user_preferences','payout_requests','support_tickets',
    'username_change_history','feed_post_comments','feed_post_shares','payment_orders','channel_profiles','recordings','clips','categories','user_sessions',
    'saved_posts','admin_audit_logs','site_settings','payment_methods','buff_install_state','admin_roles','admin_role_assignments',
    'cms_titles','cms_assets','cms_seasons','cms_episodes','cms_collections','cms_collection_items','membership_plans','stream_keys','ip_blocks',
    'system_announcements','epg_events','transcode_jobs','drm_policies','shared_sessions','job_queue','media_variants','realtime_events',
    'finance_audit_logs','moderation_queue','user_loyalty','channel_loyalty','predictions','prediction_options','prediction_entries','community_goals',
    'content_interactions','clip_gifts','api_telemetry','mutation_receipts','follow_requests'
];
$requiredColumns = [
    ['users','is_private'],['users','replay_visibility'],['recordings','playback_key'],['clips','playback_key'],['feed_posts','playback_key'],['gift_events','recording_id'],['users','account_mode'],['users','creator_enabled'],['users','creator_earnings'],['users','is_admin'],['users','account_status'],['users','phone'],['users','billing_address'],
    ['streams','delay_seconds'],['streams','adult_only'],['streams','mature_language'],['streams','ended_at'],
    ['reports','report_scope'],['reports','chat_message_id'],['reports','feed_post_id'],
    ['user_preferences','theme_mode'],['user_preferences','accent_theme'],['user_features','vip_tier'],
    ['feed_posts','video_url'],['feed_posts','poll_json'],['feed_posts','is_pinned'],
    ['payment_orders','order_type'],['payment_orders','order_meta_json'],
    ['recordings','hls_url'],['recordings','dash_url'],['recordings','processing_status'],['recordings','storage_key'],['recordings','storage_provider'],
    ['clips','hls_url'],['clips','dash_url'],['clips','processing_status'],['clips','storage_key'],['clips','storage_provider'],
    ['feed_posts','visibility'],['feed_posts','membership_tier_required'],['feed_posts','clip_id'],['notifications','metadata_json']
];

try {
    if (!is_file($sqlFile)) throw new RuntimeException('Tek SQL kurulum dosyası bulunamadı: database/BUFF_DATABASE_SETUP.sql');
    $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $sql = file_get_contents($sqlFile);
    if ($sql === false) throw new RuntimeException('SQL dosyası okunamadı.');
    $sql = str_replace('`buff_v126`', '`' . $dbName . '`', $sql);

    $done = 0;
    foreach (splitSql($sql) as $statement) {
        try {
            $migration=$pdo->query($statement);while($migration->nextRowset()){}$migration->closeCursor();
            $done++;
        } catch (PDOException $e) {
            $errno = (int)($e->errorInfo[1] ?? 0);
            // Re-run sırasında güvenle atlanabilecek nesne zaten var hataları.
            if (in_array($errno, [1007,1050,1060,1061,1068,1091,1826], true)) continue;
            throw new RuntimeException("SQL kurulumu {$done}. adımdan sonra durdu: " . $e->getMessage(), 0, $e);
        }
    }

    $pdo->exec("USE `{$dbName}`");

    $missingTables = [];
    foreach ($requiredTables as $table) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
        $st->execute([$dbName, $table]);
        if ((int)$st->fetchColumn() === 0) $missingTables[] = $table;
    }
    $missingColumns = [];
    foreach ($requiredColumns as [$table,$column]) {
        $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');
        $st->execute([$dbName, $table, $column]);
        if ((int)$st->fetchColumn() === 0) $missingColumns[] = $table . '.' . $column;
    }
    if ($missingTables || $missingColumns) {
        throw new RuntimeException('Kurulum bitti ancak şema eksik. Tablolar: ' . (implode(', ', $missingTables) ?: '-') . ' | Alanlar: ' . (implode(', ', $missingColumns) ?: '-'));
    }

    $adminUsername = trim((string)($env['ADMIN_USERNAME'] ?? 'admin'));
    $adminEmail = strtolower(trim((string)($env['ADMIN_EMAIL'] ?? 'admin@buff.local')));
    $adminPassword = (string)($env['ADMIN_PASSWORD'] ?? 'change-this-password');
    if ($adminUsername !== '' && $adminEmail !== '' && $adminPassword !== '') {
        $st = $pdo->prepare('SELECT id FROM users WHERE username=? OR email=? LIMIT 1');
        $st->execute([$adminUsername,$adminEmail]);
        $adminId = $st->fetchColumn();
        if ($adminId) {
            // Yerel kurulumda admin kimliğini .env ile senkron tut.
            $pdo->prepare("UPDATE users SET username=?, email=?, password_hash=?, display_name='BUFF Admin', is_admin=1, account_status='active' WHERE id=?")
                ->execute([$adminUsername,$adminEmail,password_hash($adminPassword,PASSWORD_DEFAULT),(int)$adminId]);
        } else {
            $pdo->prepare("INSERT INTO users (username,email,password_hash,display_name,is_admin,account_status) VALUES (?,?,?,?,1,'active')")
                ->execute([$adminUsername,$adminEmail,password_hash($adminPassword,PASSWORD_DEFAULT),'BUFF Admin']);
            $adminId = (int)$pdo->lastInsertId();
        }
        if ($adminId) {
            $roleId = $pdo->query("SELECT id FROM admin_roles WHERE code='super_admin' LIMIT 1")->fetchColumn();
            if ($roleId) $pdo->prepare('INSERT IGNORE INTO admin_role_assignments (user_id,role_id) VALUES (?,?)')->execute([(int)$adminId,(int)$roleId]);
        }
    }

    echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>BUFF V17 Kurulum</title><style>body{margin:0;background:#090b0e;color:#f7f7f8;font:15px system-ui;padding:40px}.box{max-width:820px;margin:auto;background:#12151a;border:1px solid #2a2f37;border-radius:14px;padding:28px}.ok{display:inline-block;background:#ff542e;color:#fff;padding:7px 10px;border-radius:6px;font-weight:800}code{color:#ff936f}a{display:inline-block;margin-top:18px;background:#ff542e;color:#fff;text-decoration:none;padding:12px 16px;border-radius:7px;font-weight:800}</style></head><body><div class="box"><span class="ok">KURULUM BAŞARILI</span><h1>BUFF V17 veritabanı hazır</h1><p>Tek kurulum şeması, V14 gerçek zamanlı/medya/queue tabloları ve admin şeması hazır. Veritabanı: <code>' . esc($dbName) . '</code>.</p><p>PHP + Node ortak oturum, medya işleme, queue, moderasyon, sadakat, tahmin, hedef, CMS ve admin tabloları doğrulandı.</p><a href="./frontend/index.php">BUFF\'u aç</a></div></body></html>';
} catch (Throwable $e) {
    http_response_code(500);
    echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><title>BUFF Kurulum Hatası</title></head><body style="background:#090b0e;color:#fff;font:15px system-ui;padding:40px"><h1>Kurulum hatası</h1><pre style="white-space:pre-wrap;background:#15181d;border:1px solid #353b45;padding:18px;border-radius:10px">' . esc($e->getMessage()) . '</pre></body></html>';
}
