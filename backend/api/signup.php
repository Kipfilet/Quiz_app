<?php

require __DIR__ . '/bootstrap.php';

require_method('POST');

$body = json_body();

$username = trim((string) ($body['username'] ?? ''));
$email = trim((string) ($body['email'] ?? ''));
$password = (string) ($body['password'] ?? '');
$confirmPassword = (string) ($body['confirm_password'] ?? '');

$errors = [];

if ($username === '' || strlen($username) < 3 || strlen($username) > 50) {
    $errors['username'] = 'Username must be between 3 and 50 characters.';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter a valid email address.';
}

if (strlen($password) < 6) {
    $errors['password'] = 'Password must be at least 6 characters.';
}

if ($password !== $confirmPassword) {
    $errors['confirm_password'] = 'Passwords do not match.';
}

if (!empty($errors)) {
    respond(['error' => 'Please fix the highlighted fields.', 'fields' => $errors], 422);
}

$pdo = get_pdo_connection();

$check = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
$check->execute([$username, $email]);
if ($check->fetch()) {
    respond(['error' => 'That username or email is already taken.', 'fields' => []], 409);
}

$insert = $pdo->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
$insert->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);

$userId = (int) $pdo->lastInsertId();

session_regenerate_id(true);
$_SESSION['user_id'] = $userId;

$user = current_user($pdo);

respond(['user' => $user], 201);
