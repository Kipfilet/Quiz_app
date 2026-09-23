<?php

require __DIR__ . '/bootstrap.php';

require_method('POST');

$pdo = get_pdo_connection();
$user = current_user($pdo); // optional: guests can play, but only logged-in scores are saved to the leaderboard

$body = json_body();
$score = (int) ($body['score'] ?? 0);
$correctAnswers = (int) ($body['correct_answers'] ?? 0);
$heartsUsed = (int) ($body['hearts_used'] ?? 0);
$categorySlug = isset($body['category_slug']) ? trim((string) $body['category_slug']) : null;

if ($score < 0 || $correctAnswers < 0 || $heartsUsed < 0) {
    respond_error('Invalid quiz result.', 422);
}

$categoryId = null;
if ($categorySlug) {
    $catStmt = $pdo->prepare('SELECT id FROM categories WHERE slug = ?');
    $catStmt->execute([$categorySlug]);
    $found = $catStmt->fetchColumn();
    $categoryId = $found !== false ? (int) $found : null;
}

$pdo->beginTransaction();
try {
    $insert = $pdo->prepare('
        INSERT INTO quiz_attempts (user_id, category_id, score, correct_answers, hearts_used)
        VALUES (?, ?, ?, ?, ?)
    ');
    $insert->execute([$user['id'] ?? null, $categoryId, $score, $correctAnswers, $heartsUsed]);

    if ($user) {
        $pdo->prepare('UPDATE users SET total_score = total_score + ? WHERE id = ?')
            ->execute([$score, $user['id']]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    respond_error('Could not save the result.', 500);
}

respond([
    'saved' => true,
    'user' => $user ? current_user($pdo) : null,
], 201);
