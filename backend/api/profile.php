<?php

require __DIR__ . '/bootstrap.php';

require_method('GET');

$pdo = get_pdo_connection();
$user = require_auth($pdo);

$stmt = $pdo->prepare('
    SELECT qa.correct_answers, qa.hearts_used, qa.score, qa.played_at,
           c.name AS category_name
    FROM quiz_attempts qa
    LEFT JOIN categories c ON c.id = qa.category_id
    WHERE qa.user_id = ?
    ORDER BY qa.played_at DESC
');
$stmt->execute([$user['id']]);
$rows = $stmt->fetchAll();

$accuracies = [];
$history = [];

foreach ($rows as $row) {
    // Every recorded attempt ends when the player runs out of hearts, so
    // correct_answers + hearts_used is exactly how many questions they saw.
    $questionsAnswered = (int) $row['correct_answers'] + (int) $row['hearts_used'];
    $accuracy = $questionsAnswered > 0 ? (int) round(($row['correct_answers'] / $questionsAnswered) * 100) : 0;
    $accuracies[] = $accuracy;

    if (count($history) < 20) {
        $history[] = [
            'category' => $row['category_name'] ?? 'General Trivia',
            'questions_answered' => $questionsAnswered,
            'correct_answers' => (int) $row['correct_answers'],
            'accuracy' => $accuracy,
            'score' => (int) $row['score'],
            'played_at' => $row['played_at'],
            'passed' => $accuracy >= 50,
        ];
    }
}

$quizzesCompleted = count($rows);
$averageAccuracy = $quizzesCompleted > 0 ? (int) round(array_sum($accuracies) / $quizzesCompleted) : 0;
$bestAccuracy = $quizzesCompleted > 0 ? max($accuracies) : 0;

respond([
    'user' => [
        'username' => $user['username'],
        'total_score' => (int) $user['total_score'],
    ],
    'stats' => [
        'quizzes_completed' => $quizzesCompleted,
        'average_accuracy' => $averageAccuracy,
        'best_accuracy' => $bestAccuracy,
        'total_points' => (int) $user['total_score'],
    ],
    'history' => $history,
]);
