-- BUFF V14 FINAL - TEK DOSYALIK VERITABANI KURULUMU
-- Bu dosya phpMyAdmin icinde dogrudan calistirilabilir.
-- V14 PHP + Node oturum, queue, object storage metadata, ABR, realtime, admin/CMS ve gamification semasini kapsar.
-- Mevcut eski veritabanlari silinmez; bu surum temiz bir veritabani kullanir.

CREATE DATABASE IF NOT EXISTS `buff_v126`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `buff_v126`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
-- =============================================================
-- SOURCE: 001_initial_schema.sql
-- =============================================================
-- BUFF DB TARGET: Bu dosya aktif/seçilmiş veritabanında çalışır. Önerilen yöntem: /install.php veya VERITABANI_ONAR.bat.
CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(24) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(60) NOT NULL,
  `avatar_url` VARCHAR(255) DEFAULT NULL,
  `bio` VARCHAR(255) DEFAULT '',
  `wallet_balance` INT UNSIGNED NOT NULL DEFAULT 2500,
  `total_gifts_received` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `streams` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(160) NOT NULL,
  `category` VARCHAR(80) NOT NULL,
  `description` VARCHAR(300) DEFAULT '',
  `language` VARCHAR(10) NOT NULL DEFAULT 'TR',
  `tags` VARCHAR(255) DEFAULT NULL,
  `is_live` TINYINT(1) NOT NULL DEFAULT 0,
  `gift_total` INT UNSIGNED NOT NULL DEFAULT 0,
  `started_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_streams_user` (`user_id`),
  KEY `idx_streams_live` (`is_live`, `updated_at`),
  CONSTRAINT `fk_streams_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stream_viewers` (
  `stream_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `last_seen_at` DATETIME NOT NULL,
  PRIMARY KEY (`stream_id`, `user_id`),
  CONSTRAINT `fk_stream_viewers_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stream_viewers_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gift_catalog` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(60) NOT NULL,
  `icon` VARCHAR(10) NOT NULL,
  `price` INT UNSIGNED NOT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gift_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `stream_id` BIGINT UNSIGNED NOT NULL,
  `sender_user_id` BIGINT UNSIGNED NOT NULL,
  `recipient_user_id` BIGINT UNSIGNED NOT NULL,
  `gift_id` BIGINT UNSIGNED NOT NULL,
  `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` INT UNSIGNED NOT NULL,
  `total_price` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gift_events_stream` (`stream_id`, `created_at`),
  CONSTRAINT `fk_gift_events_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gift_events_sender` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gift_events_recipient` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gift_events_gift` FOREIGN KEY (`gift_id`) REFERENCES `gift_catalog` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `amount` INT NOT NULL,
  `type` VARCHAR(40) NOT NULL,
  `description` VARCHAR(160) NOT NULL,
  `related_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wallet_user` (`user_id`, `created_at`),
  CONSTRAINT `fk_wallet_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wallet_related_user` FOREIGN KEY (`related_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `signal_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `stream_id` BIGINT UNSIGNED NOT NULL,
  `sender_user_id` BIGINT UNSIGNED NOT NULL,
  `recipient_user_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(30) NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `delivered` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_signal_poll` (`stream_id`, `recipient_user_id`, `delivered`, `id`),
  CONSTRAINT `fk_signal_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_signal_sender` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_signal_recipient` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gift_catalog` (`id`, `name`, `icon`, `price`, `sort_order`) VALUES
  (1, 'Kalp', '❤️', 50, 1),
  (2, 'Yıldız', '⭐', 120, 2),
  (3, 'Kahve', '☕', 250, 3),
  (4, 'Taç', '👑', 600, 4),
  (5, 'Roket', '🚀', 1200, 5)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `icon` = VALUES(`icon`),
  `price` = VALUES(`price`),
  `sort_order` = VALUES(`sort_order`);


-- =============================================================
-- SOURCE: 002_v3_features.sql
-- =============================================================
-- BUFF DB TARGET: Bu dosya aktif/seçilmiş veritabanında çalışır. Önerilen yöntem: /install.php veya VERITABANI_ONAR.bat.
-- BUFF V12.3: explicit database selection for direct phpMyAdmin import.
-- Logi V3 upgrade: takip, chat, bildirim ve yeni hediyeler

CREATE TABLE IF NOT EXISTS `follows` (
  `follower_user_id` BIGINT UNSIGNED NOT NULL,
  `followed_user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`follower_user_id`, `followed_user_id`),
  KEY `idx_follows_followed` (`followed_user_id`, `created_at`),
  CONSTRAINT `fk_follows_follower` FOREIGN KEY (`follower_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_follows_followed` FOREIGN KEY (`followed_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `stream_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `message` VARCHAR(300) NOT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  KEY `idx_chat_stream_id` (`stream_id`, `id`),
  CONSTRAINT `fk_chat_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `actor_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `type` VARCHAR(40) NOT NULL,
  `title` VARCHAR(120) NOT NULL,
  `body` VARCHAR(255) NOT NULL,
  `link_url` VARCHAR(255) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_read` (`user_id`, `is_read`, `id`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gift_catalog` (`id`, `name`, `icon`, `price`, `sort_order`) VALUES
  (6, 'Çiçek', '🌹', 90, 6),
  (7, 'Şimşek', '⚡', 350, 7),
  (8, 'Elmas', '💎', 900, 8),
  (9, 'Galaksi', '🌌', 2500, 9),
  (10, 'Ejderha', '🐉', 5000, 10)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `icon` = VALUES(`icon`),
  `price` = VALUES(`price`),
  `sort_order` = VALUES(`sort_order`);


-- =============================================================
-- SOURCE: 003_v4_community_payments.sql
-- =============================================================
-- BUFF DB TARGET: Bu dosya aktif/seçilmiş veritabanında çalışır. Önerilen yöntem: /install.php veya VERITABANI_ONAR.bat.
-- BUFF V12.3: explicit database selection for direct phpMyAdmin import.
-- Logi V4: moderasyon, VIP/OG, rapor, iade, banka tercihi ve çoklu dil altyapısı

CREATE TABLE IF NOT EXISTS `user_features` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `is_og` TINYINT(1) NOT NULL DEFAULT 0,
  `vip_until` DATETIME DEFAULT NULL,
  `chat_color` VARCHAR(20) NOT NULL DEFAULT '#E7ECFF',
  `preferred_language` VARCHAR(5) NOT NULL DEFAULT 'tr',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_user_features_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `user_features` (`user_id`, `is_og`)
SELECT `id`, IF(`id` <= 100, 1, 0) FROM `users`
ON DUPLICATE KEY UPDATE `user_id` = VALUES(`user_id`);

CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `account_holder` VARCHAR(120) NOT NULL,
  `iban_encrypted` TEXT NOT NULL,
  `iban_last4` VARCHAR(4) NOT NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bank_accounts_user` (`user_id`, `is_default`),
  CONSTRAINT `fk_bank_accounts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stream_payout_preferences` (
  `stream_id` BIGINT UNSIGNED NOT NULL,
  `payout_mode` VARCHAR(20) NOT NULL DEFAULT 'wallet',
  `bank_account_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`stream_id`),
  CONSTRAINT `fk_stream_payout_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stream_payout_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stream_moderators` (
  `streamer_user_id` BIGINT UNSIGNED NOT NULL,
  `moderator_user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`streamer_user_id`, `moderator_user_id`),
  CONSTRAINT `fk_stream_mod_owner` FOREIGN KEY (`streamer_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stream_mod_user` FOREIGN KEY (`moderator_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_restrictions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `stream_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `restriction_type` VARCHAR(20) NOT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `created_by_user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_restriction_active` (`stream_id`, `user_id`, `restriction_type`, `expires_at`),
  CONSTRAINT `fk_chat_restrict_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_restrict_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_chat_restrict_actor` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stream_chat_settings` (
  `stream_id` BIGINT UNSIGNED NOT NULL,
  `slow_mode_seconds` INT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`stream_id`),
  CONSTRAINT `fk_stream_chat_settings_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_message_meta` (
  `message_id` BIGINT UNSIGNED NOT NULL,
  `message_type` VARCHAR(20) NOT NULL DEFAULT 'text',
  `sticker_code` VARCHAR(40) DEFAULT NULL,
  `chat_color` VARCHAR(20) DEFAULT NULL,
  PRIMARY KEY (`message_id`),
  CONSTRAINT `fk_chat_meta_message` FOREIGN KEY (`message_id`) REFERENCES `chat_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reports` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reporter_user_id` BIGINT UNSIGNED NOT NULL,
  `target_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `stream_id` BIGINT UNSIGNED DEFAULT NULL,
  `category` VARCHAR(50) NOT NULL,
  `description` VARCHAR(500) NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'open',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reports_reporter` (`reporter_user_id`, `status`, `id`),
  CONSTRAINT `fk_reports_reporter` FOREIGN KEY (`reporter_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reports_target` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reports_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gift_settlements` (
  `gift_event_id` BIGINT UNSIGNED NOT NULL,
  `recipient_user_id` BIGINT UNSIGNED NOT NULL,
  `amount` INT UNSIGNED NOT NULL,
  `destination_mode` VARCHAR(20) NOT NULL DEFAULT 'wallet',
  `bank_account_id` BIGINT UNSIGNED DEFAULT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'wallet_credited',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`gift_event_id`),
  KEY `idx_gift_settlement_recipient` (`recipient_user_id`, `status`),
  CONSTRAINT `fk_gift_settlement_event` FOREIGN KEY (`gift_event_id`) REFERENCES `gift_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gift_settlement_recipient` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gift_settlement_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `gift_settlements` (`gift_event_id`, `recipient_user_id`, `amount`, `destination_mode`, `status`)
SELECT ge.`id`, ge.`recipient_user_id`, ge.`total_price`, 'wallet', 'wallet_credited'
FROM `gift_events` ge
LEFT JOIN `gift_settlements` gs ON gs.`gift_event_id` = ge.`id`
WHERE gs.`gift_event_id` IS NULL;

CREATE TABLE IF NOT EXISTS `gift_refund_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `gift_event_id` BIGINT UNSIGNED NOT NULL,
  `requester_user_id` BIGINT UNSIGNED NOT NULL,
  `recipient_user_id` BIGINT UNSIGNED NOT NULL,
  `reason` VARCHAR(500) NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `response_note` VARCHAR(500) DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_refund_gift_requester` (`gift_event_id`, `requester_user_id`),
  KEY `idx_refund_recipient_status` (`recipient_user_id`, `status`, `id`),
  CONSTRAINT `fk_refund_gift_event` FOREIGN KEY (`gift_event_id`) REFERENCES `gift_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_refund_requester` FOREIGN KEY (`requester_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_refund_recipient` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bank_payment_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `coin_amount` INT UNSIGNED NOT NULL,
  `money_amount` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(5) NOT NULL DEFAULT 'TRY',
  `sender_account_holder` VARCHAR(120) NOT NULL,
  `sender_iban_encrypted` TEXT NOT NULL,
  `sender_iban_last4` VARCHAR(4) NOT NULL,
  `reference_code` VARCHAR(24) NOT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'pending_verification',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_payment_reference` (`reference_code`),
  KEY `idx_bank_payment_user` (`user_id`, `status`, `id`),
  CONSTRAINT `fk_bank_payment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================
-- SOURCE: 004_v5_professional_ux.sql
-- =============================================================
-- BUFF DB TARGET: Bu dosya aktif/seçilmiş veritabanında çalışır. Önerilen yöntem: /install.php veya VERITABANI_ONAR.bat.
-- BUFF V12.3: can be imported directly in phpMyAdmin or executed by the installer.
-- BUFF V12.2 repaired migration: 004_v5_professional_ux.sql;

-- Safe for re-run on MySQL/MariaDB; existing columns/indexes are skipped.;


SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gift_events' AND COLUMN_NAME='audio_url')=0, 'ALTER TABLE `gift_events`
  ADD COLUMN `audio_url` VARCHAR(255) DEFAULT NULL AFTER `total_price`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gift_events' AND COLUMN_NAME='audio_name')=0, 'ALTER TABLE `gift_events`
  ADD COLUMN `audio_name` VARCHAR(120) DEFAULT NULL AFTER `audio_url`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

CREATE TABLE IF NOT EXISTS `feed_posts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `body` VARCHAR(1000) NOT NULL,
  `image_url` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_feed_posts_created` (`created_at`, `id`),
  CONSTRAINT `fk_feed_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `feed_post_likes` (
  `post_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`post_id`, `user_id`),
  CONSTRAINT `fk_feed_post_likes_post` FOREIGN KEY (`post_id`) REFERENCES `feed_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_feed_post_likes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================
-- SOURCE: 005_v6_settings_finance.sql
-- =============================================================
-- BUFF DB TARGET: Bu dosya aktif/seçilmiş veritabanında çalışır. Önerilen yöntem: /install.php veya VERITABANI_ONAR.bat.
-- BUFF V12.3: can be imported directly in phpMyAdmin or executed by the installer.
-- BUFF V12.2 repaired migration: 005_v6_settings_finance.sql;

-- Safe for re-run on MySQL/MariaDB; existing columns/indexes are skipped.;


SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='creator_earnings')=0, 'ALTER TABLE `users`
  ADD COLUMN `creator_earnings` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `wallet_balance`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='username_changed_at')=0, 'ALTER TABLE `users`
  ADD COLUMN `username_changed_at` DATETIME DEFAULT NULL AFTER `bio`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='streams' AND COLUMN_NAME='delay_seconds')=0, 'ALTER TABLE `streams`
  ADD COLUMN `delay_seconds` INT UNSIGNED NOT NULL DEFAULT 5 AFTER `language`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_features' AND COLUMN_NAME='vip_tier')=0, 'ALTER TABLE `user_features`
  ADD COLUMN `vip_tier` VARCHAR(20) DEFAULT NULL AFTER `vip_until`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_features' AND COLUMN_NAME='accent_theme')=0, 'ALTER TABLE `user_features`
  ADD COLUMN `accent_theme` VARCHAR(20) NOT NULL DEFAULT ''buff'' AFTER `preferred_language`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

CREATE TABLE IF NOT EXISTS `user_preferences` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `theme_mode` VARCHAR(12) NOT NULL DEFAULT 'dark',
  `accent_theme` VARCHAR(20) NOT NULL DEFAULT 'buff',
  `sidebar_collapsed` TINYINT(1) NOT NULL DEFAULT 0,
  `default_stream_delay` INT UNSIGNED NOT NULL DEFAULT 5,
  `reduce_motion` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_user_preferences_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `user_preferences` (`user_id`, `accent_theme`)
SELECT `id`, 'buff' FROM `users`;

CREATE TABLE IF NOT EXISTS `payout_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `bank_account_id` BIGINT UNSIGNED NOT NULL,
  `earning_coins` BIGINT UNSIGNED NOT NULL,
  `money_amount` DECIMAL(12,2) NOT NULL,
  `currency` VARCHAR(5) NOT NULL DEFAULT 'TRY',
  `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
  `reference_code` VARCHAR(32) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payout_reference` (`reference_code`),
  KEY `idx_payout_user_status` (`user_id`,`status`,`id`),
  CONSTRAINT `fk_payout_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payout_bank` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `ticket_type` VARCHAR(30) NOT NULL,
  `subject` VARCHAR(140) NOT NULL,
  `message` VARCHAR(2000) NOT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'open',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_support_user_status` (`user_id`,`status`,`id`),
  CONSTRAINT `fk_support_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `username_change_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `old_username` VARCHAR(24) NOT NULL,
  `new_username` VARCHAR(24) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_username_history_user` (`user_id`,`created_at`),
  CONSTRAINT `fk_username_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================
-- SOURCE: 006_v7_accounts_social.sql
-- =============================================================
-- BUFF DB TARGET: Bu dosya aktif/seçilmiş veritabanında çalışır. Önerilen yöntem: /install.php veya VERITABANI_ONAR.bat.
-- BUFF V12.3: can be imported directly in phpMyAdmin or executed by the installer.
-- BUFF V12.2 repaired migration: 006_v7_accounts_social.sql;

-- Safe for re-run on MySQL/MariaDB; existing columns/indexes are skipped.;


SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='account_mode')=0, 'ALTER TABLE `users`
  ADD COLUMN `account_mode` VARCHAR(20) NOT NULL DEFAULT ''viewer'' AFTER `bio`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='creator_enabled')=0, 'ALTER TABLE `users`
  ADD COLUMN `creator_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `account_mode`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

CREATE TABLE IF NOT EXISTS `feed_post_comments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `body` VARCHAR(600) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_feed_comments_post` (`post_id`,`id`),
  CONSTRAINT `fk_feed_comments_post` FOREIGN KEY (`post_id`) REFERENCES `feed_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_feed_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `feed_post_shares` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_feed_share_user_post` (`post_id`,`user_id`),
  KEY `idx_feed_shares_post` (`post_id`,`id`),
  CONSTRAINT `fk_feed_shares_post` FOREIGN KEY (`post_id`) REFERENCES `feed_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_feed_shares_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================
-- SOURCE: 007_v8_stream_content_ui.sql
-- =============================================================
-- BUFF DB TARGET: Bu dosya aktif/seçilmiş veritabanında çalışır. Önerilen yöntem: /install.php veya VERITABANI_ONAR.bat.
-- BUFF V12.3: can be imported directly in phpMyAdmin or executed by the installer.
-- BUFF V12.2 repaired migration: 007_v8_stream_content_ui.sql;

-- Safe for re-run on MySQL/MariaDB; existing columns/indexes are skipped.;


SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='streams' AND COLUMN_NAME='adult_only')=0, 'ALTER TABLE `streams`
  ADD COLUMN `adult_only` TINYINT(1) NOT NULL DEFAULT 0 AFTER `delay_seconds`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='streams' AND COLUMN_NAME='mature_language')=0, 'ALTER TABLE `streams`
  ADD COLUMN `mature_language` TINYINT(1) NOT NULL DEFAULT 0 AFTER `adult_only`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;


-- =============================================================
-- SOURCE: 008_v9_real_payments.sql
-- =============================================================
-- BUFF DB TARGET: Bu dosya aktif/seçilmiş veritabanında çalışır. Önerilen yöntem: /install.php veya VERITABANI_ONAR.bat.
-- BUFF V12.3: explicit database selection for direct phpMyAdmin import.
CREATE TABLE IF NOT EXISTS `payment_orders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `provider` VARCHAR(30) NOT NULL DEFAULT 'paytr',
  `merchant_oid` VARCHAR(64) NOT NULL,
  `coin_amount` INT UNSIGNED NOT NULL,
  `amount_kurus` INT UNSIGNED NOT NULL,
  `currency` VARCHAR(5) NOT NULL DEFAULT 'TL',
  `status` VARCHAR(30) NOT NULL DEFAULT 'created',
  `provider_reference` VARCHAR(190) DEFAULT NULL,
  `failure_reason` VARCHAR(255) DEFAULT NULL,
  `callback_payload` LONGTEXT DEFAULT NULL,
  `paid_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_orders_oid` (`merchant_oid`),
  KEY `idx_payment_orders_user` (`user_id`,`status`,`id`),
  CONSTRAINT `fk_payment_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================
-- SOURCE: 009_v10_reports_schema_repair.sql
-- =============================================================
-- BUFF DB TARGET: Bu dosya aktif/seçilmiş veritabanında çalışır. Önerilen yöntem: /install.php veya VERITABANI_ONAR.bat.
-- BUFF V12.3: can be imported directly in phpMyAdmin or executed by the installer.
-- BUFF V12.2 repaired migration: 009_v10_reports_schema_repair.sql;

-- Safe for re-run on MySQL/MariaDB; existing columns/indexes are skipped.;


SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reports' AND COLUMN_NAME='report_scope')=0, 'ALTER TABLE `reports`
  ADD COLUMN `report_scope` VARCHAR(20) NOT NULL DEFAULT ''stream'' AFTER `stream_id`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reports' AND COLUMN_NAME='target_username_snapshot')=0, 'ALTER TABLE `reports`
  ADD COLUMN `target_username_snapshot` VARCHAR(60) DEFAULT NULL AFTER `report_scope`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reports' AND COLUMN_NAME='stream_title_snapshot')=0, 'ALTER TABLE `reports`
  ADD COLUMN `stream_title_snapshot` VARCHAR(160) DEFAULT NULL AFTER `target_username_snapshot`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reports' AND COLUMN_NAME='resolved_at')=0, 'ALTER TABLE `reports`
  ADD COLUMN `resolved_at` DATETIME DEFAULT NULL AFTER `status`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reports' AND INDEX_NAME='idx_reports_target_status')=0, 'ALTER TABLE `reports`
  ADD INDEX `idx_reports_target_status` (`target_user_id`,`status`,`id`)', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;


-- =============================================================
-- SOURCE: 010_v11_replays_membership.sql
-- =============================================================
-- BUFF DB TARGET: Bu dosya aktif/seçilmiş veritabanında çalışır. Önerilen yöntem: /install.php veya VERITABANI_ONAR.bat.
-- BUFF V12.3: can be imported directly in phpMyAdmin or executed by the installer.
-- BUFF V12.2 repaired migration: 010_v11_replays_membership.sql;

-- Safe for re-run on MySQL/MariaDB; existing columns/indexes are skipped.;


SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='streams' AND COLUMN_NAME='ended_at')=0, 'ALTER TABLE `streams` ADD COLUMN `ended_at` DATETIME NULL AFTER `started_at`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payment_orders' AND COLUMN_NAME='order_type')=0, 'ALTER TABLE `payment_orders` ADD COLUMN `order_type` VARCHAR(30) NOT NULL DEFAULT ''coin'' AFTER `provider`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='payment_orders' AND COLUMN_NAME='order_meta_json')=0, 'ALTER TABLE `payment_orders` ADD COLUMN `order_meta_json` LONGTEXT NULL AFTER `coin_amount`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;


