<?php

function handle_register(PDO $pdo): void
{
    $body = read_json_body();
    $username = trim($body['username'] ?? '');
    $email = trim($body['email'] ?? '');
    $password = (string) ($body['password'] ?? '');

    $missing = [];
    if ($username === '') $missing[] = 'username';
    if ($email === '') $missing[] = 'email';
    if ($password === '') $missing[] = 'password';
    if ($missing) {
        $verb = count($missing) === 1 ? 'is' : 'are';
        json_error(implode(', ', $missing) . " $verb required");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_error('Invalid email address: ' . $email);
    }

    if (strlen($password) < 8) {
        json_error('Password must be at least 8 characters (got ' . strlen($password) . ')');
    }

    $stmt = $pdo->prepare('SELECT username, email FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $email]);
    $existing = $stmt->fetch();
    if ($existing) {
        if ($existing['username'] === $username) {
            json_error("Username '$username' is already taken", 409);
        }
        json_error("Email '$email' is already registered", 409);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$username, $email, $passwordHash]);
    $userId = (int) $pdo->lastInsertId();

    start_session_once();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;

    json_response([
        'id' => $userId,
        'username' => $username,
        'email' => $email,
        'total_score' => 0,
        'role' => 'user',
    ], 201);
}

function handle_login(PDO $pdo): void
{
    $body = read_json_body();
    $email = trim($body['email'] ?? '');
    $password = (string) ($body['password'] ?? '');

    if ($email === '' || $password === '') {
        json_error('email and password are required');
    }

    $stmt = $pdo->prepare('SELECT id, username, email, password_hash, total_score, role FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        json_error('Invalid email or password', 401);
    }

    start_session_once();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];

    unset($user['password_hash']);
    json_response(normalize_user($user));
}

function handle_logout(PDO $pdo): void
{
    start_session_once();
    $_SESSION = [];
    session_destroy();
    json_response(['success' => true]);
}

function handle_me(PDO $pdo): void
{
    json_response(require_auth($pdo));
}
