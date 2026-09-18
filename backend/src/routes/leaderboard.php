<?php

function handle_leaderboard(PDO $pdo): void
{
    $limit = isset($_GET['limit']) ? max(1, min(100, (int) $_GET['limit'])) : 10;

    $stmt = $pdo->query('SELECT id, username, total_score FROM users ORDER BY total_score DESC, id ASC LIMIT ' . $limit);

    json_response($stmt->fetchAll());
}