-- =============================================================
-- SOURCE: 011_v12_platform.sql
-- =============================================================
-- BUFF DB TARGET: Bu dosya aktif/seçilmiş veritabanında çalışır. Önerilen yöntem: /install.php veya VERITABANI_ONAR.bat.
-- BUFF V12.3: can be imported directly in phpMyAdmin or executed by the installer.
-- BUFF V12.2 repaired migration: 011_v12_platform.sql;

-- Safe for re-run on MySQL/MariaDB; existing columns/indexes are skipped.;


SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='is_admin')=0, 'ALTER TABLE `users` ADD COLUMN `is_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `creator_enabled`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='account_status')=0, 'ALTER TABLE `users` ADD COLUMN `account_status` VARCHAR(20) NOT NULL DEFAULT ''active'' AFTER `is_admin`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='email_changed_at')=0, 'ALTER TABLE `users` ADD COLUMN `email_changed_at` DATETIME NULL AFTER `username_changed_at`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='feed_posts' AND COLUMN_NAME='video_url')=0, 'ALTER TABLE `feed_posts` ADD COLUMN `video_url` VARCHAR(255) NULL AFTER `image_url`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='feed_posts' AND COLUMN_NAME='poll_json')=0, 'ALTER TABLE `feed_posts` ADD COLUMN `poll_json` LONGTEXT NULL AFTER `video_url`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='feed_posts' AND COLUMN_NAME='is_pinned')=0, 'ALTER TABLE `feed_posts` ADD COLUMN `is_pinned` TINYINT(1) NOT NULL DEFAULT 0 AFTER `poll_json`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='feed_post_comments' AND COLUMN_NAME='parent_comment_id')=0, 'ALTER TABLE `feed_post_comments` ADD COLUMN `parent_comment_id` BIGINT UNSIGNED NULL AFTER `user_id`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

