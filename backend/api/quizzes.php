<?php

require __DIR__ . '/bootstrap.php';

$pdo = get_pdo_connection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query('
        SELECT z.id, z.title, z.description, z.difficulty, z.created_at,
               c.slug AS category_slug, c.name AS category_name,
               u.username AS author,
               COUNT(q.id) AS question_count
        FROM quizzes z
        LEFT JOIN categories c ON c.id = z.category_id
        LEFT JOIN users u ON u.id = z.created_by
        LEFT JOIN questions q ON q.quiz_id = z.id
        GROUP BY z.id, z.title, z.description, z.difficulty, z.created_at, c.slug, c.name, u.username
        ORDER BY z.created_at DESC
    ');

    $quizzes = array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'difficulty' => $row['difficulty'],
            'category' => $row['category_slug'] ? ['slug' => $row['category_slug'], 'name' => $row['category_name']] : null,
            'author' => $row['author'],
            'question_count' => (int) $row['question_count'],
            'created_at' => $row['created_at'],
        ];
    }, $stmt->fetchAll());

    respond(['quizzes' => $quizzes]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_auth($pdo);

    $body = json_body();
    $title = trim((string) ($body['title'] ?? ''));
    $description = trim((string) ($body['description'] ?? ''));
    $difficulty = in_array($body['difficulty'] ?? '', ['easy', 'normal', 'hard'], true) ? $body['difficulty'] : 'normal';
    $categoryId = resolve_category_id($pdo, isset($body['category_slug']) ? trim((string) $body['category_slug']) : null);
    $questions = validate_questions_payload(is_array($body['questions'] ?? null) ? $body['questions'] : []);

    if ($title === '') {
        respond_error('Give your quiz a title.', 422);
    }

    $pdo->beginTransaction();
    try {
        $insertQuiz = $pdo->prepare('
            INSERT INTO quizzes (title, description, category_id, difficulty, created_by)
            VALUES (?, ?, ?, ?, ?)
        ');
        $insertQuiz->execute([$title, $description ?: null, $categoryId, $difficulty, $user['id']]);
        $quizId = (int) $pdo->lastInsertId();

        $insertQuestion = $pdo->prepare('
            INSERT INTO questions (category_id, quiz_id, difficulty, question, option_a, option_b, option_c, option_d, correct_option, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        foreach ($questions as $q) {
            $insertQuestion->execute([
                $categoryId, $quizId, $q['difficulty'], $q['question'],
                $q['options']['A'], $q['options']['B'], $q['options']['C'], $q['options']['D'],
                $q['answer'], $user['id'],
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        respond_error('Could not save the quiz.', 500);
    }

    respond(['id' => $quizId, 'questions_created' => count($questions)], 201);
}

respond_error('Method not allowed', 405);
