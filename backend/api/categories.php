<?php

require __DIR__ . '/bootstrap.php';

require_method('GET');

$pdo = get_pdo_connection();

$rows = $pdo->query('
    SELECT c.id, c.slug, c.name, c.emoji, c.color, COUNT(q.id) AS question_count
    FROM categories c
    LEFT JOIN questions q ON q.category_id = c.id
    GROUP BY c.id, c.slug, c.name, c.emoji, c.color
    ORDER BY c.id ASC
')->fetchAll();

foreach ($rows as &$row) {
    $row['question_count'] = (int) $row['question_count'];
}

respond(['categories' => $rows]);