CREATE TABLE IF NOT EXISTS `channel_profiles` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `banner_url` VARCHAR(255) NULL,
  `channel_bio` VARCHAR(500) NOT NULL DEFAULT '',
  `social_json` LONGTEXT NULL,
  `premium_layout` VARCHAR(30) NOT NULL DEFAULT 'standard',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_channel_profile_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `channel_schedule` (
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
  KEY `idx_channel_schedule_user` (`user_id`,`weekday`,`start_time`),
  CONSTRAINT `fk_channel_schedule_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recordings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `stream_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(160) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_url` VARCHAR(255) NOT NULL,
  `thumbnail_path` VARCHAR(255) NULL,
  `thumbnail_url` VARCHAR(255) NULL,
  `duration_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
  `visibility` VARCHAR(20) NOT NULL DEFAULT 'public',
  `status` VARCHAR(20) NOT NULL DEFAULT 'ready',
  `view_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_recordings_user_created` (`user_id`,`created_at`),
  KEY `idx_recordings_stream` (`stream_id`),
  CONSTRAINT `fk_recordings_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_recordings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `clips` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `stream_id` BIGINT UNSIGNED NOT NULL,
  `recording_id` BIGINT UNSIGNED NULL,
  `created_by_user_id` BIGINT UNSIGNED NOT NULL,
  `streamer_user_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(160) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_url` VARCHAR(255) NOT NULL,
  `thumbnail_path` VARCHAR(255) NULL,
  `thumbnail_url` VARCHAR(255) NULL,
  `duration_seconds` INT UNSIGNED NOT NULL DEFAULT 30,
  `visibility` VARCHAR(20) NOT NULL DEFAULT 'public',
  `view_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_clips_streamer_created` (`streamer_user_id`,`created_at`),
  KEY `idx_clips_stream` (`stream_id`),
  CONSTRAINT `fk_clips_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_clips_recording` FOREIGN KEY (`recording_id`) REFERENCES `recordings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_clips_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_clips_streamer` FOREIGN KEY (`streamer_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(80) NOT NULL,
  `slug` VARCHAR(90) NOT NULL,
  `icon` VARCHAR(12) NOT NULL DEFAULT '✦',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`name`,`slug`,`icon`,`sort_order`) VALUES
