<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

// API cevaplarının PHP warning/notice HTML'i ile bozulmasını engelle.
@ini_set('display_errors', '0');
@ini_set('html_errors', '0');
@ini_set('log_errors', '1');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('logi_session');
    session_start();
}

date_default_timezone_set('Europe/Istanbul');

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function load_env_file(string $path): array {
    if (!is_file($path)) {
        throw new RuntimeException('.env dosyası bulunamadı: ' . $path);
    }
    $env = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        throw new RuntimeException('.env dosyası okunamadı.');
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $env[$key] = trim($value, "\"'");
    }
    return $env;
}

function app_env(): array {
    static $env = null;
    if (is_array($env)) {
        return $env;
    }
    $env = load_env_file(dirname(__DIR__) . '/.env');
    return $env;
}

function bootstrap_core_schema(PDO $pdo, string $dbName): void {
    static $done = false;
    if ($done) return;
    $safeDb = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName) ?: 'buff_v126';
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$safeDb}`");

    // V12.6: yarım/boş veritabanında giriş ve Creator Studio'nun 500 vermemesi için
    // minimum çekirdek şemayı uygulama açılırken idempotent şekilde oluştur.
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `username` VARCHAR(24) NOT NULL,
      `email` VARCHAR(190) NOT NULL,
      `phone` VARCHAR(32) DEFAULT NULL,
      `billing_address` VARCHAR(500) DEFAULT NULL,
      `password_hash` VARCHAR(255) NOT NULL,
      `display_name` VARCHAR(60) NOT NULL,
      `avatar_url` VARCHAR(255) DEFAULT NULL,
      `bio` VARCHAR(255) NOT NULL DEFAULT '',
      `account_mode` VARCHAR(20) NOT NULL DEFAULT 'viewer',
      `creator_enabled` TINYINT(1) NOT NULL DEFAULT 0,
      `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
      `account_status` VARCHAR(20) NOT NULL DEFAULT 'active',
      `wallet_balance` BIGINT UNSIGNED NOT NULL DEFAULT 2500,
      `creator_earnings` BIGINT UNSIGNED NOT NULL DEFAULT 0,
      `total_gifts_received` BIGINT UNSIGNED NOT NULL DEFAULT 0,
      `username_changed_at` DATETIME DEFAULT NULL,
      `email_changed_at` DATETIME DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_users_username` (`username`),
      UNIQUE KEY `uq_users_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $userColumns = [
      'phone' => 'VARCHAR(32) DEFAULT NULL',
      'billing_address' => 'VARCHAR(500) DEFAULT NULL',
      'account_mode' => "VARCHAR(20) NOT NULL DEFAULT 'viewer'",
      'creator_enabled' => 'TINYINT(1) NOT NULL DEFAULT 0',
      'is_admin' => 'TINYINT(1) NOT NULL DEFAULT 0',
      'account_status' => "VARCHAR(20) NOT NULL DEFAULT 'active'",
      'wallet_balance' => 'BIGINT UNSIGNED NOT NULL DEFAULT 2500',
      'creator_earnings' => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
      'total_gifts_received' => 'BIGINT UNSIGNED NOT NULL DEFAULT 0',
      'username_changed_at' => 'DATETIME DEFAULT NULL',
      'email_changed_at' => 'DATETIME DEFAULT NULL',
    ];
    foreach ($userColumns as $column => $definition) {
        $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='users' AND COLUMN_NAME=?");
        $q->execute([$safeDb, $column]);
        if ((int)$q->fetchColumn() === 0) {
            try { $pdo->exec("ALTER TABLE `users` ADD COLUMN `{$column}` {$definition}"); }
            catch (Throwable $e) { error_log('[BUFF core users] '.$column.': '.$e->getMessage()); }
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS `user_features` (
      `user_id` BIGINT UNSIGNED NOT NULL,
      `is_og` TINYINT(1) NOT NULL DEFAULT 0,
      `vip_until` DATETIME DEFAULT NULL,
      `vip_tier` VARCHAR(20) DEFAULT NULL,
      `chat_color` VARCHAR(20) NOT NULL DEFAULT '#E7ECFF',
      `preferred_language` VARCHAR(5) NOT NULL DEFAULT 'tr',
      `accent_theme` VARCHAR(24) NOT NULL DEFAULT 'buff',
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `user_preferences` (
      `user_id` BIGINT UNSIGNED NOT NULL,
      `theme_mode` VARCHAR(12) NOT NULL DEFAULT 'dark',
      `accent_theme` VARCHAR(24) NOT NULL DEFAULT 'buff',
      `sidebar_collapsed` TINYINT(1) NOT NULL DEFAULT 0,
      `default_stream_delay` INT UNSIGNED NOT NULL DEFAULT 5,
      `reduce_motion` TINYINT(1) NOT NULL DEFAULT 0,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `streams` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `user_id` BIGINT UNSIGNED NOT NULL,
      `title` VARCHAR(160) NOT NULL DEFAULT '',
      `category` VARCHAR(80) NOT NULL DEFAULT '',
      `description` VARCHAR(300) NOT NULL DEFAULT '',
      `language` VARCHAR(10) NOT NULL DEFAULT 'TR',
      `tags` VARCHAR(255) DEFAULT NULL,
      `is_live` TINYINT(1) NOT NULL DEFAULT 0,
      `gift_total` BIGINT UNSIGNED NOT NULL DEFAULT 0,
      `delay_seconds` INT UNSIGNED NOT NULL DEFAULT 5,
      `adult_only` TINYINT(1) NOT NULL DEFAULT 0,
      `mature_language` TINYINT(1) NOT NULL DEFAULT 0,
      `started_at` DATETIME DEFAULT NULL,
      `ended_at` DATETIME DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_streams_user` (`user_id`),
      KEY `idx_streams_live` (`is_live`,`updated_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $done = true;
}

function buff_split_sql(string $sql): array {
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
                if ($quote === "'" && $next === "'") { $buf .= $next; $i++; continue; }
                $quote = null;
            }
            continue;
        }
        if ($ch === "'" || $ch === '"' || $ch === '`') { $quote = $ch; $buf .= $ch; continue; }
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

function bootstrap_full_schema(PDO $pdo, string $dbName): void {
    static $done = false;
    if ($done) return;

    $safeDb = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName) ?: 'buff_v126';
    $pdo->exec("USE `{$safeDb}`");

    $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME='buff_install_state'");
    $check->execute([$safeDb]);
    $hasState = (int)$check->fetchColumn() > 0;
    if ($hasState) {
        try {
            $version = (string)($pdo->query("SELECT version FROM buff_install_state WHERE id=1 LIMIT 1")->fetchColumn() ?: '');
            // V12.7+ tek SQL kurulum kaydı mevcutsa normal API isteklerinde
            // bütün şemayı tekrar tekrar çalıştırma. Bu özellikle auth 500
            // hatalarının ana sebebiydi.
            if ($version !== '' && version_compare($version, '12.7.0', '>=')) { $done = true; return; }
        } catch (Throwable $e) {}
    }

    $sqlFile = dirname(__DIR__) . '/database/BUFF_DATABASE_SETUP.sql';
    if (!is_file($sqlFile)) throw new RuntimeException('BUFF_DATABASE_SETUP.sql bulunamadı.');
    $sql = file_get_contents($sqlFile);
    if ($sql === false) throw new RuntimeException('BUFF_DATABASE_SETUP.sql okunamadı.');
    $sql = str_replace('`buff_v126`', '`' . $safeDb . '`', $sql);

    foreach (buff_split_sql($sql) as $statement) {
        try {
            $migration=$pdo->query($statement);while($migration->nextRowset()){}$migration->closeCursor();
        } catch (PDOException $e) {
            $errno = (int)($e->errorInfo[1] ?? 0);
            if (in_array($errno, [1007,1050,1060,1061,1068,1091,1826], true)) continue;
            throw $e;
        }
    }
    $pdo->exec("USE `{$safeDb}`");
    $done = true;
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $env = app_env();
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $port = (int)($env['DB_PORT'] ?? 3306);
    $name = preg_replace('/[^a-zA-Z0-9_]/', '', $env['DB_DATABASE'] ?? 'buff_v126') ?: 'buff_v126';
    $user = $env['DB_USER'] ?? 'root';
    $pass = $env['DB_PASSWORD'] ?? '';

    // Veritabanı henüz yoksa dbname içeren DSN doğrudan hata verir. Önce sunucuya bağlanıp oluştur.
    $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    bootstrap_core_schema($pdo, $name);

    // Tam şema kurulumu normal giriş/kayıt API'sini bloke etmemeli.
    // Çekirdek auth tabloları hazır olduktan sonra tam kurulumda eski/yarım
    // bir SQL nesnesi sorun çıkarırsa bunu logla ve isteğin çalışmasına izin ver.
    try {
        bootstrap_full_schema($pdo, $name);
    } catch (Throwable $e) {
        error_log('[BUFF full schema bootstrap] ' . $e->getMessage());
    }
    if (function_exists('ensure_v14_schema')) {
        try { ensure_v14_schema($pdo); } catch (Throwable $e) { error_log('[BUFF V14 schema bootstrap] ' . $e->getMessage()); }
    }
    if (function_exists('ensure_v16_schema')) {
        try { ensure_v16_schema($pdo); } catch (Throwable $e) { error_log('[BUFF V16 schema bootstrap] ' . $e->getMessage()); }
    }
    ensure_v17_schema($pdo);
    return $pdo;
}
function db_table_exists(string $table): bool {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) return false;

    // Aktif XAMPP/MariaDB bağlantısındaki tabloyu doğrudan kontrol et.
    // Bu, Creator Studio'da görülen yanlış "users tablosu bulunamadı"
    // sonucunu information_schema kontrolüne bağımlı olmaktan çıkarır.
    try {
        $stmt = db()->query("SHOW TABLES LIKE " . db()->quote($table));
        if ($stmt && $stmt->fetchColumn() !== false) return true;
    } catch (Throwable $e) {
        error_log('[BUFF table check/show] ' . $e->getMessage());
    }

    try {
        $stmt = db()->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function db_column_exists(string $table, string $column): bool {
    if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table) || !preg_match('/^[a-zA-Z0-9_]+$/D', $column)) return false;
    $stmt = db()->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function ensure_creator_stream_schema(): void {
    static $ready = false;
    if ($ready) return;

    $pdo = db();

    // V12.6: eski / yarım kurulmuş veritabanlarında Creator Studio'nun
    // tamamen çökmesini engelleyen minimum yayın şeması. Foreign key
    // eklemek burada zorunlu değil; amaç yayın başlatma akışını ayağa kaldırmak.
    if (!db_table_exists('users')) {
        // Creator Studio fail-safe: yalnızca eksik users tablosunu oluştur.
        // Başka hiçbir uygulama özelliğini değiştirmez.
        $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `username` VARCHAR(24) NOT NULL,
          `email` VARCHAR(190) NOT NULL,
          `phone` VARCHAR(32) DEFAULT NULL,
          `billing_address` VARCHAR(500) DEFAULT NULL,
          `password_hash` VARCHAR(255) NOT NULL,
          `display_name` VARCHAR(60) NOT NULL,
          `avatar_url` VARCHAR(255) DEFAULT NULL,
          `bio` VARCHAR(255) NOT NULL DEFAULT '',
          `account_mode` VARCHAR(20) NOT NULL DEFAULT 'viewer',
          `creator_enabled` TINYINT(1) NOT NULL DEFAULT 0,
          `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
          `account_status` VARCHAR(20) NOT NULL DEFAULT 'active',
          `wallet_balance` BIGINT UNSIGNED NOT NULL DEFAULT 2500,
          `creator_earnings` BIGINT UNSIGNED NOT NULL DEFAULT 0,
          `total_gifts_received` BIGINT UNSIGNED NOT NULL DEFAULT 0,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_users_username` (`username`),
          UNIQUE KEY `uq_users_email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    if (!db_table_exists('users')) {
        throw new RuntimeException('Aktif veritabanında users tablosu oluşturulamadı.');
    }

    if (!db_table_exists('streams')) {
        $pdo->exec("CREATE TABLE `streams` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `user_id` BIGINT UNSIGNED NOT NULL,
          `title` VARCHAR(160) NOT NULL DEFAULT '',
          `category` VARCHAR(80) NOT NULL DEFAULT '',
          `description` VARCHAR(300) NOT NULL DEFAULT '',
          `language` VARCHAR(10) NOT NULL DEFAULT 'TR',
          `tags` VARCHAR(255) DEFAULT NULL,
          `is_live` TINYINT(1) NOT NULL DEFAULT 0,
          `gift_total` BIGINT UNSIGNED NOT NULL DEFAULT 0,
          `delay_seconds` INT UNSIGNED NOT NULL DEFAULT 5,
          `adult_only` TINYINT(1) NOT NULL DEFAULT 0,
          `mature_language` TINYINT(1) NOT NULL DEFAULT 0,
          `started_at` DATETIME DEFAULT NULL,
          `ended_at` DATETIME DEFAULT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_streams_user` (`user_id`),
          KEY `idx_streams_live` (`is_live`,`updated_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    $columns = [
        ['users', 'account_mode', "VARCHAR(20) NOT NULL DEFAULT 'viewer'"],
        ['users', 'creator_enabled', 'TINYINT(1) NOT NULL DEFAULT 0'],
        ['streams', 'title', "VARCHAR(160) NOT NULL DEFAULT ''"],
        ['streams', 'category', "VARCHAR(80) NOT NULL DEFAULT ''"],
        ['streams', 'description', "VARCHAR(300) NOT NULL DEFAULT ''"],
        ['streams', 'tags', 'VARCHAR(255) DEFAULT NULL'],
        ['streams', 'language', "VARCHAR(10) NOT NULL DEFAULT 'TR'"],
        ['streams', 'delay_seconds', 'INT UNSIGNED NOT NULL DEFAULT 5'],
        ['streams', 'adult_only', 'TINYINT(1) NOT NULL DEFAULT 0'],
        ['streams', 'mature_language', 'TINYINT(1) NOT NULL DEFAULT 0'],
        ['streams', 'is_live', 'TINYINT(1) NOT NULL DEFAULT 0'],
        ['streams', 'gift_total', 'BIGINT UNSIGNED NOT NULL DEFAULT 0'],
        ['streams', 'started_at', 'DATETIME NULL'],
        ['streams', 'ended_at', 'DATETIME NULL'],
        ['streams', 'created_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'],
        ['streams', 'updated_at', 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
    ];

    foreach ($columns as [$table, $column, $definition]) {
        if (db_column_exists($table, $column)) continue;
        try {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        } catch (Throwable $e) {
            // Kullanıcının MySQL/MariaDB sürümü AFTER/FK/legacy şema nedeniyle
            // sorun çıkarırsa daha sonra start.php yalnızca mevcut kolonları kullanır.
            error_log('[BUFF schema repair] ' . $table . '.' . $column . ': ' . $e->getMessage());
        }
    }

    // Bunlar yardımcı tablolardır. Oluşturulamamaları yayını başlatmayı engellememeli.
    try {
        if (!db_table_exists('user_preferences')) {
            $pdo->exec("CREATE TABLE `user_preferences` (
              `user_id` BIGINT UNSIGNED NOT NULL,
              `theme_mode` VARCHAR(12) NOT NULL DEFAULT 'dark',
              `accent_theme` VARCHAR(24) NOT NULL DEFAULT 'buff',
              `sidebar_collapsed` TINYINT(1) NOT NULL DEFAULT 0,
              `default_stream_delay` INT UNSIGNED NOT NULL DEFAULT 5,
              `reduce_motion` TINYINT(1) NOT NULL DEFAULT 0,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    } catch (Throwable $e) {
        error_log('[BUFF schema repair] user_preferences: ' . $e->getMessage());
    }

    try {
        if (!db_table_exists('stream_chat_settings')) {
            $pdo->exec("CREATE TABLE `stream_chat_settings` (
              `stream_id` BIGINT UNSIGNED NOT NULL,
              `slow_mode_seconds` INT UNSIGNED NOT NULL DEFAULT 1,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`stream_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    } catch (Throwable $e) {
        error_log('[BUFF schema repair] stream_chat_settings: ' . $e->getMessage());
    }

    $helperTables = [
        'stream_viewers' => "CREATE TABLE `stream_viewers` (
          `stream_id` BIGINT UNSIGNED NOT NULL,
          `user_id` BIGINT UNSIGNED NOT NULL,
          `last_seen_at` DATETIME NOT NULL,
          PRIMARY KEY (`stream_id`,`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'signal_messages' => "CREATE TABLE `signal_messages` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `stream_id` BIGINT UNSIGNED NOT NULL,
          `sender_user_id` BIGINT UNSIGNED NOT NULL,
          `recipient_user_id` BIGINT UNSIGNED NOT NULL,
          `type` VARCHAR(30) NOT NULL,
          `payload` LONGTEXT NOT NULL,
          `delivered` TINYINT(1) NOT NULL DEFAULT 0,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_signal_poll` (`stream_id`,`recipient_user_id`,`delivered`,`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        'chat_messages' => "CREATE TABLE `chat_messages` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `stream_id` BIGINT UNSIGNED NOT NULL,
          `user_id` BIGINT UNSIGNED NOT NULL,
          `message` VARCHAR(300) NOT NULL,
          `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
          PRIMARY KEY (`id`),
          KEY `idx_chat_stream_id` (`stream_id`,`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
    foreach ($helperTables as $table => $sql) {
        try {
            if (!db_table_exists($table)) $pdo->exec($sql);
        } catch (Throwable $e) {
            error_log('[BUFF schema repair] ' . $table . ': ' . $e->getMessage());
        }
    }

    // V13 admin yanıtları + ayrı yönetim uygulaması + temel CMS/RBAC tabloları.
    $pdo->exec("CREATE TABLE IF NOT EXISTS `notifications` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `user_id` BIGINT UNSIGNED NOT NULL,
      `actor_user_id` BIGINT UNSIGNED DEFAULT NULL,
      `type` VARCHAR(40) NOT NULL,
      `title` VARCHAR(120) NOT NULL,
      `body` VARCHAR(255) NOT NULL,
      `link_url` VARCHAR(255) DEFAULT NULL,
      `is_read` TINYINT(1) NOT NULL DEFAULT 0,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`), KEY `idx_notifications_user_read` (`user_id`,`is_read`,`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $supportCols = [
      'admin_reply' => 'TEXT DEFAULT NULL',
      'replied_at' => 'DATETIME DEFAULT NULL',
      'replied_by_user_id' => 'BIGINT UNSIGNED DEFAULT NULL'
    ];
    foreach ($supportCols as $column => $definition) {
        if (!db_column_exists('support_tickets', $column)) {
            try { $pdo->exec("ALTER TABLE `support_tickets` ADD COLUMN `{$column}` {$definition}"); } catch (Throwable $e) {}
        }
    }
    $reportReplyCols = [
      'admin_reply' => 'TEXT DEFAULT NULL',
      'replied_at' => 'DATETIME DEFAULT NULL',
      'replied_by_user_id' => 'BIGINT UNSIGNED DEFAULT NULL'
    ];
    foreach ($reportReplyCols as $column => $definition) {
        if (!db_column_exists('reports', $column)) {
            try { $pdo->exec("ALTER TABLE `reports` ADD COLUMN `{$column}` {$definition}"); } catch (Throwable $e) {}
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_roles` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `code` VARCHAR(40) NOT NULL, `name` VARCHAR(80) NOT NULL,
      `permissions_json` LONGTEXT DEFAULT NULL, `is_system` TINYINT(1) NOT NULL DEFAULT 0,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`), UNIQUE KEY `uq_admin_role_code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_role_assignments` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` BIGINT UNSIGNED NOT NULL,
      `role_id` BIGINT UNSIGNED NOT NULL, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`), UNIQUE KEY `uq_admin_role_assignment` (`user_id`,`role_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("INSERT IGNORE INTO admin_roles (code,name,permissions_json,is_system) VALUES
      ('super_admin','Süper Admin','[\"*\"]',1),
      ('content_manager','İçerik Yöneticisi','[\"content.*\",\"collections.*\"]',1),
      ('editor','Editör','[\"content.read\",\"content.write\"]',1),
      ('support_agent','Müşteri Temsilcisi','[\"reports.*\",\"support.*\",\"users.read\"]',1)");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `cms_titles` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `content_type` VARCHAR(20) NOT NULL DEFAULT 'film',
      `title` VARCHAR(180) NOT NULL, `description` TEXT DEFAULT NULL, `genre` VARCHAR(120) DEFAULT NULL,
      `cast_text` TEXT DEFAULT NULL, `director` VARCHAR(160) DEFAULT NULL, `age_rating` VARCHAR(20) DEFAULT NULL,
      `poster_url` VARCHAR(500) DEFAULT NULL, `trailer_url` VARCHAR(500) DEFAULT NULL,
      `status` VARCHAR(20) NOT NULL DEFAULT 'draft', `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`), KEY `idx_cms_titles_type_status` (`content_type`,`status`,`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `cms_assets` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `title_id` BIGINT UNSIGNED DEFAULT NULL,
      `asset_type` VARCHAR(30) NOT NULL, `label` VARCHAR(140) DEFAULT NULL, `language_code` VARCHAR(12) DEFAULT NULL,
      `file_url` VARCHAR(700) NOT NULL, `mime_type` VARCHAR(100) DEFAULT NULL, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`), KEY `idx_cms_assets_title` (`title_id`,`asset_type`,`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `cms_seasons` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `title_id` BIGINT UNSIGNED NOT NULL, `season_number` INT NOT NULL,
      `name` VARCHAR(140) DEFAULT NULL, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`), UNIQUE KEY `uq_cms_season` (`title_id`,`season_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `cms_episodes` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `season_id` BIGINT UNSIGNED NOT NULL, `episode_number` INT NOT NULL,
      `title` VARCHAR(180) NOT NULL, `description` TEXT DEFAULT NULL, `video_url` VARCHAR(700) DEFAULT NULL,
      `duration_seconds` INT NOT NULL DEFAULT 0, `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`), UNIQUE KEY `uq_cms_episode` (`season_id`,`episode_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `cms_collections` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(120) NOT NULL, `slug` VARCHAR(140) NOT NULL,
      `collection_type` VARCHAR(30) NOT NULL DEFAULT 'manual', `sort_order` INT NOT NULL DEFAULT 100,
      `is_active` TINYINT(1) NOT NULL DEFAULT 1, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`), UNIQUE KEY `uq_cms_collection_slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `cms_collection_items` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `collection_id` BIGINT UNSIGNED NOT NULL,
      `title_id` BIGINT UNSIGNED NOT NULL, `sort_order` INT NOT NULL DEFAULT 100,
      PRIMARY KEY (`id`), UNIQUE KEY `uq_collection_title` (`collection_id`,`title_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `membership_plans` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(40) NOT NULL, `name` VARCHAR(100) NOT NULL,
      `price_kurus` INT NOT NULL DEFAULT 0, `billing_period` VARCHAR(20) NOT NULL DEFAULT 'monthly',
      `quality` VARCHAR(20) NOT NULL DEFAULT 'HD', `ads_enabled` TINYINT(1) NOT NULL DEFAULT 0,
      `family_slots` INT NOT NULL DEFAULT 1, `is_active` TINYINT(1) NOT NULL DEFAULT 1,
      PRIMARY KEY (`id`), UNIQUE KEY `uq_membership_plan_code` (`code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("INSERT IGNORE INTO membership_plans (code,name,price_kurus,billing_period,quality,ads_enabled,family_slots,is_active) VALUES
      ('free','Ücretsiz',0,'monthly','HD',1,1,1),('plus','Plus',9900,'monthly','FHD',0,1,1),
      ('pro','Pro',14900,'monthly','FHD',0,2,1),('premium','Premium',22900,'monthly','4K',0,5,1)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `stream_keys` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` BIGINT UNSIGNED NOT NULL,
      `stream_key` VARCHAR(96) NOT NULL, `status` VARCHAR(20) NOT NULL DEFAULT 'active',
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `last_used_at` DATETIME DEFAULT NULL,
      PRIMARY KEY (`id`), UNIQUE KEY `uq_stream_key_value` (`stream_key`), KEY `idx_stream_key_user` (`user_id`,`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `ip_blocks` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `ip_address` VARCHAR(64) NOT NULL, `reason` VARCHAR(255) DEFAULT NULL,
      `is_active` TINYINT(1) NOT NULL DEFAULT 1, `created_by_user_id` BIGINT UNSIGNED DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`), UNIQUE KEY `uq_ip_block_ip` (`ip_address`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `system_announcements` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `title` VARCHAR(140) NOT NULL, `body` VARCHAR(1000) NOT NULL,
      `channel` VARCHAR(20) NOT NULL DEFAULT 'in_app', `target_scope` VARCHAR(30) NOT NULL DEFAULT 'all',
      `is_active` TINYINT(1) NOT NULL DEFAULT 1, `created_by_user_id` BIGINT UNSIGNED DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `epg_events` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `stream_id` BIGINT UNSIGNED DEFAULT NULL,
      `channel_name` VARCHAR(120) NOT NULL, `title` VARCHAR(180) NOT NULL, `start_at` DATETIME NOT NULL, `end_at` DATETIME NOT NULL,
      `status` VARCHAR(20) NOT NULL DEFAULT 'scheduled', `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`), KEY `idx_epg_schedule` (`start_at`,`end_at`,`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `transcode_jobs` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `asset_id` BIGINT UNSIGNED DEFAULT NULL, `input_url` VARCHAR(700) NOT NULL,
      `output_profile` VARCHAR(80) NOT NULL DEFAULT 'HLS_AUTO', `status` VARCHAR(30) NOT NULL DEFAULT 'queued', `progress` INT NOT NULL DEFAULT 0,
      `error_message` VARCHAR(500) DEFAULT NULL, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `drm_policies` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(120) NOT NULL, `widevine_enabled` TINYINT(1) NOT NULL DEFAULT 0,
      `fairplay_enabled` TINYINT(1) NOT NULL DEFAULT 0, `geo_mode` VARCHAR(20) NOT NULL DEFAULT 'off',
      `allowed_countries` VARCHAR(500) DEFAULT NULL, `blocked_countries` VARCHAR(500) DEFAULT NULL, `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $ready = true;
}

function request_json(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function request_method(): string {
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function require_post(): void {
    if (request_method() !== 'POST') {
        json_response(['message' => 'Bu işlem POST isteği gerektirir.'], 405);
    }
}

function text_len(string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function text_substr(string $value, int $start, int $length): string {
    return function_exists('mb_substr') ? mb_substr($value, $start, $length, 'UTF-8') : substr($value, $start, $length);
}

function h(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fetch_user_by_id(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function user_features(int $userId): array {
    $stmt = db()->prepare('SELECT * FROM user_features WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if ($row) {
        return $row;
    }
    $stmt = db()->prepare('INSERT IGNORE INTO user_features (user_id) VALUES (?)');
    $stmt->execute([$userId]);
    return [
        'user_id' => $userId,
        'is_og' => 0,
        'vip_until' => null,
        'vip_tier' => null,
        'chat_color' => '#E7ECFF',
        'preferred_language' => 'tr',
        'accent_theme' => 'buff',
    ];
}

function is_vip_feature(array $features): bool {
    $until = $features['vip_until'] ?? null;
    return $until !== null && strtotime((string)$until) > time();
}

function public_user(array $user): array {
    $features = user_features((int)$user['id']);
    $preferences = user_preferences((int)$user['id']);
    return [
        'id' => (int)$user['id'],
        'publicId' => (string)($user['public_id'] ?? (function_exists('v16_public_id') ? v16_public_id((int)$user['id']) : ('BF'.str_pad((string)$user['id'],8,'0',STR_PAD_LEFT)))),
        'isPrivate'=>(bool)($user['is_private']??false),'replayVisibility'=>$user['replay_visibility']??'private',
        'username' => (string)$user['username'],
        'email' => (string)$user['email'],
        'phone' => (string)($user['phone'] ?? ''),
        'billingAddress' => (string)($user['billing_address'] ?? ''),
        'displayName' => (string)$user['display_name'],
        'avatarUrl' => $user['avatar_url'] ?: null,
        'bio' => (string)($user['bio'] ?? ''),
        'accountMode' => (string)($user['account_mode'] ?? 'viewer'),
        'creatorEnabled' => (bool)($user['creator_enabled'] ?? 0),
        'isAdmin' => (bool)($user['is_admin'] ?? 0),
        'accountStatus' => (string)($user['account_status'] ?? 'active'),
        'walletBalance' => (int)($user['wallet_balance'] ?? 0),
        'creatorEarnings' => (int)($user['creator_earnings'] ?? 0),
        'totalGiftsReceived' => (int)($user['total_gifts_received'] ?? 0),
        'isOg' => (bool)($features['is_og'] ?? 0),
        'isVip' => is_vip_feature($features),
        'vipTier' => (string)($features['vip_tier'] ?? ''),
        'vipUntil' => $features['vip_until'] ?? null,
        'chatColor' => (string)($features['chat_color'] ?? '#E7ECFF'),
        'preferredLanguage' => (string)($features['preferred_language'] ?? 'tr'),
        'accentTheme' => (string)($preferences['accent_theme'] ?? $features['accent_theme'] ?? 'buff'),
        'themeMode' => (string)($preferences['theme_mode'] ?? 'dark'),
        'sidebarCollapsed' => (bool)($preferences['sidebar_collapsed'] ?? 0),
        'defaultStreamDelay' => (int)($preferences['default_stream_delay'] ?? 5),
        'reduceMotion' => (bool)($preferences['reduce_motion'] ?? 0),
        'createdAt' => $user['created_at'] ?? null,
    ];
}

function current_user(): ?array {
    $id = (int)($_SESSION['user_id'] ?? 0);
    if ($id > 0) return fetch_user_by_id($id);
    if (function_exists('v14_token_user')) {
        $user = v14_token_user();
        if ($user) {
            $_SESSION['user_id'] = (int)$user['id'];
            return $user;
        }
    }
    return null;
}

function require_auth(): array {
    $user = current_user();
    if (!$user) {
        json_response(['message' => 'Bu işlem için giriş yapmalısınız.'], 401);
    }
    if (($user['account_status'] ?? 'active') !== 'active') {
        unset($_SESSION['user_id']);
        json_response(['message' => 'Hesabınız geçici olarak kullanıma kapalı.'], 403);
    }
    touch_user_session((int)$user['id']);
    return $user;
}

function normalize_tags(string $tags): string {
    $parts = array_filter(array_map('trim', explode(',', $tags)), fn($v) => $v !== '');
    $parts = array_slice(array_values(array_unique($parts)), 0, 8);
    return implode(',', $parts);
}

function get_stream_by_id(int $streamId): ?array {
    $sql = 'SELECT s.*, u.username, u.display_name, u.avatar_url FROM streams s JOIN users u ON u.id = s.user_id WHERE s.id = ? LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute([$streamId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function active_viewer_count(int $streamId): int {
    try {
        if (!db_table_exists('stream_viewers')) return 0;
        $stmt = db()->prepare('SELECT COUNT(*) AS c FROM stream_viewers WHERE stream_id = ? AND last_seen_at >= (NOW() - INTERVAL 20 SECOND)');
        $stmt->execute([$streamId]);
        $row = $stmt->fetch();
        return (int)($row['c'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function map_stream(array $row): array {
    $tagParts = [];
    if (!empty($row['tags'])) {
        $tagParts = array_values(array_filter(array_map('trim', explode(',', (string)$row['tags']))));
    }
    $streamId = (int)$row['id'];
    $viewers = array_key_exists('viewer_count_live', $row) ? (int)$row['viewer_count_live'] : active_viewer_count($streamId);
    return [
        'id' => $streamId,
        'userId' => (int)$row['user_id'],
        'streamerName' => (string)$row['display_name'],
        'username' => (string)$row['username'],
        'avatarUrl' => $row['avatar_url'] ?: null,
        'title' => (string)$row['title'],
        'category' => (string)$row['category'],
        'description' => (string)($row['description'] ?? ''),
        'language' => (string)($row['language'] ?? 'TR'),
        'delaySeconds' => (int)($row['delay_seconds'] ?? 5),
        'adultOnly' => (bool)($row['adult_only'] ?? 0),
        'matureLanguage' => (bool)($row['mature_language'] ?? 0),
        'tags' => $tagParts,
        'isLive' => (bool)$row['is_live'],
        'viewerCount' => $viewers,
        'giftTotal' => (int)($row['gift_total'] ?? 0),
        'startedAt' => $row['started_at'] ?? null,
        'followerCount' => (int)($row['follower_count'] ?? 0),
    ];
}

function credit_wallet(int $userId, int $amount, string $type, string $description, ?int $relatedUserId = null): void {
    if ($amount <= 0) {
        throw new InvalidArgumentException('Geçersiz kredi miktarı.');
    }
    $pdo = db();
    $stmt = $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?');
    $stmt->execute([$amount, $userId]);
    $stmt = $pdo->prepare('INSERT INTO wallet_transactions (user_id, amount, type, description, related_user_id) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $amount, $type, $description, $relatedUserId]);
}

function debit_wallet(int $userId, int $amount, string $type, string $description, ?int $relatedUserId = null): void {
    if ($amount <= 0) {
        throw new InvalidArgumentException('Geçersiz borç miktarı.');
    }
    $pdo = db();
    $stmt = $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ? AND wallet_balance >= ?');
    $stmt->execute([$amount, $userId, $amount]);
    if ($stmt->rowCount() < 1) {
        throw new RuntimeException('Yetersiz bakiye.');
    }
    $stmt = $pdo->prepare('INSERT INTO wallet_transactions (user_id, amount, type, description, related_user_id) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userId, -$amount, $type, $description, $relatedUserId]);
}


function creator_coin_try_rate(): float {
    $rate = (float)(app_env()['CREATOR_COIN_TRY_RATE'] ?? 0.01);
    return $rate > 0 ? $rate : 0.01;
}

function credit_creator_earnings(int $userId, int $amount, string $description, ?int $relatedUserId = null): void {
    if ($amount <= 0) throw new InvalidArgumentException('Geçersiz kazanç miktarı.');
    $pdo = db();
    $stmt = $pdo->prepare('UPDATE users SET creator_earnings = creator_earnings + ? WHERE id = ?');
    $stmt->execute([$amount, $userId]);
    $stmt = $pdo->prepare('INSERT INTO wallet_transactions (user_id, amount, type, description, related_user_id) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $amount, 'creator_earning', $description, $relatedUserId]);
}

function debit_creator_earnings(int $userId, int $amount, string $description): void {
    if ($amount <= 0) throw new InvalidArgumentException('Geçersiz çekim miktarı.');
    $pdo = db();
    $stmt = $pdo->prepare('UPDATE users SET creator_earnings = creator_earnings - ? WHERE id = ? AND creator_earnings >= ?');
    $stmt->execute([$amount, $userId, $amount]);
    if ($stmt->rowCount() < 1) throw new RuntimeException('Yetersiz yayıncı kazancı.');
    $stmt = $pdo->prepare('INSERT INTO wallet_transactions (user_id, amount, type, description) VALUES (?, ?, ?, ?)');
    $stmt->execute([$userId, -$amount, 'creator_payout_request', $description]);
}

function vip_tier_rank(?string $tier): int {
    return ['plus' => 1, 'pro' => 2, 'premium' => 3][strtolower((string)$tier)] ?? 0;
}

function user_preferences(int $userId): array {
    $defaults = ['user_id'=>$userId,'theme_mode'=>'dark','accent_theme'=>'buff','sidebar_collapsed'=>0,'default_stream_delay'=>5,'reduce_motion'=>0];
    try {
        if (!db_table_exists('user_preferences')) return $defaults;
        $stmt = db()->prepare('SELECT * FROM user_preferences WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if ($row) return array_merge($defaults, $row);
        db()->prepare('INSERT IGNORE INTO user_preferences (user_id) VALUES (?)')->execute([$userId]);
    } catch (Throwable $e) {
        return $defaults;
    }
    return $defaults;
}

function normalize_iban(string $iban): string {
    return strtoupper(preg_replace('/\s+/', '', trim($iban)) ?? '');
}

function valid_iban(string $iban): bool {
    $iban = normalize_iban($iban);
    if (!preg_match('/^[A-Z]{2}[0-9A-Z]{13,32}$/', $iban)) {
        return false;
    }
    $rearranged = substr($iban, 4) . substr($iban, 0, 4);
    $numeric = '';
    foreach (str_split($rearranged) as $ch) {
        $numeric .= ctype_alpha($ch) ? (string)(ord($ch) - 55) : $ch;
    }
    $remainder = 0;
    foreach (str_split($numeric) as $digit) {
        $remainder = ($remainder * 10 + (int)$digit) % 97;
    }
    return $remainder === 1;
}

function encryption_key(): string {
    $raw = (string)(app_env()['APP_ENCRYPTION_KEY'] ?? '');
    if ($raw === '') {
        throw new RuntimeException('APP_ENCRYPTION_KEY .env içinde tanımlı değil.');
    }
    return hash('sha256', $raw, true);
}

function encrypt_sensitive(string $plaintext): string {
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', encryption_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) {
        throw new RuntimeException('Hassas veri şifrelenemedi.');
    }
    return base64_encode($iv . $tag . $cipher);
}

function decrypt_sensitive(string $encoded): string {
    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) < 29) {
        throw new RuntimeException('Şifreli veri çözülemedi.');
    }
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', encryption_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($plain === false) {
        throw new RuntimeException('Şifreli veri çözülemedi.');
    }
    return $plain;
}

function masked_iban_from_last4(string $last4): string {
    return '•••• •••• •••• •••• •••• ' . $last4;
}

function stream_role(int $streamId, int $userId): string {
    $stream = get_stream_by_id($streamId);
    if (!$stream) return 'viewer';
    if ((int)$stream['user_id'] === $userId) return 'owner';
    $stmt = db()->prepare('SELECT 1 FROM stream_moderators WHERE streamer_user_id = ? AND moderator_user_id = ? LIMIT 1');
    $stmt->execute([(int)$stream['user_id'], $userId]);
    return $stmt->fetchColumn() ? 'moderator' : 'viewer';
}

function create_notification(int $userId, ?int $actorUserId, string $type, string $title, string $body, ?string $linkUrl = null): void {
    $stmt = db()->prepare('INSERT INTO notifications (user_id, actor_user_id, type, title, body, link_url) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $actorUserId, $type, text_substr($title, 0, 120), text_substr($body, 0, 255), $linkUrl]);
    $notificationId = (int)db()->lastInsertId();
    if (function_exists('v14_publish')) {
        v14_publish('notification.created', [
            'id'=>$notificationId,'type'=>$type,'title'=>text_substr($title,0,120),
            'body'=>text_substr($body,0,255),'linkUrl'=>$linkUrl,'actorUserId'=>$actorUserId
        ], $userId, null, 'user.'.$userId);
    }
}



function client_ip(): string {
    return text_substr((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 64);
}

function touch_user_session(int $userId): void {
    try {
        $key = session_id();
        if ($key === '') return;
        $stmt = db()->prepare('INSERT INTO user_sessions (user_id, session_key, ip_address, user_agent, last_seen_at) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), ip_address=VALUES(ip_address), user_agent=VALUES(user_agent), last_seen_at=NOW()');
        $stmt->execute([$userId, $key, client_ip(), text_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255)]);
    } catch (Throwable $e) {
        // V12 migration may not be installed yet; authentication must still be usable enough to show install guidance.
    }
}


function ensure_admin_control_schema(): void {
    static $ready = false;
    if ($ready) return;
    $pdo = db();

    // Admin paneli, destek ve şikayet akışının minimum çalışma şeması.
    // Eski/yarım kurulumlarda paneli tüm migration zincirine bağımlı bırakmaz.
    $pdo->exec("CREATE TABLE IF NOT EXISTS `support_tickets` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `user_id` BIGINT UNSIGNED NOT NULL,
      `ticket_type` VARCHAR(30) NOT NULL DEFAULT 'request',
      `subject` VARCHAR(140) NOT NULL,
      `message` VARCHAR(2000) NOT NULL,
      `status` VARCHAR(30) NOT NULL DEFAULT 'open',
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_support_user_status` (`user_id`,`status`,`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `reports` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `reporter_user_id` BIGINT UNSIGNED NOT NULL,
      `target_user_id` BIGINT UNSIGNED DEFAULT NULL,
      `stream_id` BIGINT UNSIGNED DEFAULT NULL,
      `chat_message_id` BIGINT UNSIGNED DEFAULT NULL,
      `feed_post_id` BIGINT UNSIGNED DEFAULT NULL,
      `report_scope` VARCHAR(20) NOT NULL DEFAULT 'stream',
      `target_username_snapshot` VARCHAR(60) DEFAULT NULL,
      `stream_title_snapshot` VARCHAR(160) DEFAULT NULL,
      `target_snapshot` VARCHAR(255) DEFAULT NULL,
      `category` VARCHAR(50) NOT NULL DEFAULT 'other',
      `description` VARCHAR(500) NOT NULL,
      `status` VARCHAR(20) NOT NULL DEFAULT 'open',
      `resolved_at` DATETIME DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_reports_reporter` (`reporter_user_id`,`status`,`id`),
      KEY `idx_reports_status` (`status`,`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $reportColumns = [
      'target_user_id' => 'BIGINT UNSIGNED DEFAULT NULL',
      'stream_id' => 'BIGINT UNSIGNED DEFAULT NULL',
      'chat_message_id' => 'BIGINT UNSIGNED DEFAULT NULL',
      'feed_post_id' => 'BIGINT UNSIGNED DEFAULT NULL',
      'report_scope' => "VARCHAR(20) NOT NULL DEFAULT 'stream'",
      'target_username_snapshot' => 'VARCHAR(60) DEFAULT NULL',
      'stream_title_snapshot' => 'VARCHAR(160) DEFAULT NULL',
      'target_snapshot' => 'VARCHAR(255) DEFAULT NULL',
      'category' => "VARCHAR(50) NOT NULL DEFAULT 'other'",
      'description' => "VARCHAR(500) NOT NULL DEFAULT ''",
      'status' => "VARCHAR(20) NOT NULL DEFAULT 'open'",
      'resolved_at' => 'DATETIME DEFAULT NULL',
    ];
    foreach ($reportColumns as $column => $definition) {
        if (!db_column_exists('reports', $column)) {
            try { $pdo->exec("ALTER TABLE `reports` ADD COLUMN `{$column}` {$definition}"); }
            catch (Throwable $e) { error_log('[BUFF admin reports] '.$column.': '.$e->getMessage()); }
        }
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_audit_logs` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `admin_user_id` BIGINT UNSIGNED NOT NULL,
      `action_name` VARCHAR(80) NOT NULL,
      `target_type` VARCHAR(60) DEFAULT NULL,
      `target_id` BIGINT UNSIGNED DEFAULT NULL,
      `details_json` LONGTEXT DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_admin_audit_created` (`created_at`,`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `site_settings` (
      `setting_key` VARCHAR(80) NOT NULL,
      `setting_value` LONGTEXT DEFAULT NULL,
      `setting_type` VARCHAR(20) NOT NULL DEFAULT 'string',
      `is_public` TINYINT(1) NOT NULL DEFAULT 0,
      `updated_by_user_id` BIGINT UNSIGNED DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `user_sessions` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `user_id` BIGINT UNSIGNED NOT NULL,
      `session_key` VARCHAR(128) NOT NULL,
      `ip_address` VARCHAR(64) DEFAULT NULL,
      `user_agent` VARCHAR(255) DEFAULT NULL,
      `last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_user_session_key` (`session_key`),
      KEY `idx_user_sessions_seen` (`last_seen_at`,`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // V13 yönetim modülleri: eski buff_v126 veritabanlarında bu tablolar
    // bulunmayabiliyor. Admin API'leri açılırken idempotent olarak tamamla.
    $adminModuleTables = [
      'notifications' => "CREATE TABLE IF NOT EXISTS `notifications` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` BIGINT UNSIGNED NOT NULL,
        `actor_user_id` BIGINT UNSIGNED DEFAULT NULL, `type` VARCHAR(40) NOT NULL,
        `title` VARCHAR(120) NOT NULL, `body` VARCHAR(255) NOT NULL, `link_url` VARCHAR(255) DEFAULT NULL,
        `is_read` TINYINT(1) NOT NULL DEFAULT 0, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), KEY `idx_notifications_user_read` (`user_id`,`is_read`,`id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      'cms_titles' => "CREATE TABLE IF NOT EXISTS `cms_titles` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `content_type` VARCHAR(20) NOT NULL DEFAULT 'film',
        `title` VARCHAR(180) NOT NULL, `description` TEXT DEFAULT NULL, `genre` VARCHAR(120) DEFAULT NULL,
        `cast_text` TEXT DEFAULT NULL, `director` VARCHAR(160) DEFAULT NULL, `age_rating` VARCHAR(20) DEFAULT NULL,
        `poster_url` VARCHAR(500) DEFAULT NULL, `trailer_url` VARCHAR(500) DEFAULT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'draft', `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), KEY `idx_cms_titles_type_status` (`content_type`,`status`,`id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      'cms_assets' => "CREATE TABLE IF NOT EXISTS `cms_assets` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `title_id` BIGINT UNSIGNED DEFAULT NULL,
        `asset_type` VARCHAR(30) NOT NULL, `label` VARCHAR(140) DEFAULT NULL, `language_code` VARCHAR(12) DEFAULT NULL,
        `file_url` VARCHAR(700) NOT NULL, `mime_type` VARCHAR(100) DEFAULT NULL, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), KEY `idx_cms_assets_title` (`title_id`,`asset_type`,`id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      'cms_seasons' => "CREATE TABLE IF NOT EXISTS `cms_seasons` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `title_id` BIGINT UNSIGNED NOT NULL, `season_number` INT NOT NULL,
        `name` VARCHAR(140) DEFAULT NULL, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), UNIQUE KEY `uq_cms_season` (`title_id`,`season_number`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      'cms_episodes' => "CREATE TABLE IF NOT EXISTS `cms_episodes` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `season_id` BIGINT UNSIGNED NOT NULL, `episode_number` INT NOT NULL,
        `title` VARCHAR(180) NOT NULL, `description` TEXT DEFAULT NULL, `video_url` VARCHAR(700) DEFAULT NULL,
        `duration_seconds` INT NOT NULL DEFAULT 0, `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), UNIQUE KEY `uq_cms_episode` (`season_id`,`episode_number`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      'cms_collections' => "CREATE TABLE IF NOT EXISTS `cms_collections` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(120) NOT NULL, `slug` VARCHAR(140) NOT NULL,
        `collection_type` VARCHAR(30) NOT NULL DEFAULT 'manual', `sort_order` INT NOT NULL DEFAULT 100,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), UNIQUE KEY `uq_cms_collection_slug` (`slug`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      'cms_collection_items' => "CREATE TABLE IF NOT EXISTS `cms_collection_items` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `collection_id` BIGINT UNSIGNED NOT NULL,
        `title_id` BIGINT UNSIGNED NOT NULL, `sort_order` INT NOT NULL DEFAULT 100,
        PRIMARY KEY (`id`), UNIQUE KEY `uq_collection_title` (`collection_id`,`title_id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      'membership_plans' => "CREATE TABLE IF NOT EXISTS `membership_plans` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(40) NOT NULL, `name` VARCHAR(100) NOT NULL,
        `price_kurus` INT NOT NULL DEFAULT 0, `billing_period` VARCHAR(20) NOT NULL DEFAULT 'monthly',
        `quality` VARCHAR(20) NOT NULL DEFAULT 'HD', `ads_enabled` TINYINT(1) NOT NULL DEFAULT 0,
        `family_slots` INT NOT NULL DEFAULT 1, `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (`id`), UNIQUE KEY `uq_membership_plan_code` (`code`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      'stream_keys' => "CREATE TABLE IF NOT EXISTS `stream_keys` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` BIGINT UNSIGNED NOT NULL,
        `stream_key` VARCHAR(96) NOT NULL, `status` VARCHAR(20) NOT NULL DEFAULT 'active',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `last_used_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`), UNIQUE KEY `uq_stream_key_value` (`stream_key`), KEY `idx_stream_key_user` (`user_id`,`status`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      'ip_blocks' => "CREATE TABLE IF NOT EXISTS `ip_blocks` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `ip_address` VARCHAR(64) NOT NULL, `reason` VARCHAR(255) DEFAULT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1, `created_by_user_id` BIGINT UNSIGNED DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`), UNIQUE KEY `uq_ip_block_ip` (`ip_address`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      'system_announcements' => "CREATE TABLE IF NOT EXISTS `system_announcements` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `title` VARCHAR(140) NOT NULL, `body` VARCHAR(1000) NOT NULL,
        `channel` VARCHAR(20) NOT NULL DEFAULT 'in_app', `target_scope` VARCHAR(30) NOT NULL DEFAULT 'all',
        `is_active` TINYINT(1) NOT NULL DEFAULT 1, `created_by_user_id` BIGINT UNSIGNED DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (`id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      'epg_events' => "CREATE TABLE IF NOT EXISTS `epg_events` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `stream_id` BIGINT UNSIGNED DEFAULT NULL,
        `channel_name` VARCHAR(120) NOT NULL, `title` VARCHAR(180) NOT NULL, `start_at` DATETIME NOT NULL, `end_at` DATETIME NOT NULL,
        `status` VARCHAR(20) NOT NULL DEFAULT 'scheduled', `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), KEY `idx_epg_schedule` (`start_at`,`end_at`,`status`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      'transcode_jobs' => "CREATE TABLE IF NOT EXISTS `transcode_jobs` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `asset_id` BIGINT UNSIGNED DEFAULT NULL, `input_url` VARCHAR(700) NOT NULL,
        `output_profile` VARCHAR(80) NOT NULL DEFAULT 'HLS_AUTO', `status` VARCHAR(30) NOT NULL DEFAULT 'queued',
        `progress` INT NOT NULL DEFAULT 0, `error_message` VARCHAR(500) DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
      'drm_policies' => "CREATE TABLE IF NOT EXISTS `drm_policies` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(120) NOT NULL,
        `widevine_enabled` TINYINT(1) NOT NULL DEFAULT 0, `fairplay_enabled` TINYINT(1) NOT NULL DEFAULT 0,
        `geo_mode` VARCHAR(20) NOT NULL DEFAULT 'off', `allowed_countries` VARCHAR(500) DEFAULT NULL,
        `blocked_countries` VARCHAR(500) DEFAULT NULL, `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (`id`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    foreach ($adminModuleTables as $table => $sql) {
        try { $pdo->exec($sql); }
        catch (Throwable $e) { error_log('[BUFF admin schema] '.$table.': '.$e->getMessage()); }
    }

    // Varsayılan paketler ve admin yanıt kolonları.
    try {
        $pdo->exec("INSERT IGNORE INTO membership_plans (code,name,price_kurus,billing_period,quality,ads_enabled,family_slots,is_active) VALUES
          ('free','Ücretsiz',0,'monthly','HD',1,1,1),('plus','Plus',9900,'monthly','FHD',0,1,1),
          ('pro','Pro',14900,'monthly','FHD',0,2,1),('premium','Premium',22900,'monthly','4K',0,5,1)");
    } catch (Throwable $e) {}

    foreach ([
      'admin_reply' => 'TEXT DEFAULT NULL',
      'replied_at' => 'DATETIME DEFAULT NULL',
      'replied_by_user_id' => 'BIGINT UNSIGNED DEFAULT NULL'
    ] as $column => $definition) {
        if (!db_column_exists('support_tickets', $column)) {
            try { $pdo->exec("ALTER TABLE `support_tickets` ADD COLUMN `{$column}` {$definition}"); } catch (Throwable $e) {}
        }
        if (!db_column_exists('reports', $column)) {
            try { $pdo->exec("ALTER TABLE `reports` ADD COLUMN `{$column}` {$definition}"); } catch (Throwable $e) {}
        }
    }

    $ready = true;
}

function admin_table_rows(string $table, string $sql, array $args = []): array {
    try {
        if (!db_table_exists($table)) return [];
        $stmt = db()->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('[BUFF admin list] '.$table.': '.$e->getMessage());
        return [];
    }
}

function admin_scalar(string $table, string $sql, int|float $default = 0): int|float {
    try {
        if (!db_table_exists($table)) return $default;
        $value = db()->query($sql)->fetchColumn();
        return is_numeric($value) ? $value + 0 : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function require_admin(): array {
    $adminId = (int)($_SESSION['admin_user_id'] ?? 0);
    if ($adminId < 1) {
        // Legacy compatibility: a normal authenticated session may also carry admin rights.
        $legacyId = (int)($_SESSION['user_id'] ?? 0);
        if ($legacyId > 0) {
            $legacy = fetch_user_by_id($legacyId);
            if ($legacy && !empty($legacy['is_admin'])) {
                $_SESSION['admin_user_id'] = $legacyId;
                $adminId = $legacyId;
            }
        }
    }
    if ($adminId < 1) json_response(['message' => 'Admin oturumu gerekli.'], 401);

    $user = fetch_user_by_id($adminId);
    if (!$user || empty($user['is_admin'])) {
        unset($_SESSION['admin_user_id'], $_SESSION['admin_authenticated_at']);
        json_response(['message' => 'Bu alan yalnızca yönetici hesabına açıktır.'], 403);
    }
    if (($user['account_status'] ?? 'active') !== 'active') {
        unset($_SESSION['admin_user_id'], $_SESSION['admin_authenticated_at']);
        json_response(['message' => 'Bu yönetici hesabı aktif değil.'], 403);
    }

    // Bootstrap admin is never locked out by a missing RBAC row. Other admins
    // must have at least one non-expired role assignment.
    if (!is_bootstrap_admin_account($user) && !admin_has_active_assignment($adminId)) {
        try { db()->prepare('UPDATE users SET is_admin=0 WHERE id=?')->execute([$adminId]); } catch (Throwable $e) {}
        unset($_SESSION['admin_user_id'], $_SESSION['admin_authenticated_at']);
        json_response(['message' => 'Yönetici yetkinin süresi dolmuş veya kaldırılmış.'], 403);
    }
    return $user;
}

function ensure_admin_rbac_schema(): bool {
    try {
        $pdo = db();
        $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_roles` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `code` VARCHAR(40) NOT NULL,
          `name` VARCHAR(80) NOT NULL,
          `permissions_json` LONGTEXT DEFAULT NULL,
          `is_system` TINYINT(1) NOT NULL DEFAULT 0,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_admin_role_code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_role_assignments` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `user_id` BIGINT UNSIGNED NOT NULL,
          `role_id` BIGINT UNSIGNED NOT NULL,
          `granted_by_user_id` BIGINT UNSIGNED DEFAULT NULL,
          `expires_at` DATETIME DEFAULT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_admin_role_assignment` (`user_id`,`role_id`),
          KEY `idx_admin_assignment_expiry` (`user_id`,`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if (!db_column_exists('admin_role_assignments','granted_by_user_id')) {
            try { $pdo->exec("ALTER TABLE `admin_role_assignments` ADD COLUMN `granted_by_user_id` BIGINT UNSIGNED DEFAULT NULL AFTER `role_id`"); } catch (Throwable $e) {}
        }
        if (!db_column_exists('admin_role_assignments','expires_at')) {
            try { $pdo->exec("ALTER TABLE `admin_role_assignments` ADD COLUMN `expires_at` DATETIME DEFAULT NULL AFTER `granted_by_user_id`"); } catch (Throwable $e) {}
        }
        $pdo->exec("INSERT IGNORE INTO `admin_roles` (`code`,`name`,`permissions_json`,`is_system`) VALUES
          ('super_admin','Süper Admin','[\"*\"]',1),
          ('content_manager','İçerik Yöneticisi','[\"content.*\",\"collections.*\"]',1),
          ('editor','Editör','[\"content.read\",\"content.write\"]',1),
          ('support_agent','Müşteri Temsilcisi','[\"reports.*\",\"support.*\",\"users.read\"]',1)");
        return db_table_exists('admin_roles') && db_table_exists('admin_role_assignments');
    } catch (Throwable $e) {
        error_log('[BUFF RBAC repair] ' . $e->getMessage());
        return false;
    }
}

function is_bootstrap_admin_account(array $user): bool {
    $env = app_env();
    $adminUsername = strtolower(trim((string)($env['ADMIN_USERNAME'] ?? 'admin')));
    $adminEmail = strtolower(trim((string)($env['ADMIN_EMAIL'] ?? 'buffyonetim@gmail.com')));
    $username = strtolower(trim((string)($user['username'] ?? '')));
    $email = strtolower(trim((string)($user['email'] ?? '')));
    return ($adminUsername !== '' && $username === $adminUsername) || ($adminEmail !== '' && $email === $adminEmail);
}

function admin_has_active_assignment(int $userId): bool {
    try {
        if (!ensure_admin_rbac_schema()) return false;
        $stmt = db()->prepare('SELECT 1 FROM admin_role_assignments WHERE user_id=? AND (expires_at IS NULL OR expires_at>NOW()) LIMIT 1');
        $stmt->execute([$userId]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) { return false; }
}

function admin_permission_set(int $userId): array {
    try {
        if (!ensure_admin_rbac_schema()) return [];
        $stmt = db()->prepare('SELECT r.permissions_json FROM admin_role_assignments a JOIN admin_roles r ON r.id=a.role_id WHERE a.user_id=? AND (a.expires_at IS NULL OR a.expires_at>NOW())');
        $stmt->execute([$userId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $items = json_decode((string)($row['permissions_json'] ?? '[]'), true);
            if (is_array($items)) foreach ($items as $item) if (is_string($item)) $out[$item] = true;
        }
        return array_keys($out);
    } catch (Throwable $e) { return []; }
}

function admin_can(int $userId, string $permission): bool {
    $user = fetch_user_by_id($userId);
    if (!$user || empty($user['is_admin']) || (($user['account_status'] ?? 'active') !== 'active')) return false;
    // The original bootstrap admin always retains recovery access.
    if (is_bootstrap_admin_account($user)) return true;
    $perms = admin_permission_set($userId);
    if (in_array('*', $perms, true)) return true;
    foreach ($perms as $p) {
        if ($p === $permission) return true;
        if (str_ends_with($p, '.*') && str_starts_with($permission, substr($p, 0, -1))) return true;
    }
    return false;
}

function require_admin_permission(string $permission): array {
    $admin = require_admin();
    if (!admin_can((int)$admin['id'], $permission)) json_response(['message'=>'Bu yönetim modülü için yetkin yok.'],403);
    return $admin;
}

function admin_audit(int $adminUserId, string $action, ?string $targetType = null, ?int $targetId = null, array $details = []): void {
    try {
        $stmt = db()->prepare('INSERT INTO admin_audit_logs (admin_user_id, action_name, target_type, target_id, details_json) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$adminUserId, text_substr($action,0,80), $targetType, $targetId, $details ? json_encode($details, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null]);
    } catch (Throwable $e) {}
}


function site_setting(string $key, mixed $default = null): mixed {
    try {
        $stmt = db()->prepare('SELECT setting_value, setting_type FROM site_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if (!$row) return $default;
        $value = $row['setting_value'];
        return match ((string)($row['setting_type'] ?? 'string')) {
            'boolean' => in_array(strtolower((string)$value), ['1','true','yes','on'], true),
            'integer' => (int)$value,
            'json' => json_decode((string)$value, true) ?? $default,
            default => $value,
        };
    } catch (Throwable $e) { return $default; }
}

function safe_upload_name(string $extension): string {
    return bin2hex(random_bytes(12)) . '.' . strtolower($extension);
}

function ensure_upload_dir(string $folder): string {
    $base = dirname(__DIR__) . '/uploads/' . trim($folder, '/');
    if (!is_dir($base) && !mkdir($base, 0755, true) && !is_dir($base)) {
        throw new RuntimeException('Yükleme klasörü oluşturulamadı.');
    }
    return $base;
}

function public_upload_url(string $folder, string $filename): string {
    return '../uploads/' . trim($folder, '/') . '/' . rawurlencode($filename);
}


// BUFF V14 production infrastructure (JWT/Redis/storage/queue/media/loyalty).
require_once __DIR__ . '/v14.php';
require_once __DIR__ . '/v16.php';

// Lightweight PHP API telemetry for Prometheus/Grafana dashboards.
$__buff_v14_request_started = microtime(true);
register_shutdown_function(static function () use ($__buff_v14_request_started): void {
    if (!function_exists('v14_telemetry')) return;
    $uri = (string)($_SERVER['REQUEST_URI'] ?? 'cli');
    if (!str_contains($uri, '/backend/api/')) return;
    try { v14_telemetry(parse_url($uri, PHP_URL_PATH) ?: $uri, (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'), http_response_code() ?: 200, $__buff_v14_request_started); }
    catch (Throwable $e) {}
});

require_once __DIR__.'/v17.php';
