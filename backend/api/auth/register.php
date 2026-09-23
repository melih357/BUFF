<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_post();

if (site_setting('registration_enabled', true) === false) {
    json_response(['message' => 'Yeni kullanıcı kaydı geçici olarak kapalı.'], 403);
}

try {
    $data = request_json();
    $username = trim((string)($data['username'] ?? ''));
    $email = strtolower(trim((string)($data['email'] ?? '')));
    $password = (string)($data['password'] ?? '');
    $displayName = trim((string)($data['displayName'] ?? $username));
    $accountMode = strtolower(trim((string)($data['accountMode'] ?? 'viewer')));
    if (!in_array($accountMode, ['viewer','creator'], true)) $accountMode = 'viewer';

    if (!preg_match('/^[a-zA-Z0-9_]{3,24}$/', $username)) {
        json_response(['message' => 'Kullanıcı adı 3-24 karakter olmalı.'], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(['message' => 'Geçerli bir e-posta girin.'], 400);
    }
    if (text_len($password) < 6) {
        json_response(['message' => 'Şifre en az 6 karakter olmalı.'], 400);
    }
    if ($displayName === '') {
        $displayName = $username;
    }
    $displayName = text_substr($displayName, 0, 60);

    $pdo = db();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        json_response(['message' => 'Bu kullanıcı adı veya e-posta zaten kullanımda.'], 409);
    }

    $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, display_name, account_mode, creator_enabled) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $displayName, $accountMode, $accountMode === 'creator' ? 1 : 0]);
    $userId = (int)$pdo->lastInsertId();

    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    touch_user_session($userId);

    $user = fetch_user_by_id($userId);
    $shared = v14_create_shared_session($userId, false);
    v14_loyalty_snapshot($userId);
    json_response(['message' => 'Hesap oluşturuldu.', 'user' => public_user($user), 'accessToken' => $shared['token'], 'tokenExpiresAt' => $shared['expiresAt']], 201);
} catch (Throwable $e) {
    error_log('[BUFF auth] ' . $e->getMessage());
    $debug = (string)(app_env()['APP_DEBUG'] ?? '') === '1';
    json_response(['message' => 'Kayıt sırasında hata oluştu.' . ($debug ? ' Teknik ayrıntı: ' . $e->getMessage() : '')], 500);
}
