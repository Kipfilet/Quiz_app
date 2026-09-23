<?php

require __DIR__ . '/bootstrap.php';

$pdo = get_pdo_connection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = '
        SELECT q.id, q.difficulty, q.question, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option,
               c.id AS category_id, c.slug AS category_slug, c.name AS category_name
        FROM questions q
        LEFT JOIN categories c ON c.id = q.category_id
    ';
    $params = [];

    if (!empty($_GET['category'])) {
        $sql .= ' WHERE c.slug = ?';
        $params[] = $_GET['category'];
    } elseif (isset($_GET['uncategorized'])) {
        $sql .= ' WHERE q.category_id IS NULL';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $questions = array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'difficulty' => $row['difficulty'],
            'question' => $row['question'],
            'options' => [
                'A' => $row['option_a'],
                'B' => $row['option_b'],
                'C' => $row['option_c'],
                'D' => $row['option_d'],
            ],
            'answer' => $row['correct_option'],
            'category' => $row['category_id'] !== null ? [
                'id' => (int) $row['category_id'],
                'slug' => $row['category_slug'],
                'name' => $row['category_name'],
            ] : null,
        ];
    }, $stmt->fetchAll());

    respond(['questions' => $questions]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_auth($pdo);

    $body = json_body();
    $categoryId = resolve_category_id($pdo, isset($body['category_slug']) ? trim((string) $body['category_slug']) : null);
    $questions = validate_questions_payload(is_array($body['questions'] ?? null) ? $body['questions'] : []);

    $insert = $pdo->prepare('
        INSERT INTO questions (category_id, difficulty, question, option_a, option_b, option_c, option_d, correct_option, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');

    $pdo->beginTransaction();
    try {
        foreach ($questions as $q) {
            $insert->execute([
                $categoryId, $q['difficulty'], $q['question'],
                $q['options']['A'], $q['options']['B'], $q['options']['C'], $q['options']['D'],
                $q['answer'], $user['id'],
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        respond_error('Could not save the questions.', 500);
    }

    respond(['created' => count($questions)], 201);
}

respond_error('Method not allowed', 405);
