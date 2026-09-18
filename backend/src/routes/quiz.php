<?php

function get_current_question_row(PDO $pdo, int $attemptId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT qaq.question_id, qaq.order_index, q.question_text, q.options, q.difficulty, q.category, q.country
         FROM quiz_attempt_questions qaq
         JOIN questions q ON q.id = qaq.question_id
         WHERE qaq.quiz_attempt_id = ? AND qaq.answered_at IS NULL
         ORDER BY qaq.order_index ASC
         LIMIT 1'
    );
    $stmt->execute([$attemptId]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    $row['options'] = json_decode($row['options'], true);

    return $row;
}

function fetch_attempt_with_answered_count(PDO $pdo, int $attemptId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT qa.*, (
            SELECT COUNT(*) FROM quiz_attempt_questions
            WHERE quiz_attempt_id = qa.id AND answered_at IS NOT NULL
         ) AS answered_count
         FROM quiz_attempts qa WHERE qa.id = ?'
    );
    $stmt->execute([$attemptId]);
    $attempt = $stmt->fetch();

    return $attempt ?: null;
}

function format_attempt_state(array $attempt, ?array $question): array
{
    return [
        'attempt_id' => (int) $attempt['id'],
        'status' => $attempt['status'],
        'lives' => (int) $attempt['lives'],
        'score' => (int) $attempt['score'],
        'questions_answered' => (int) $attempt['answered_count'],
        'total_questions' => QUESTIONS_PER_QUIZ,
        'question' => $question ? [
            'id' => (int) $question['question_id'],
            'order_index' => (int) $question['order_index'],
            'question_text' => $question['question_text'],
            'options' => $question['options'],
            'difficulty' => $question['difficulty'],
            'category' => $question['category'],
            'country' => $question['country'],
        ] : null,
    ];
}

function handle_quiz_start(PDO $pdo): void
{
    $user = require_auth($pdo);

    // Resume an in-progress attempt instead of starting a second one.
    $stmt = $pdo->prepare("SELECT id FROM quiz_attempts WHERE user_id = ? AND status = 'in_progress' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user['id']]);
    $existingId = $stmt->fetchColumn();

    if ($existingId) {
        $attempt = fetch_attempt_with_answered_count($pdo, (int) $existingId);
        $question = get_current_question_row($pdo, (int) $existingId);
        json_response(format_attempt_state($attempt, $question));
    }

    $category = trim($_GET['category'] ?? '');
    $country = trim($_GET['country'] ?? '');

    $where = [];
    $params = [];

    if ($category !== '') {
        $where[] = 'category = ?';
        $params[] = $category;
    }

    if ($country !== '') {
        $where[] = 'country = ?';
        $params[] = $country;
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmt = $pdo->prepare("SELECT id FROM questions {$whereSql} ORDER BY RAND() LIMIT " . QUESTIONS_PER_QUIZ);
    $stmt->execute($params);
    $questionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($questionIds) < QUESTIONS_PER_QUIZ) {
        json_error('Not enough questions available for this selection', 422);
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO quiz_attempts (user_id, status, lives, score, category, country) VALUES (?, "in_progress", ?, 0, ?, ?)'
        );
        $stmt->execute([
            $user['id'],
            STARTING_LIVES,
            $category !== '' ? $category : null,
            $country !== '' ? $country : null,
        ]);
        $attemptId = (int) $pdo->lastInsertId();

        $insertQuestion = $pdo->prepare(
            'INSERT INTO quiz_attempt_questions (quiz_attempt_id, question_id, order_index) VALUES (?, ?, ?)'
        );
        foreach (array_values($questionIds) as $index => $questionId) {
            $insertQuestion->execute([$attemptId, $questionId, $index]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $attempt = fetch_attempt_with_answered_count($pdo, $attemptId);
    $question = get_current_question_row($pdo, $attemptId);

    json_response(format_attempt_state($attempt, $question), 201);
}

function handle_quiz_answer(PDO $pdo, int $attemptId): void
{
    $user = require_auth($pdo);
    $body = read_json_body();

    if (!array_key_exists('question_id', $body) || !array_key_exists('selected_index', $body)) {
        json_error('question_id and selected_index are required');
    }

    $questionId = (int) $body['question_id'];
    $selectedIndex = (int) $body['selected_index'];

    $attempt = fetch_attempt_with_answered_count($pdo, $attemptId);

    if (!$attempt || (int) $attempt['user_id'] !== $user['id']) {
        json_error('Quiz attempt not found', 404);
    }

    if ($attempt['status'] !== 'in_progress') {
        json_error('This quiz attempt has already finished', 409);
    }

    $current = get_current_question_row($pdo, $attemptId);

    if (!$current || (int) $current['question_id'] !== $questionId) {
        json_error('This is not the current question for this attempt', 409);
    }

    $stmt = $pdo->prepare('SELECT difficulty, correct_index FROM questions WHERE id = ?');
    $stmt->execute([$questionId]);
    $question = $stmt->fetch();

    $isCorrect = $selectedIndex === (int) $question['correct_index'];
    $pointsAwarded = $isCorrect ? points_for_difficulty($question['difficulty']) : 0;

    $pdo->prepare(
        'UPDATE quiz_attempt_questions SET selected_index = ?, is_correct = ?, points_awarded = ?, answered_at = NOW()
         WHERE quiz_attempt_id = ? AND question_id = ?'
    )->execute([$selectedIndex, $isCorrect ? 1 : 0, $pointsAwarded, $attemptId, $questionId]);

    $newScore = (int) $attempt['score'] + $pointsAwarded;
    $newLives = max((int) $attempt['lives'] - ($isCorrect ? 0 : 1), 0);
    $newAnsweredCount = (int) $attempt['answered_count'] + 1;
    $finished = $newLives <= 0 || $newAnsweredCount >= QUESTIONS_PER_QUIZ;

    if ($finished) {
        $pdo->prepare("UPDATE quiz_attempts SET score = ?, lives = ?, status = 'completed', completed_at = NOW() WHERE id = ?")
            ->execute([$newScore, $newLives, $attemptId]);
        $pdo->prepare('UPDATE users SET total_score = total_score + ? WHERE id = ?')
            ->execute([$newScore, $user['id']]);
    } else {
        $pdo->prepare('UPDATE quiz_attempts SET score = ?, lives = ? WHERE id = ?')
            ->execute([$newScore, $newLives, $attemptId]);
    }

    $nextQuestion = $finished ? null : get_current_question_row($pdo, $attemptId);

    json_response([
        'is_correct' => $isCorrect,
        'correct_index' => (int) $question['correct_index'],
        'points_awarded' => $pointsAwarded,
        'attempt' => format_attempt_state([
            'id' => $attemptId,
            'status' => $finished ? 'completed' : 'in_progress',
            'lives' => $newLives,
            'score' => $newScore,
            'answered_count' => $newAnsweredCount,
        ], $nextQuestion),
    ]);
}
