<?php

require __DIR__ . '/bootstrap.php';

require_method('POST');

$body = json_body();

$email = trim((string) ($body['email'] ?? ''));
$password = (string) ($body['password'] ?? '');

if ($email === '' || $password === '') {
    respond_error('Enter your email and password.', 422);
}

$pdo = get_pdo_connection();

$stmt = $pdo->prepare('SELECT id, username, email, password_hash FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    respond_error('Invalid email or password.', 401);
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $user['id'];

respond(['user' => current_user($pdo)]);
