<?php
declare(strict_types=1);

/**
 * BUFF V16 social/messaging layer.
 * Keeps local XAMPP installs working while adding public IDs, mutual-follow DMs,
 * group rooms, media/voice messages, P2P call signaling, clip interactions and coin transfers.
 */

function v16_safe_exec(PDO $pdo, string $sql): void {
    try { $pdo->exec($sql); } catch (Throwable $e) { error_log('[BUFF V16 schema] '.$e->getMessage()); }
}

function v16_index_exists(PDO $pdo, string $table, string $index): bool {
    try {
        $st=$pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
        $st->execute([$table,$index]);
        return (int)$st->fetchColumn()>0;
    } catch(Throwable $e){ return false; }
}

function ensure_v16_schema(PDO $pdo): void {
    static $done=false;
    if($done) return;

    if (!v14_column_exists($pdo,'users','public_id')) {
        v14_add_column($pdo,'users','public_id','VARCHAR(16) DEFAULT NULL');
    }
    try { $pdo->exec("UPDATE users SET public_id=CONCAT('BF',LPAD(id,8,'0')) WHERE public_id IS NULL OR public_id=''"); } catch(Throwable $e){}
    if (!v16_index_exists($pdo,'users','uq_users_public_id')) {
        v16_safe_exec($pdo,'ALTER TABLE users ADD UNIQUE KEY uq_users_public_id (public_id)');
    }

    v16_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `message_rooms` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `room_type` VARCHAR(20) NOT NULL DEFAULT 'direct',
      `direct_key` VARCHAR(80) DEFAULT NULL,
      `title` VARCHAR(120) NOT NULL DEFAULT '',
      `avatar_url` VARCHAR(1000) DEFAULT NULL,
      `created_by_user_id` BIGINT UNSIGNED NOT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_message_rooms_direct_key` (`direct_key`),
      KEY `idx_message_rooms_updated` (`updated_at`,`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    v16_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `message_room_members` (
      `room_id` BIGINT UNSIGNED NOT NULL,
      `user_id` BIGINT UNSIGNED NOT NULL,
      `member_role` VARCHAR(20) NOT NULL DEFAULT 'member',
      `last_read_message_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
      `joined_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`room_id`,`user_id`),
      KEY `idx_message_members_user` (`user_id`,`room_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    v16_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `room_messages` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `room_id` BIGINT UNSIGNED NOT NULL,
      `user_id` BIGINT UNSIGNED NOT NULL,
      `message_type` VARCHAR(20) NOT NULL DEFAULT 'text',
      `body` VARCHAR(2000) NOT NULL DEFAULT '',
      `media_url` VARCHAR(1000) DEFAULT NULL,
      `media_mime` VARCHAR(120) DEFAULT NULL,
      `duration_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
      `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
      `deleted_at` DATETIME DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_room_messages_room` (`room_id`,`id`),
      KEY `idx_room_messages_user` (`user_id`,`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    v16_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `dm_signals` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `room_id` BIGINT UNSIGNED NOT NULL,
      `sender_user_id` BIGINT UNSIGNED NOT NULL,
      `recipient_user_id` BIGINT UNSIGNED NOT NULL,
      `signal_type` VARCHAR(30) NOT NULL,
      `payload_json` LONGTEXT NOT NULL,
      `delivered` TINYINT(1) NOT NULL DEFAULT 0,
      `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
      PRIMARY KEY (`id`),
      KEY `idx_dm_signal_poll` (`recipient_user_id`,`delivered`,`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    v16_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `coin_transfers` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `sender_user_id` BIGINT UNSIGNED NOT NULL,
      `recipient_user_id` BIGINT UNSIGNED NOT NULL,
      `room_id` BIGINT UNSIGNED DEFAULT NULL,
      `amount` BIGINT UNSIGNED NOT NULL,
      `note` VARCHAR(160) NOT NULL DEFAULT '',
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_coin_transfer_sender` (`sender_user_id`,`id`),
      KEY `idx_coin_transfer_recipient` (`recipient_user_id`,`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    v16_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `clip_likes` (
      `clip_id` BIGINT UNSIGNED NOT NULL,
      `user_id` BIGINT UNSIGNED NOT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`clip_id`,`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    v16_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `clip_comments` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `clip_id` BIGINT UNSIGNED NOT NULL,
      `user_id` BIGINT UNSIGNED NOT NULL,
      `parent_comment_id` BIGINT UNSIGNED DEFAULT NULL,
      `body` VARCHAR(600) NOT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_clip_comments_clip` (`clip_id`,`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    v16_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `clip_reposts` (
      `clip_id` BIGINT UNSIGNED NOT NULL,
      `user_id` BIGINT UNSIGNED NOT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`clip_id`,`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    v16_safe_exec($pdo, "CREATE TABLE IF NOT EXISTS `clip_shares` (
      `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      `clip_id` BIGINT UNSIGNED NOT NULL,
      `user_id` BIGINT UNSIGNED NOT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_clip_shares_clip` (`clip_id`,`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    v16_safe_exec($pdo, 'ALTER TABLE clips MODIFY COLUMN stream_id BIGINT UNSIGNED NULL');
    v14_add_column($pdo,'clips','source_type',"VARCHAR(20) NOT NULL DEFAULT 'live'");
    v14_add_column($pdo,'clips','description',"VARCHAR(500) NOT NULL DEFAULT ''");

    try {
        $stmt=$pdo->prepare("INSERT INTO buff_install_state (id,version) VALUES (1,'16.0.0') ON DUPLICATE KEY UPDATE version=IF(CAST(SUBSTRING_INDEX(version,'.',1) AS UNSIGNED)<16,VALUES(version),version)");
        $stmt->execute();
    } catch(Throwable $e){}
    $done=true;
}

function v16_public_id(int $userId): string {
    $id='BF'.str_pad((string)$userId,8,'0',STR_PAD_LEFT);
    try {
        $st=db()->prepare('SELECT public_id FROM users WHERE id=? LIMIT 1');$st->execute([$userId]);$existing=(string)($st->fetchColumn()?:'');
        if($existing!=='') return $existing;
        db()->prepare('UPDATE users SET public_id=? WHERE id=?')->execute([$id,$userId]);
    } catch(Throwable $e){}
    return $id;
}

function v16_mutual_follow(int $a, int $b): bool {
    if($a<1||$b<1||$a===$b) return false;
    try {
        $st=db()->prepare('SELECT COUNT(*) FROM follows WHERE (follower_user_id=? AND followed_user_id=?) OR (follower_user_id=? AND followed_user_id=?)');
        $st->execute([$a,$b,$b,$a]);
        return (int)$st->fetchColumn()>=2;
    } catch(Throwable $e){ return false; }
}

function v16_room_member(int $roomId, int $userId): bool {
    try {$st=db()->prepare('SELECT 1 FROM message_room_members WHERE room_id=? AND user_id=? LIMIT 1');$st->execute([$roomId,$userId]);return (bool)$st->fetchColumn();}
    catch(Throwable $e){return false;}
}

function v16_direct_room_key(int $a,int $b): string {
    $ids=[$a,$b];sort($ids,SORT_NUMERIC);return 'direct:'.$ids[0].':'.$ids[1];
}

function v16_room_payload(array $room, int $viewerId): array {
    $pdo=db();$roomId=(int)$room['id'];
    $m=$pdo->prepare('SELECT u.id,u.public_id,u.username,u.display_name,u.avatar_url,m.member_role FROM message_room_members m JOIN users u ON u.id=m.user_id WHERE m.room_id=? ORDER BY (m.member_role=\'owner\') DESC,u.display_name');
    $m->execute([$roomId]);$members=$m->fetchAll();
    $last=$pdo->prepare('SELECT rm.id,rm.message_type,rm.body,rm.media_url,rm.duration_seconds,rm.created_at,u.display_name FROM room_messages rm JOIN users u ON u.id=rm.user_id WHERE rm.room_id=? AND rm.deleted_at IS NULL ORDER BY rm.id DESC LIMIT 1');
    $last->execute([$roomId]);$lr=$last->fetch();
    $unread=$pdo->prepare('SELECT COUNT(*) FROM room_messages rm JOIN message_room_members mm ON mm.room_id=rm.room_id AND mm.user_id=? WHERE rm.room_id=? AND rm.id>mm.last_read_message_id AND rm.user_id<>? AND rm.deleted_at IS NULL');
    $unread->execute([$viewerId,$roomId,$viewerId]);
    $displayTitle=(string)$room['title'];$displayAvatar=$room['avatar_url']?:null;
    if((string)$room['room_type']==='direct'){
        foreach($members as $u){if((int)$u['id']!==$viewerId){$displayTitle=(string)$u['display_name'];$displayAvatar=$u['avatar_url']?:null;break;}}
    }
    return [
      'id'=>$roomId,'type'=>(string)$room['room_type'],'title'=>$displayTitle,'avatarUrl'=>$displayAvatar,
      'members'=>array_map(static fn($u)=>['id'=>(int)$u['id'],'publicId'=>(string)($u['public_id']??''),'username'=>(string)$u['username'],'displayName'=>(string)$u['display_name'],'avatarUrl'=>$u['avatar_url']?:null,'role'=>(string)$u['member_role']],$members),
      'lastMessage'=>$lr?['id'=>(int)$lr['id'],'type'=>(string)$lr['message_type'],'body'=>(string)$lr['body'],'mediaUrl'=>$lr['media_url']?:null,'durationSeconds'=>(int)$lr['duration_seconds'],'senderName'=>(string)$lr['display_name'],'createdAt'=>(string)$lr['created_at']]:null,
      'unread'=>(int)$unread->fetchColumn(),'updatedAt'=>(string)$room['updated_at']
    ];
}

function v16_resolve_user_id(mixed $value): int {
    if (is_int($value) || (is_string($value) && ctype_digit($value))) return max(0,(int)$value);
    $publicId=strtoupper(trim((string)$value));
    if($publicId==='') return 0;
    try{$st=db()->prepare('SELECT id FROM users WHERE UPPER(public_id)=? LIMIT 1');$st->execute([$publicId]);return (int)($st->fetchColumn()?:0);}catch(Throwable $e){return 0;}
}