('Sohbet','sohbet','💬',1),('Oyun','oyun','🎮',2),('Müzik','muzik','♫',3),('E-spor','e-spor','🏆',4),('Sanat','sanat','🎨',5),('Mobil','mobil','📱',6)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`),`icon`=VALUES(`icon`),`sort_order`=VALUES(`sort_order`);

CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `session_key` VARCHAR(128) NOT NULL,
  `ip_address` VARCHAR(64) NULL,
  `user_agent` VARCHAR(255) NULL,
  `last_seen_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_session_key` (`session_key`),
  KEY `idx_user_sessions_user` (`user_id`,`last_seen_at`),
  CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `saved_posts` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `post_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`post_id`),
  CONSTRAINT `fk_saved_post_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_saved_post_post` FOREIGN KEY (`post_id`) REFERENCES `feed_posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_user_id` BIGINT UNSIGNED NOT NULL,
  `action_name` VARCHAR(80) NOT NULL,
  `target_type` VARCHAR(40) NULL,
  `target_id` BIGINT UNSIGNED NULL,
  `details_json` LONGTEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_logs_created` (`created_at`),
  CONSTRAINT `fk_admin_log_user` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE user_preferences MODIFY COLUMN theme_mode VARCHAR(12) NOT NULL DEFAULT 'dark';

ALTER TABLE user_preferences MODIFY COLUMN accent_theme VARCHAR(24) NOT NULL DEFAULT 'buff';


-- =============================================================
-- SOURCE: 012_v12_final_admin_reports.sql
-- =============================================================
-- BUFF DB TARGET: Bu dosya aktif/seçilmiş veritabanında çalışır. Önerilen yöntem: /install.php veya VERITABANI_ONAR.bat.
-- BUFF V12.3: can be imported directly in phpMyAdmin or executed by the installer.
-- BUFF V12.2 repaired migration: 012_v12_final_admin_reports.sql;

-- Safe for re-run on MySQL/MariaDB; existing columns/indexes are skipped.;


SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reports' AND COLUMN_NAME='chat_message_id')=0, 'ALTER TABLE `reports` ADD COLUMN `chat_message_id` BIGINT UNSIGNED NULL AFTER `stream_id`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reports' AND COLUMN_NAME='feed_post_id')=0, 'ALTER TABLE `reports` ADD COLUMN `feed_post_id` BIGINT UNSIGNED NULL AFTER `chat_message_id`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reports' AND COLUMN_NAME='target_snapshot')=0, 'ALTER TABLE `reports` ADD COLUMN `target_snapshot` VARCHAR(255) NULL AFTER `stream_title_snapshot`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reports' AND INDEX_NAME='idx_reports_chat_message')=0, 'ALTER TABLE `reports` ADD INDEX `idx_reports_chat_message` (`chat_message_id`)', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reports' AND INDEX_NAME='idx_reports_feed_post')=0, 'ALTER TABLE `reports` ADD INDEX `idx_reports_feed_post` (`feed_post_id`)', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

CREATE TABLE IF NOT EXISTS `site_settings` (
  `setting_key` VARCHAR(80) NOT NULL,
  `setting_value` LONGTEXT NULL,
  `setting_type` VARCHAR(20) NOT NULL DEFAULT 'string',
  `is_public` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_by_user_id` BIGINT UNSIGNED NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`),
  CONSTRAINT `fk_site_settings_admin` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `site_settings` (`setting_key`,`setting_value`,`setting_type`,`is_public`) VALUES
('site_name','BUFF','string',1),
('maintenance_mode','0','boolean',1),
('registration_enabled','1','boolean',1),
('default_language','tr','string',1),
('support_email','','string',0),
('max_upload_mb','700','integer',0)
ON DUPLICATE KEY UPDATE `setting_key`=VALUES(`setting_key`);


-- =============================================================
-- SOURCE: 013_v12_2_cards_contact.sql
-- =============================================================
-- BUFF DB TARGET: Bu dosya aktif/seçilmiş veritabanında çalışır. Önerilen yöntem: /install.php veya VERITABANI_ONAR.bat.
-- BUFF V12.3: can be imported directly in phpMyAdmin or executed by the installer.
-- BUFF V12.2 payment/contact migration;

-- Re-runnable: existing columns are skipped; full card number/CVV are never stored.;


SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='phone')=0, 'ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(24) NULL AFTER `email`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='billing_address')=0, 'ALTER TABLE `users` ADD COLUMN `billing_address` VARCHAR(400) NULL AFTER `phone`', 'SELECT 1');

PREPARE buff_stmt FROM @buff_sql;

EXECUTE buff_stmt;

DEALLOCATE PREPARE buff_stmt;

CREATE TABLE IF NOT EXISTS `payment_methods` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `provider` VARCHAR(30) NOT NULL DEFAULT 'local-token',
  `provider_token` VARCHAR(190) NOT NULL,
  `card_holder` VARCHAR(120) NOT NULL,
  `brand` VARCHAR(30) NOT NULL DEFAULT 'card',
  `last4` VARCHAR(4) NOT NULL,
  `exp_month` TINYINT UNSIGNED NOT NULL,
  `exp_year` SMALLINT UNSIGNED NOT NULL,
  `phone` VARCHAR(24) NULL,
  `billing_address` VARCHAR(400) NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_method_token` (`provider_token`),
  KEY `idx_payment_methods_user_default` (`user_id`,`is_default`,`id`),
  CONSTRAINT `fk_payment_methods_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- V12.3 fallback site_settings: keeps this migration standalone if 012 was skipped.
CREATE TABLE IF NOT EXISTS `site_settings` (
  `setting_key` VARCHAR(80) NOT NULL,
  `setting_value` LONGTEXT NULL,
  `setting_type` VARCHAR(20) NOT NULL DEFAULT 'string',
  `is_public` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_by_user_id` BIGINT UNSIGNED NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`),
  CONSTRAINT `fk_site_settings_admin_v13` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `site_settings` (`setting_key`,`setting_value`,`setting_type`,`is_public`) VALUES
('site_name','BUFF','string',1)
ON DUPLICATE KEY UPDATE `setting_value`=VALUES(`setting_value`), `setting_type`=VALUES(`setting_type`), `is_public`=VALUES(`is_public`);


-- =============================================================
-- SOURCE: 014_v12_3_stream_runtime_repair.sql
-- =============================================================
-- BUFF DB TARGET: Bu dosya aktif/seçilmiş veritabanında çalışır. Önerilen yöntem: /install.php veya VERITABANI_ONAR.bat.
-- BUFF V12.3 critical runtime repair.
-- This migration is intentionally non-destructive and can be re-run.
SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='account_mode')=0,
  'ALTER TABLE `users` ADD COLUMN `account_mode` VARCHAR(20) NOT NULL DEFAULT ''viewer''', 'SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='creator_enabled')=0,
  'ALTER TABLE `users` ADD COLUMN `creator_enabled` TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='streams' AND COLUMN_NAME='delay_seconds')=0,
  'ALTER TABLE `streams` ADD COLUMN `delay_seconds` INT UNSIGNED NOT NULL DEFAULT 5', 'SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='streams' AND COLUMN_NAME='adult_only')=0,
  'ALTER TABLE `streams` ADD COLUMN `adult_only` TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='streams' AND COLUMN_NAME='mature_language')=0,
  'ALTER TABLE `streams` ADD COLUMN `mature_language` TINYINT(1) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='streams' AND COLUMN_NAME='ended_at')=0,
  'ALTER TABLE `streams` ADD COLUMN `ended_at` DATETIME NULL', 'SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;

CREATE TABLE IF NOT EXISTS `user_preferences` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `theme_mode` VARCHAR(12) NOT NULL DEFAULT 'dark',
  `accent_theme` VARCHAR(24) NOT NULL DEFAULT 'buff',
  `sidebar_collapsed` TINYINT(1) NOT NULL DEFAULT 0,
  `default_stream_delay` INT UNSIGNED NOT NULL DEFAULT 5,
  `reduce_motion` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_user_preferences_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stream_chat_settings` (
  `stream_id` BIGINT UNSIGNED NOT NULL,
  `slow_mode_seconds` INT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`stream_id`),
  CONSTRAINT `fk_stream_chat_settings_stream` FOREIGN KEY (`stream_id`) REFERENCES `streams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `user_preferences` (`user_id`, `theme_mode`, `accent_theme`)
SELECT `id`, 'dark', 'buff' FROM `users`;

ALTER TABLE `user_preferences` MODIFY COLUMN `accent_theme` VARCHAR(24) NOT NULL DEFAULT 'buff';
UPDATE `user_preferences` SET `accent_theme`='buff' WHERE `accent_theme` IN ('indigo','logi','green');


-- =============================================================
-- SOURCE: 015_v12_4_stream_fail_safe.sql
-- =============================================================
-- BUFF DB TARGET: Bu dosya aktif/seçilmiş veritabanında çalışır. Önerilen yöntem: /install.php veya VERITABANI_ONAR.bat.
-- BUFF V12.5 compatibility repair for the old V12.4 stream fail-safe migration.
-- Safe to import on an empty OR partially installed database. Existing data is not deleted.
CREATE TABLE IF NOT EXISTS `users` (
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
  PRIMARY KEY (`id`), UNIQUE KEY `uq_users_username` (`username`), UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='account_mode')=0,'ALTER TABLE `users` ADD COLUMN `account_mode` VARCHAR(20) NOT NULL DEFAULT ''viewer''','SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;
SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='creator_enabled')=0,'ALTER TABLE `users` ADD COLUMN `creator_enabled` TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;
SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='creator_earnings')=0,'ALTER TABLE `users` ADD COLUMN `creator_earnings` BIGINT UNSIGNED NOT NULL DEFAULT 0','SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;
SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='is_admin')=0,'ALTER TABLE `users` ADD COLUMN `is_admin` TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;
SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='account_status')=0,'ALTER TABLE `users` ADD COLUMN `account_status` VARCHAR(20) NOT NULL DEFAULT ''active''','SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;
SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='phone')=0,'ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(32) NULL','SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;
SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='billing_address')=0,'ALTER TABLE `users` ADD COLUMN `billing_address` VARCHAR(500) NULL','SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;

CREATE TABLE IF NOT EXISTS `streams` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(160) NOT NULL DEFAULT '', `category` VARCHAR(80) NOT NULL DEFAULT '', `description` VARCHAR(300) NOT NULL DEFAULT '',
  `language` VARCHAR(10) NOT NULL DEFAULT 'TR', `tags` VARCHAR(255) DEFAULT NULL, `is_live` TINYINT(1) NOT NULL DEFAULT 0,
  `gift_total` BIGINT UNSIGNED NOT NULL DEFAULT 0, `delay_seconds` INT UNSIGNED NOT NULL DEFAULT 5,
  `adult_only` TINYINT(1) NOT NULL DEFAULT 0, `mature_language` TINYINT(1) NOT NULL DEFAULT 0,
  `started_at` DATETIME DEFAULT NULL, `ended_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_streams_user` (`user_id`), KEY `idx_streams_live` (`is_live`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='streams' AND COLUMN_NAME='delay_seconds')=0,'ALTER TABLE `streams` ADD COLUMN `delay_seconds` INT UNSIGNED NOT NULL DEFAULT 5','SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;
SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='streams' AND COLUMN_NAME='adult_only')=0,'ALTER TABLE `streams` ADD COLUMN `adult_only` TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;
SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='streams' AND COLUMN_NAME='mature_language')=0,'ALTER TABLE `streams` ADD COLUMN `mature_language` TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;
SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='streams' AND COLUMN_NAME='ended_at')=0,'ALTER TABLE `streams` ADD COLUMN `ended_at` DATETIME NULL','SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;

CREATE TABLE IF NOT EXISTS `user_features` (
  `user_id` BIGINT UNSIGNED NOT NULL, `is_og` TINYINT(1) NOT NULL DEFAULT 0, `vip_until` DATETIME DEFAULT NULL,
  `vip_tier` VARCHAR(20) DEFAULT NULL, `chat_color` VARCHAR(20) NOT NULL DEFAULT '#E7ECFF', `preferred_language` VARCHAR(5) NOT NULL DEFAULT 'tr',
  `accent_theme` VARCHAR(24) NOT NULL DEFAULT 'buff', `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_preferences` (
  `user_id` BIGINT UNSIGNED NOT NULL, `theme_mode` VARCHAR(12) NOT NULL DEFAULT 'dark', `accent_theme` VARCHAR(24) NOT NULL DEFAULT 'buff',
  `sidebar_collapsed` TINYINT(1) NOT NULL DEFAULT 0, `default_stream_delay` INT UNSIGNED NOT NULL DEFAULT 5, `reduce_motion` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stream_viewers` (`stream_id` BIGINT UNSIGNED NOT NULL,`user_id` BIGINT UNSIGNED NOT NULL,`last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`stream_id`,`user_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `signal_messages` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`stream_id` BIGINT UNSIGNED NOT NULL,`sender_user_id` BIGINT UNSIGNED NOT NULL,`recipient_user_id` BIGINT UNSIGNED NOT NULL,`type` VARCHAR(30) NOT NULL,`payload` LONGTEXT NOT NULL,`delivered` TINYINT(1) NOT NULL DEFAULT 0,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),KEY `idx_signal_poll` (`stream_id`,`recipient_user_id`,`delivered`,`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `chat_messages` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`stream_id` BIGINT UNSIGNED NOT NULL,`user_id` BIGINT UNSIGNED NOT NULL,`message` VARCHAR(300) NOT NULL,`created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),PRIMARY KEY (`id`),KEY `idx_chat_stream_id` (`stream_id`,`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `stream_chat_settings` (`stream_id` BIGINT UNSIGNED NOT NULL,`slow_mode_seconds` INT UNSIGNED NOT NULL DEFAULT 1,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`stream_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `user_features` (`user_id`) SELECT `id` FROM `users`;
INSERT IGNORE INTO `user_preferences` (`user_id`) SELECT `id` FROM `users`;


-- =============================================================
-- SOURCE: 016_v12_5_core_stream_repair.sql
-- =============================================================
-- BUFF DB TARGET: Bu dosya aktif/seçilmiş veritabanında çalışır. Önerilen yöntem: /install.php veya VERITABANI_ONAR.bat.
-- BUFF V12.5 compatibility repair for the old V12.4 stream fail-safe migration.
-- Safe to import on an empty OR partially installed database. Existing data is not deleted.
CREATE TABLE IF NOT EXISTS `users` (
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
  PRIMARY KEY (`id`), UNIQUE KEY `uq_users_username` (`username`), UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='account_mode')=0,'ALTER TABLE `users` ADD COLUMN `account_mode` VARCHAR(20) NOT NULL DEFAULT ''viewer''','SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;
SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='creator_enabled')=0,'ALTER TABLE `users` ADD COLUMN `creator_enabled` TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;
SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='creator_earnings')=0,'ALTER TABLE `users` ADD COLUMN `creator_earnings` BIGINT UNSIGNED NOT NULL DEFAULT 0','SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;
SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='is_admin')=0,'ALTER TABLE `users` ADD COLUMN `is_admin` TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;
SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='account_status')=0,'ALTER TABLE `users` ADD COLUMN `account_status` VARCHAR(20) NOT NULL DEFAULT ''active''','SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;
SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='phone')=0,'ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(32) NULL','SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;
SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='billing_address')=0,'ALTER TABLE `users` ADD COLUMN `billing_address` VARCHAR(500) NULL','SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;

