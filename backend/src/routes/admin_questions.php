<?php

function validate_question_input(array $body, bool $partial = false): array
{
    $errors = [];
    $data = [];

    if (!$partial || array_key_exists('question_text', $body)) {
        $data['question_text'] = trim($body['question_text'] ?? '');
        if ($data['question_text'] === '') {
            $errors[] = 'question_text is required';
        }
    }

    if (!$partial || array_key_exists('options', $body)) {
        $options = $body['options'] ?? null;
        if (!is_array($options) || count($options) < 2) {
            $errors[] = 'options must be an array of at least 2 strings';
        } else {
            $data['options'] = array_values(array_map('strval', $options));
        }
    }

    if (!$partial || array_key_exists('correct_index', $body)) {
        $data['correct_index'] = (int) ($body['correct_index'] ?? -1);
        if (isset($data['options']) && ($data['correct_index'] < 0 || $data['correct_index'] >= count($data['options']))) {
            $errors[] = 'correct_index must point at one of the options';
        }
    }

    if (!$partial || array_key_exists('difficulty', $body)) {
        $data['difficulty'] = $body['difficulty'] ?? '';
        if (!array_key_exists($data['difficulty'], DIFFICULTY_POINTS)) {
            $errors[] = 'difficulty must be one of: ' . implode(', ', array_keys(DIFFICULTY_POINTS));
        }
    }

    if (!$partial || array_key_exists('quiz_set_id', $body)) {
        $data['quiz_set_id'] = (int) ($body['quiz_set_id'] ?? 0);
        if ($data['quiz_set_id'] <= 0) {
            $errors[] = 'quiz_set_id is required';
        }
    }

    if ($errors) {
        json_error(implode('; ', $errors));
    }

    return $data;
}

function handle_admin_list_questions(PDO $pdo): void
{
    require_admin($pdo);

    $rows = $pdo->query(
        'SELECT id, quiz_set_id, difficulty, question_text, options, correct_index, created_at FROM questions ORDER BY id DESC'
    )->fetchAll();

    foreach ($rows as &$row) {
        $row['options'] = json_decode($row['options'], true);
        $row['quiz_set_id'] = (int) $row['quiz_set_id'];
    }

    json_response($rows);
}

function handle_admin_create_question(PDO $pdo): void
{
    require_admin($pdo);

    $data = validate_question_input(read_json_body());

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO questions (quiz_set_id, difficulty, question_text, options, correct_index) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['quiz_set_id'],
            $data['difficulty'],
            $data['question_text'],
            json_encode($data['options']),
            $data['correct_index'],
        ]);
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000) {
            json_error('quiz_set_id does not reference an existing quiz set', 422);
        }
        throw $e;
    }

    $data['id'] = (int) $pdo->lastInsertId();
    json_response($data, 201);
}

function handle_admin_update_question(PDO $pdo, int $id): void
{
    require_admin($pdo);

    $stmt = $pdo->prepare('SELECT id FROM questions WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        json_error('Question not found', 404);
    }

    $data = validate_question_input(read_json_body(), true);

    if (!$data) {
        json_error('No fields to update');
    }

    $fields = [];
    $params = [];
    foreach ($data as $column => $value) {
        $fields[] = "{$column} = ?";
        $params[] = $column === 'options' ? json_encode($value) : $value;
    }
    $params[] = $id;

    try {
        $pdo->prepare('UPDATE questions SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000) {
            json_error('quiz_set_id does not reference an existing quiz set', 422);
        }
        throw $e;
    }

    json_response(['success' => true]);
}

function handle_admin_delete_question(PDO $pdo, int $id): void
{
    require_admin($pdo);

    try {
        $stmt = $pdo->prepare('DELETE FROM questions WHERE id = ?');
        $stmt->execute([$id]);
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000) {
            json_error('This question is used in past quiz attempts and cannot be deleted', 409);
        }
        throw $e;
    }

    if ($stmt->rowCount() === 0) {
        json_error('Question not found', 404);
    }

    json_response(['success' => true]);
}
