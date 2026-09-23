<?php

// Shared setup for every backend/api/*.php endpoint: starts the session,
// opens the DB connection, and provides small helpers for reading JSON
// request bodies and sending JSON responses.

require __DIR__ . '/../database/connection.php';

session_start();

header('Content-Type: application/json');

/**
 * Decode the JSON request body into an associative array.
 */
function json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Send a JSON response and stop execution.
 */
function respond($data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function respond_error(string $message, int $status = 400): never
{
    respond(['error' => $message], $status);
}

/**
 * Reject the request unless it uses the given HTTP method.
 */
function require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        respond_error('Method not allowed', 405);
    }
}

/**
 * Return the logged-in user's public fields, or null if no one is logged in.
 */
function current_user(PDO $pdo): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, username, email, total_score, created_at FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        // Session points at a user that no longer exists.
        unset($_SESSION['user_id']);
        return null;
    }

    return $user;
}

/**
 * Return the logged-in user, or send a 401 response and stop execution.
 */
function require_auth(PDO $pdo): array
{
    $user = current_user($pdo);
    if (!$user) {
        respond_error('You must be logged in.', 401);
    }
    return $user;
}

/**
 * Validate a list of {question, options: {A,B,C,D}, answer, difficulty?}
 * payloads. Sends a 422 response and stops execution on the first problem.
 * Returns the same list with defaults filled in (difficulty, answer upper-cased).
 */
function validate_questions_payload(array $questions): array
{
    if (count($questions) === 0) {
        respond_error('Provide at least one question.', 422);
    }

    $validDifficulties = ['easy', 'normal', 'hard'];
    $validated = [];

    foreach ($questions as $index => $q) {
        $text = trim((string) ($q['question'] ?? ''));
        $options = $q['options'] ?? [];
        $answer = strtoupper((string) ($q['answer'] ?? ''));
        $difficulty = $q['difficulty'] ?? 'normal';

        if ($text === '') {
            respond_error('Question ' . ($index + 1) . ' needs question text.', 422);
        }
        foreach (['A', 'B', 'C', 'D'] as $letter) {
            if (empty($options[$letter]) || trim((string) $options[$letter]) === '') {
                respond_error('Question ' . ($index + 1) . ' needs all four answer options.', 422);
            }
        }
        if (!in_array($answer, ['A', 'B', 'C', 'D'], true)) {
            respond_error('Question ' . ($index + 1) . ' needs a correct answer selected.', 422);
        }
        if (!in_array($difficulty, $validDifficulties, true)) {
            $difficulty = 'normal';
        }

        $validated[] = [
            'question' => $text,
            'options' => $options,
            'answer' => $answer,
            'difficulty' => $difficulty,
        ];
    }

    return $validated;
}

/**
 * Look up a category by slug, or send a 422 response if it doesn't exist.
 * Returns null if no slug was given.
 */
function resolve_category_id(PDO $pdo, ?string $slug): ?int
{
    if (!$slug) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id FROM categories WHERE slug = ?');
    $stmt->execute([$slug]);
    $id = $stmt->fetchColumn();

    if ($id === false) {
        respond_error('Unknown category.', 422);
    }

    return (int) $id;
}