CREATE TABLE IF NOT EXISTS `streams` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(160) NOT NULL DEFAULT '', `category` VARCHAR(80) NOT NULL DEFAULT '', `description` VARCHAR(300) NOT NULL DEFAULT '',
  `language` VARCHAR(10) NOT NULL DEFAULT 'TR', `tags` VARCHAR(255) DEFAULT NULL, `is_live` TINYINT(1) NOT NULL DEFAULT 0,
  `gift_total` BIGINT UNSIGNED NOT NULL DEFAULT 0, `delay_seconds` INT UNSIGNED NOT NULL DEFAULT 5,
  `adult_only` TINYINT(1) NOT NULL DEFAULT 0, `mature_language` TINYINT(1) NOT NULL DEFAULT 0,
  `started_at` DATETIME DEFAULT NULL, `ended_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_streams_user` (`user_id`), KEY `idx_streams_live` (`is_live`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='streams' AND COLUMN_NAME='delay_seconds')=0,'ALTER TABLE `streams` ADD COLUMN `delay_seconds` INT UNSIGNED NOT NULL DEFAULT 5','SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;
SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='streams' AND COLUMN_NAME='adult_only')=0,'ALTER TABLE `streams` ADD COLUMN `adult_only` TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;
SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='streams' AND COLUMN_NAME='mature_language')=0,'ALTER TABLE `streams` ADD COLUMN `mature_language` TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;
SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='streams' AND COLUMN_NAME='ended_at')=0,'ALTER TABLE `streams` ADD COLUMN `ended_at` DATETIME NULL','SELECT 1');
PREPARE buff_stmt FROM @buff_sql;
EXECUTE buff_stmt;
DEALLOCATE PREPARE buff_stmt;

