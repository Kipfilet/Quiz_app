<?php

function start_session_once(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function normalize_user(array $user): array
{
    $user['id'] = (int) $user['id'];
    $user['total_score'] = (int) $user['total_score'];
    return $user;
}

function current_user(PDO $pdo): ?array
{
    start_session_once();

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, username, email, total_score, role, created_at FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user ? normalize_user($user) : null;
}

function require_auth(PDO $pdo): array
{
    $user = current_user($pdo);

    if (!$user) {
        json_error('Authentication required', 401);
    }

    return $user;
}

function require_admin(PDO $pdo): array
{
    $user = require_auth($pdo);

    if ($user['role'] !== 'admin') {
        json_error('Admin access required', 403);
    }

    return $user;
}
