<?php

require __DIR__ . '/bootstrap.php';

require_method('GET');

$pdo = get_pdo_connection();

$limit = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 10;

$stmt = $pdo->prepare('
    SELECT u.id, u.username, u.total_score, COUNT(qa.id) AS quizzes_played
    FROM users u
    LEFT JOIN quiz_attempts qa ON qa.user_id = u.id
    GROUP BY u.id, u.username, u.total_score
    ORDER BY u.total_score DESC, quizzes_played DESC, u.username ASC
    LIMIT ' . $limit . '
');
$stmt->execute();

$rows = array_map(static function (array $row): array {
    return [
        'id' => (int) $row['id'],
        'username' => $row['username'],
        'total_score' => (int) $row['total_score'],
        'quizzes_played' => (int) $row['quizzes_played'],
    ];
}, $stmt->fetchAll());

respond(['leaderboard' => $rows]);