CREATE TABLE IF NOT EXISTS `user_features` (
  `user_id` BIGINT UNSIGNED NOT NULL, `is_og` TINYINT(1) NOT NULL DEFAULT 0, `vip_until` DATETIME DEFAULT NULL,
  `vip_tier` VARCHAR(20) DEFAULT NULL, `chat_color` VARCHAR(20) NOT NULL DEFAULT '#E7ECFF', `preferred_language` VARCHAR(5) NOT NULL DEFAULT 'tr',
  `accent_theme` VARCHAR(24) NOT NULL DEFAULT 'buff', `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_preferences` (
  `user_id` BIGINT UNSIGNED NOT NULL, `theme_mode` VARCHAR(12) NOT NULL DEFAULT 'dark', `accent_theme` VARCHAR(24) NOT NULL DEFAULT 'buff',
  `sidebar_collapsed` TINYINT(1) NOT NULL DEFAULT 0, `default_stream_delay` INT UNSIGNED NOT NULL DEFAULT 5, `reduce_motion` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stream_viewers` (`stream_id` BIGINT UNSIGNED NOT NULL,`user_id` BIGINT UNSIGNED NOT NULL,`last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`stream_id`,`user_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `signal_messages` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`stream_id` BIGINT UNSIGNED NOT NULL,`sender_user_id` BIGINT UNSIGNED NOT NULL,`recipient_user_id` BIGINT UNSIGNED NOT NULL,`type` VARCHAR(30) NOT NULL,`payload` LONGTEXT NOT NULL,`delivered` TINYINT(1) NOT NULL DEFAULT 0,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),KEY `idx_signal_poll` (`stream_id`,`recipient_user_id`,`delivered`,`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `chat_messages` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`stream_id` BIGINT UNSIGNED NOT NULL,`user_id` BIGINT UNSIGNED NOT NULL,`message` VARCHAR(300) NOT NULL,`created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),PRIMARY KEY (`id`),KEY `idx_chat_stream_id` (`stream_id`,`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `stream_chat_settings` (`stream_id` BIGINT UNSIGNED NOT NULL,`slow_mode_seconds` INT UNSIGNED NOT NULL DEFAULT 1,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`stream_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `user_features` (`user_id`) SELECT `id` FROM `users`;
INSERT IGNORE INTO `user_preferences` (`user_id`) SELECT `id` FROM `users`;


SET FOREIGN_KEY_CHECKS = 1;

-- Kurulum dogrulama bilgisi
CREATE TABLE IF NOT EXISTS `buff_install_state` (
  `id` TINYINT UNSIGNED NOT NULL,
  `version` VARCHAR(20) NOT NULL,
  `installed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `buff_install_state` (`id`,`version`) VALUES (1,'14.3.0')
ON DUPLICATE KEY UPDATE `version`=VALUES(`version`), `updated_at`=CURRENT_TIMESTAMP;


-- V13.2 Admin RBAC core (safe/idempotent)
CREATE TABLE IF NOT EXISTS `admin_roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(40) NOT NULL,
  `name` VARCHAR(80) NOT NULL,
  `permissions_json` LONGTEXT DEFAULT NULL,
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_role_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_role_assignments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role_id` BIGINT UNSIGNED NOT NULL,
  `granted_by_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admin_role_assignment` (`user_id`,`role_id`),
  KEY `idx_admin_assignment_expiry` (`user_id`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `admin_roles` (`code`,`name`,`permissions_json`,`is_system`) VALUES
('super_admin','Süper Admin','["*"]',1),
('content_manager','İçerik Yöneticisi','["content.*","collections.*"]',1),
('editor','Editör','["content.read","content.write"]',1),
('support_agent','Müşteri Temsilcisi','["reports.*","support.*","users.read"]',1);
-- =============================================================
-- BUFF V14 FINAL PRODUCTION SCHEMA
-- =============================================================

CREATE TABLE IF NOT EXISTS `cms_titles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `content_type` VARCHAR(20) NOT NULL DEFAULT 'film',
  `title` VARCHAR(180) NOT NULL, `description` TEXT DEFAULT NULL, `genre` VARCHAR(120) DEFAULT NULL,
  `cast_text` TEXT DEFAULT NULL, `director` VARCHAR(160) DEFAULT NULL, `age_rating` VARCHAR(20) DEFAULT NULL,
  `poster_url` VARCHAR(500) DEFAULT NULL, `trailer_url` VARCHAR(500) DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'draft', `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_cms_titles_type_status` (`content_type`,`status`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `cms_assets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `title_id` BIGINT UNSIGNED DEFAULT NULL,
  `asset_type` VARCHAR(30) NOT NULL, `label` VARCHAR(140) DEFAULT NULL, `language_code` VARCHAR(12) DEFAULT NULL,
  `file_url` VARCHAR(700) NOT NULL, `mime_type` VARCHAR(100) DEFAULT NULL, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_cms_assets_title` (`title_id`,`asset_type`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `cms_seasons` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `title_id` BIGINT UNSIGNED NOT NULL, `season_number` INT NOT NULL,
  `name` VARCHAR(140) DEFAULT NULL, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_cms_season` (`title_id`,`season_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `cms_episodes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `season_id` BIGINT UNSIGNED NOT NULL, `episode_number` INT NOT NULL,
  `title` VARCHAR(180) NOT NULL, `description` TEXT DEFAULT NULL, `video_url` VARCHAR(700) DEFAULT NULL,
  `duration_seconds` INT NOT NULL DEFAULT 0, `status` VARCHAR(20) NOT NULL DEFAULT 'draft', `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_cms_episode` (`season_id`,`episode_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `cms_collections` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `name` VARCHAR(120) NOT NULL, `slug` VARCHAR(140) NOT NULL,
  `collection_type` VARCHAR(30) NOT NULL DEFAULT 'manual', `sort_order` INT NOT NULL DEFAULT 100,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1, `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_cms_collection_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `cms_collection_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `collection_id` BIGINT UNSIGNED NOT NULL, `title_id` BIGINT UNSIGNED NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 100, PRIMARY KEY (`id`), UNIQUE KEY `uq_collection_title` (`collection_id`,`title_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `membership_plans` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `code` VARCHAR(40) NOT NULL, `name` VARCHAR(100) NOT NULL,
  `price_kurus` INT NOT NULL DEFAULT 0, `billing_period` VARCHAR(20) NOT NULL DEFAULT 'monthly', `quality` VARCHAR(20) NOT NULL DEFAULT 'HD',
  `ads_enabled` TINYINT(1) NOT NULL DEFAULT 0, `family_slots` INT NOT NULL DEFAULT 1, `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_membership_plan_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO membership_plans (code,name,price_kurus,billing_period,quality,ads_enabled,family_slots,is_active) VALUES
('free','Ücretsiz',0,'monthly','HD',1,1,1),('plus','Plus',9900,'monthly','FHD',0,1,1),('pro','Pro',14900,'monthly','FHD',0,2,1),('premium','Premium',22900,'monthly','4K',0,5,1);
CREATE TABLE IF NOT EXISTS `stream_keys` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` BIGINT UNSIGNED NOT NULL, `stream_key` VARCHAR(96) NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'active', `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `last_used_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_stream_key_value` (`stream_key`), KEY `idx_stream_key_user` (`user_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `ip_blocks` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`ip_address` VARCHAR(64) NOT NULL,`reason` VARCHAR(255) DEFAULT NULL,`is_active` TINYINT(1) NOT NULL DEFAULT 1,`created_by_user_id` BIGINT UNSIGNED DEFAULT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `uq_ip_block_ip` (`ip_address`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `system_announcements` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`title` VARCHAR(140) NOT NULL,`body` VARCHAR(1000) NOT NULL,`channel` VARCHAR(20) NOT NULL DEFAULT 'in_app',`target_scope` VARCHAR(30) NOT NULL DEFAULT 'all',`is_active` TINYINT(1) NOT NULL DEFAULT 1,`created_by_user_id` BIGINT UNSIGNED DEFAULT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `epg_events` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`stream_id` BIGINT UNSIGNED DEFAULT NULL,`channel_name` VARCHAR(120) NOT NULL,`title` VARCHAR(180) NOT NULL,`start_at` DATETIME NOT NULL,`end_at` DATETIME NOT NULL,`status` VARCHAR(20) NOT NULL DEFAULT 'scheduled',`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),KEY `idx_epg_schedule` (`start_at`,`end_at`,`status`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `transcode_jobs` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`asset_id` BIGINT UNSIGNED DEFAULT NULL,`input_url` VARCHAR(700) NOT NULL,`output_profile` VARCHAR(80) NOT NULL DEFAULT 'HLS_AUTO',`status` VARCHAR(30) NOT NULL DEFAULT 'queued',`progress` INT NOT NULL DEFAULT 0,`error_message` VARCHAR(500) DEFAULT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `drm_policies` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`name` VARCHAR(120) NOT NULL,`widevine_enabled` TINYINT(1) NOT NULL DEFAULT 0,`fairplay_enabled` TINYINT(1) NOT NULL DEFAULT 0,`geo_mode` VARCHAR(20) NOT NULL DEFAULT 'off',`allowed_countries` VARCHAR(500) DEFAULT NULL,`blocked_countries` VARCHAR(500) DEFAULT NULL,`status` VARCHAR(20) NOT NULL DEFAULT 'draft',`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shared_sessions` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`user_id` BIGINT UNSIGNED NOT NULL,`jti` VARCHAR(80) NOT NULL,`token_hash` CHAR(64) NOT NULL,`expires_at` DATETIME NOT NULL,`revoked_at` DATETIME DEFAULT NULL,`ip_address` VARCHAR(64) DEFAULT NULL,`user_agent` VARCHAR(255) DEFAULT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `uq_shared_sessions_jti` (`jti`),KEY `idx_shared_sessions_user` (`user_id`,`expires_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `job_queue` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`queue_name` VARCHAR(50) NOT NULL DEFAULT 'default',`job_type` VARCHAR(80) NOT NULL,`payload_json` LONGTEXT NOT NULL,`status` VARCHAR(20) NOT NULL DEFAULT 'pending',`attempts` INT UNSIGNED NOT NULL DEFAULT 0,`available_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,`locked_at` DATETIME DEFAULT NULL,`locked_by` VARCHAR(100) DEFAULT NULL,`last_error` TEXT DEFAULT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`id`),KEY `idx_job_queue_pick` (`status`,`available_at`,`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `media_variants` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`owner_type` VARCHAR(30) NOT NULL,`owner_id` BIGINT UNSIGNED NOT NULL,`quality` VARCHAR(20) NOT NULL,`protocol` VARCHAR(12) NOT NULL,`file_url` VARCHAR(1000) NOT NULL,`storage_key` VARCHAR(500) DEFAULT NULL,`bitrate_kbps` INT UNSIGNED DEFAULT NULL,`width` INT UNSIGNED DEFAULT NULL,`height` INT UNSIGNED DEFAULT NULL,`status` VARCHAR(20) NOT NULL DEFAULT 'ready',`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `uq_media_variant` (`owner_type`,`owner_id`,`quality`,`protocol`),KEY `idx_media_owner` (`owner_type`,`owner_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `realtime_events` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`user_id` BIGINT UNSIGNED DEFAULT NULL,`stream_id` BIGINT UNSIGNED DEFAULT NULL,`topic` VARCHAR(100) NOT NULL,`event_type` VARCHAR(80) NOT NULL,`payload_json` LONGTEXT DEFAULT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),KEY `idx_realtime_user` (`user_id`,`id`),KEY `idx_realtime_stream` (`stream_id`,`id`),KEY `idx_realtime_topic` (`topic`,`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `finance_audit_logs` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`event_type` VARCHAR(80) NOT NULL,`ref_type` VARCHAR(50) DEFAULT NULL,`ref_id` BIGINT UNSIGNED DEFAULT NULL,`user_id` BIGINT UNSIGNED DEFAULT NULL,`payload_ciphertext` LONGTEXT NOT NULL,`payload_iv` VARCHAR(64) NOT NULL,`payload_tag` VARCHAR(64) NOT NULL,`prev_hash` CHAR(64) DEFAULT NULL,`row_hash` CHAR(64) NOT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),KEY `idx_finance_ref` (`ref_type`,`ref_id`),KEY `idx_finance_user` (`user_id`,`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `moderation_queue` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`entity_type` VARCHAR(40) NOT NULL,`entity_id` BIGINT UNSIGNED DEFAULT NULL,`user_id` BIGINT UNSIGNED DEFAULT NULL,`provider` VARCHAR(30) NOT NULL DEFAULT 'builtin',`status` VARCHAR(20) NOT NULL DEFAULT 'pending',`risk_score` DECIMAL(6,4) NOT NULL DEFAULT 0,`labels_json` LONGTEXT DEFAULT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,`reviewed_at` DATETIME DEFAULT NULL,PRIMARY KEY (`id`),KEY `idx_moderation_status` (`status`,`created_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `user_loyalty` (`user_id` BIGINT UNSIGNED NOT NULL,`points` BIGINT UNSIGNED NOT NULL DEFAULT 0,`xp` BIGINT UNSIGNED NOT NULL DEFAULT 0,`level` INT UNSIGNED NOT NULL DEFAULT 1,`daily_grant_date` DATE DEFAULT NULL,`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`user_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `channel_loyalty` (`user_id` BIGINT UNSIGNED NOT NULL,`channel_user_id` BIGINT UNSIGNED NOT NULL,`watch_seconds` BIGINT UNSIGNED NOT NULL DEFAULT 0,`message_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,`gift_amount` BIGINT UNSIGNED NOT NULL DEFAULT 0,`clip_count` BIGINT UNSIGNED NOT NULL DEFAULT 0,`xp` BIGINT UNSIGNED NOT NULL DEFAULT 0,`level` INT UNSIGNED NOT NULL DEFAULT 1,`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`user_id`,`channel_user_id`),KEY `idx_channel_loyalty_rank` (`channel_user_id`,`level`,`xp`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `stream_metric_samples` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`stream_id` BIGINT UNSIGNED NOT NULL,`creator_user_id` BIGINT UNSIGNED NOT NULL,`viewer_count` INT UNSIGNED NOT NULL DEFAULT 0,`chatter_count` INT UNSIGNED NOT NULL DEFAULT 0,`sampled_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),KEY `idx_stream_metric_creator` (`creator_user_id`,`sampled_at`),KEY `idx_stream_metric_stream` (`stream_id`,`sampled_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `predictions` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`stream_id` BIGINT UNSIGNED NOT NULL,`creator_user_id` BIGINT UNSIGNED NOT NULL,`question` VARCHAR(180) NOT NULL,`status` VARCHAR(20) NOT NULL DEFAULT 'open',`winning_option_id` BIGINT UNSIGNED DEFAULT NULL,`closes_at` DATETIME DEFAULT NULL,`resolved_at` DATETIME DEFAULT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),KEY `idx_prediction_stream` (`stream_id`,`status`,`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `prediction_options` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`prediction_id` BIGINT UNSIGNED NOT NULL,`label` VARCHAR(100) NOT NULL,`sort_order` INT UNSIGNED NOT NULL DEFAULT 0,PRIMARY KEY (`id`),KEY `idx_prediction_options` (`prediction_id`,`sort_order`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `prediction_entries` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`prediction_id` BIGINT UNSIGNED NOT NULL,`option_id` BIGINT UNSIGNED NOT NULL,`user_id` BIGINT UNSIGNED NOT NULL,`points` BIGINT UNSIGNED NOT NULL,`payout_points` BIGINT UNSIGNED NOT NULL DEFAULT 0,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `uq_prediction_user` (`prediction_id`,`user_id`),KEY `idx_prediction_entries_option` (`option_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `community_goals` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`stream_id` BIGINT UNSIGNED NOT NULL,`creator_user_id` BIGINT UNSIGNED NOT NULL,`title` VARCHAR(160) NOT NULL,`target_amount` BIGINT UNSIGNED NOT NULL,`current_amount` BIGINT UNSIGNED NOT NULL DEFAULT 0,`status` VARCHAR(20) NOT NULL DEFAULT 'active',`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,`completed_at` DATETIME DEFAULT NULL,PRIMARY KEY (`id`),KEY `idx_goal_stream` (`stream_id`,`status`,`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `content_interactions` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`user_id` BIGINT UNSIGNED NOT NULL,`channel_user_id` BIGINT UNSIGNED DEFAULT NULL,`content_type` VARCHAR(30) NOT NULL,`content_id` BIGINT UNSIGNED DEFAULT NULL,`interaction_type` VARCHAR(30) NOT NULL,`weight` DECIMAL(8,3) NOT NULL DEFAULT 1,`duration_seconds` INT UNSIGNED NOT NULL DEFAULT 0,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),KEY `idx_interactions_user` (`user_id`,`created_at`),KEY `idx_interactions_channel` (`channel_user_id`,`interaction_type`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `clip_gifts` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`clip_id` BIGINT UNSIGNED NOT NULL,`sender_user_id` BIGINT UNSIGNED NOT NULL,`streamer_user_id` BIGINT UNSIGNED NOT NULL,`clipper_user_id` BIGINT UNSIGNED NOT NULL,`amount` BIGINT UNSIGNED NOT NULL,`streamer_share` BIGINT UNSIGNED NOT NULL,`clipper_share` BIGINT UNSIGNED NOT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),KEY `idx_clip_gifts_clip` (`clip_id`,`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `api_telemetry` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`route` VARCHAR(190) NOT NULL,`method` VARCHAR(10) NOT NULL,`status_code` SMALLINT UNSIGNED NOT NULL,`duration_ms` DECIMAL(12,3) NOT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),KEY `idx_telemetry_route` (`route`,`created_at`),KEY `idx_telemetry_created` (`created_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='recordings' AND COLUMN_NAME='hls_url')=0,'ALTER TABLE `recordings` ADD COLUMN `hls_url` VARCHAR(1000) DEFAULT NULL','SELECT 1');
PREPARE buff_stmt FROM @buff_sql; EXECUTE buff_stmt; DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='recordings' AND COLUMN_NAME='dash_url')=0,'ALTER TABLE `recordings` ADD COLUMN `dash_url` VARCHAR(1000) DEFAULT NULL','SELECT 1');
PREPARE buff_stmt FROM @buff_sql; EXECUTE buff_stmt; DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='recordings' AND COLUMN_NAME='processing_status')=0,'ALTER TABLE `recordings` ADD COLUMN `processing_status` VARCHAR(20) NOT NULL DEFAULT ''ready''','SELECT 1');
PREPARE buff_stmt FROM @buff_sql; EXECUTE buff_stmt; DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='recordings' AND COLUMN_NAME='storage_key')=0,'ALTER TABLE `recordings` ADD COLUMN `storage_key` VARCHAR(500) DEFAULT NULL','SELECT 1');
PREPARE buff_stmt FROM @buff_sql; EXECUTE buff_stmt; DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='recordings' AND COLUMN_NAME='storage_provider')=0,'ALTER TABLE `recordings` ADD COLUMN `storage_provider` VARCHAR(30) NOT NULL DEFAULT ''local''','SELECT 1');
PREPARE buff_stmt FROM @buff_sql; EXECUTE buff_stmt; DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clips' AND COLUMN_NAME='hls_url')=0,'ALTER TABLE `clips` ADD COLUMN `hls_url` VARCHAR(1000) DEFAULT NULL','SELECT 1');
PREPARE buff_stmt FROM @buff_sql; EXECUTE buff_stmt; DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clips' AND COLUMN_NAME='dash_url')=0,'ALTER TABLE `clips` ADD COLUMN `dash_url` VARCHAR(1000) DEFAULT NULL','SELECT 1');
PREPARE buff_stmt FROM @buff_sql; EXECUTE buff_stmt; DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clips' AND COLUMN_NAME='processing_status')=0,'ALTER TABLE `clips` ADD COLUMN `processing_status` VARCHAR(20) NOT NULL DEFAULT ''ready''','SELECT 1');
PREPARE buff_stmt FROM @buff_sql; EXECUTE buff_stmt; DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clips' AND COLUMN_NAME='storage_key')=0,'ALTER TABLE `clips` ADD COLUMN `storage_key` VARCHAR(500) DEFAULT NULL','SELECT 1');
PREPARE buff_stmt FROM @buff_sql; EXECUTE buff_stmt; DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clips' AND COLUMN_NAME='storage_provider')=0,'ALTER TABLE `clips` ADD COLUMN `storage_provider` VARCHAR(30) NOT NULL DEFAULT ''local''','SELECT 1');
PREPARE buff_stmt FROM @buff_sql; EXECUTE buff_stmt; DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='feed_posts' AND COLUMN_NAME='visibility')=0,'ALTER TABLE `feed_posts` ADD COLUMN `visibility` VARCHAR(30) NOT NULL DEFAULT ''public''','SELECT 1');
PREPARE buff_stmt FROM @buff_sql; EXECUTE buff_stmt; DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='feed_posts' AND COLUMN_NAME='membership_tier_required')=0,'ALTER TABLE `feed_posts` ADD COLUMN `membership_tier_required` VARCHAR(20) DEFAULT NULL','SELECT 1');
PREPARE buff_stmt FROM @buff_sql; EXECUTE buff_stmt; DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='feed_posts' AND COLUMN_NAME='clip_id')=0,'ALTER TABLE `feed_posts` ADD COLUMN `clip_id` BIGINT UNSIGNED DEFAULT NULL','SELECT 1');
PREPARE buff_stmt FROM @buff_sql; EXECUTE buff_stmt; DEALLOCATE PREPARE buff_stmt;

SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='metadata_json')=0,'ALTER TABLE `notifications` ADD COLUMN `metadata_json` LONGTEXT DEFAULT NULL','SELECT 1');
PREPARE buff_stmt FROM @buff_sql; EXECUTE buff_stmt; DEALLOCATE PREPARE buff_stmt;

INSERT INTO `buff_install_state` (`id`,`version`) VALUES (1,'14.3.0') ON DUPLICATE KEY UPDATE `version`=VALUES(`version`),`updated_at`=CURRENT_TIMESTAMP;


-- =============================================================
-- BUFF V16 SOCIAL / MESSAGING / USER CLIPS
-- =============================================================
SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='public_id')=0,
'ALTER TABLE `users` ADD COLUMN `public_id` VARCHAR(16) DEFAULT NULL','SELECT 1');
PREPARE buff_stmt FROM @buff_sql; EXECUTE buff_stmt; DEALLOCATE PREPARE buff_stmt;
UPDATE users SET public_id=CONCAT('BF',LPAD(id,8,'0')) WHERE public_id IS NULL OR public_id='';
SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND INDEX_NAME='uq_users_public_id')=0,
'ALTER TABLE `users` ADD UNIQUE KEY `uq_users_public_id` (`public_id`)','SELECT 1');
PREPARE buff_stmt FROM @buff_sql; EXECUTE buff_stmt; DEALLOCATE PREPARE buff_stmt;

CREATE TABLE IF NOT EXISTS `message_rooms` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room_type` VARCHAR(20) NOT NULL DEFAULT 'direct',
  `direct_key` VARCHAR(80) DEFAULT NULL,
  `title` VARCHAR(120) NOT NULL DEFAULT '',
  `avatar_url` VARCHAR(1000) DEFAULT NULL,
  `created_by_user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), UNIQUE KEY `uq_message_rooms_direct_key` (`direct_key`),
  KEY `idx_message_rooms_updated` (`updated_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `message_room_members` (
  `room_id` BIGINT UNSIGNED NOT NULL, `user_id` BIGINT UNSIGNED NOT NULL,
  `member_role` VARCHAR(20) NOT NULL DEFAULT 'member', `last_read_message_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `joined_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`room_id`,`user_id`), KEY `idx_message_members_user` (`user_id`,`room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `room_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `room_id` BIGINT UNSIGNED NOT NULL, `user_id` BIGINT UNSIGNED NOT NULL,
  `message_type` VARCHAR(20) NOT NULL DEFAULT 'text', `body` VARCHAR(2000) NOT NULL DEFAULT '',
  `media_url` VARCHAR(1000) DEFAULT NULL, `media_mime` VARCHAR(120) DEFAULT NULL, `duration_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3), `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`), KEY `idx_room_messages_room` (`room_id`,`id`), KEY `idx_room_messages_user` (`user_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dm_signals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `room_id` BIGINT UNSIGNED NOT NULL,
  `sender_user_id` BIGINT UNSIGNED NOT NULL, `recipient_user_id` BIGINT UNSIGNED NOT NULL,
  `signal_type` VARCHAR(30) NOT NULL, `payload_json` LONGTEXT NOT NULL, `delivered` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`), KEY `idx_dm_signal_poll` (`recipient_user_id`,`delivered`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `coin_transfers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `sender_user_id` BIGINT UNSIGNED NOT NULL,
  `recipient_user_id` BIGINT UNSIGNED NOT NULL, `room_id` BIGINT UNSIGNED DEFAULT NULL,
  `amount` BIGINT UNSIGNED NOT NULL, `note` VARCHAR(160) NOT NULL DEFAULT '', `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`), KEY `idx_coin_transfer_sender` (`sender_user_id`,`id`), KEY `idx_coin_transfer_recipient` (`recipient_user_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `clip_likes` (`clip_id` BIGINT UNSIGNED NOT NULL,`user_id` BIGINT UNSIGNED NOT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`clip_id`,`user_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `clip_comments` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`clip_id` BIGINT UNSIGNED NOT NULL,`user_id` BIGINT UNSIGNED NOT NULL,`parent_comment_id` BIGINT UNSIGNED DEFAULT NULL,`body` VARCHAR(600) NOT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),KEY `idx_clip_comments_clip` (`clip_id`,`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `clip_reposts` (`clip_id` BIGINT UNSIGNED NOT NULL,`user_id` BIGINT UNSIGNED NOT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`clip_id`,`user_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `clip_shares` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,`clip_id` BIGINT UNSIGNED NOT NULL,`user_id` BIGINT UNSIGNED NOT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),KEY `idx_clip_shares_clip` (`clip_id`,`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `clips` MODIFY COLUMN `stream_id` BIGINT UNSIGNED NULL;
SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clips' AND COLUMN_NAME='source_type')=0,
'ALTER TABLE `clips` ADD COLUMN `source_type` VARCHAR(20) NOT NULL DEFAULT ''live''','SELECT 1');
PREPARE buff_stmt FROM @buff_sql; EXECUTE buff_stmt; DEALLOCATE PREPARE buff_stmt;
SET @buff_sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clips' AND COLUMN_NAME='description')=0,
'ALTER TABLE `clips` ADD COLUMN `description` VARCHAR(500) NOT NULL DEFAULT ''''','SELECT 1');
PREPARE buff_stmt FROM @buff_sql; EXECUTE buff_stmt; DEALLOCATE PREPARE buff_stmt;

INSERT INTO buff_install_state (id,version) VALUES (1,'16.0.0')
ON DUPLICATE KEY UPDATE version=VALUES(version),updated_at=CURRENT_TIMESTAMP;

-- V17 additive schema. Existing records are preserved.
SET @v17sql=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='is_private')=0,'ALTER TABLE users ADD COLUMN is_private TINYINT(1) NOT NULL DEFAULT 0','SELECT 1');
PREPARE v17stmt FROM @v17sql;EXECUTE v17stmt;DEALLOCATE PREPARE v17stmt;
SET @v17sql=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='replay_visibility')=0,'ALTER TABLE users ADD COLUMN replay_visibility VARCHAR(20) NOT NULL DEFAULT ''private''','SELECT 1');
PREPARE v17stmt FROM @v17sql;EXECUTE v17stmt;DEALLOCATE PREPARE v17stmt;
SET @v17sql=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clips' AND COLUMN_NAME='playback_key')=0,'ALTER TABLE clips ADD COLUMN playback_key VARCHAR(1000) DEFAULT NULL','SELECT 1');
PREPARE v17stmt FROM @v17sql;EXECUTE v17stmt;DEALLOCATE PREPARE v17stmt;
SET @v17sql=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='recordings' AND COLUMN_NAME='playback_key')=0,'ALTER TABLE recordings ADD COLUMN playback_key VARCHAR(1000) DEFAULT NULL','SELECT 1');
PREPARE v17stmt FROM @v17sql;EXECUTE v17stmt;DEALLOCATE PREPARE v17stmt;
SET @v17sql=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='feed_posts' AND COLUMN_NAME='playback_key')=0,'ALTER TABLE feed_posts ADD COLUMN playback_key VARCHAR(1000) DEFAULT NULL','SELECT 1');
PREPARE v17stmt FROM @v17sql;EXECUTE v17stmt;DEALLOCATE PREPARE v17stmt;
SET @v17sql=IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gift_events' AND COLUMN_NAME='recording_id')=0,'ALTER TABLE gift_events ADD COLUMN recording_id BIGINT UNSIGNED DEFAULT NULL','SELECT 1');
PREPARE v17stmt FROM @v17sql;EXECUTE v17stmt;DEALLOCATE PREPARE v17stmt;
CREATE TABLE IF NOT EXISTS mutation_receipts(user_id BIGINT UNSIGNED NOT NULL,request_key VARCHAR(80) NOT NULL,operation VARCHAR(40) NOT NULL,payload_hash CHAR(64) NOT NULL,response_json LONGTEXT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(user_id,request_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS follow_requests(requester_user_id BIGINT UNSIGNED NOT NULL,target_user_id BIGINT UNSIGNED NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(requester_user_id,target_user_id),KEY idx_target(target_user_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO buff_install_state(id,version) VALUES(1,'17.0.0') ON DUPLICATE KEY UPDATE version=VALUES(version);
