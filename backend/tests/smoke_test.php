<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);

require $projectRoot . '/backend/database/connection.php';
require $projectRoot . '/backend/src/scoring.php';

$port = 8099;
$baseUrl = "http://127.0.0.1:{$port}";

$passed = 0;
$failed = 0;

function run_test(string $name, callable $fn): void
{
    global $passed, $failed;

    try {
        $fn();
        $passed++;
        echo "[PASS] {$name}\n";
    } catch (Throwable $e) {
        $failed++;
        echo "[FAIL] {$name}: {$e->getMessage()}\n";
    }
}

function http_request(string $cookieJar, string $method, string $url, ?array $body = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 5,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $raw = curl_exec($ch);

    if ($raw === false) {
        throw new RuntimeException('HTTP request failed: ' . curl_error($ch));
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($raw, true);

    return ['status' => $status, 'body' => is_array($decoded) ? $decoded : $raw];
}

function assert_status(array $response, int $expected, string $context): void
{
    if ($response['status'] !== $expected) {
        throw new RuntimeException("{$context}: expected HTTP {$expected}, got {$response['status']} (" . json_encode($response['body']) . ')');
    }
}

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function purge_smoke_data(PDO $pdo): void
{
    $pdo->exec("DELETE qaq FROM quiz_attempt_questions qaq
        JOIN quiz_attempts qa ON qa.id = qaq.quiz_attempt_id
        JOIN users u ON u.id = qa.user_id
        WHERE u.email LIKE 'smoke-%@smoketest.local'");
    $pdo->exec("DELETE qa FROM quiz_attempts qa
        JOIN users u ON u.id = qa.user_id
        WHERE u.email LIKE 'smoke-%@smoketest.local'");
    $pdo->exec("DELETE FROM users WHERE email LIKE 'smoke-%@smoketest.local'");
    $pdo->exec("DELETE FROM questions WHERE category = 'SmokeCategory'");
}

// --- Setup ---------------------------------------------------------------

$pdo = get_pdo_connection();
purge_smoke_data($pdo);

$stmt = $pdo->prepare(
    'INSERT INTO questions (category, country, difficulty, question_text, options, correct_index) VALUES (?, ?, ?, ?, ?, 0)'
);
$difficulties = ['easy', 'medium', 'hard'];
for ($i = 1; $i <= 12; $i++) {
    $stmt->execute(['SmokeCategory', 'Smokeland', $difficulties[$i % 3], "Smoke question {$i}", json_encode(['A', 'B', 'C', 'D'])]);
}

// Redirect the dev server's own stdio to the null device so its pipes never
// fill up and block it while we're not reading from them.
$nul = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
$process = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', 'frontend', 'backend/main.php'],
    [0 => ['file', $nul, 'r'], 1 => ['file', $nul, 'w'], 2 => ['file', $nul, 'w']],
    $pipes,
    $projectRoot
);

if (!is_resource($process)) {
    fwrite(STDERR, "Failed to start the dev server.\n");
    exit(1);
}

$ready = false;
for ($i = 0; $i < 50; $i++) {
    $ch = curl_init("{$baseUrl}/api/leaderboard");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
    $ready = curl_exec($ch) !== false;
    curl_close($ch);
    if ($ready) {
        break;
    }
    usleep(100000);
}

if (!$ready) {
    proc_terminate($process);
    proc_close($process);
    fwrite(STDERR, "Dev server did not start in time.\n");
    exit(1);
}

$adminCookies = tempnam(sys_get_temp_dir(), 'quizcookie');
$userCookies = tempnam(sys_get_temp_dir(), 'quizcookie');
$anonCookies = tempnam(sys_get_temp_dir(), 'quizcookie');

// --- Tests -----------------------------------------------------------------

try {
    $adminEmail = 'smoke-admin@smoketest.local';
    $userEmail = 'smoke-user@smoketest.local';

    run_test('register creates a user', function () use ($baseUrl, $adminCookies, $adminEmail) {
        $res = http_request($adminCookies, 'POST', "{$baseUrl}/api/auth/register", [
            'username' => 'smoke_admin',
            'email' => $adminEmail,
            'password' => 'password123',
        ]);
        assert_status($res, 201, 'register');
        assert_true($res['body']['role'] === 'user', 'new accounts should default to role=user');
        assert_true($res['body']['total_score'] === 0, 'new accounts should start at total_score=0');
    });

    run_test('duplicate email rejected (409)', function () use ($baseUrl, $adminCookies, $adminEmail) {
        $res = http_request($adminCookies, 'POST', "{$baseUrl}/api/auth/register", [
            'username' => 'someone_else',
            'email' => $adminEmail,
            'password' => 'password123',
        ]);
        assert_status($res, 409, 'duplicate register');
    });

    run_test('login sets session', function () use ($baseUrl, $adminCookies, $adminEmail) {
        assert_status(http_request($adminCookies, 'POST', "{$baseUrl}/api/auth/login", [
            'email' => $adminEmail,
            'password' => 'password123',
        ]), 200, 'login');

        $me = http_request($adminCookies, 'GET', "{$baseUrl}/api/auth/me");
        assert_status($me, 200, 'me after login');
        assert_true($me['body']['email'] === $adminEmail, 'me should return the logged-in user');
    });

    // There's no signup flow for admins by design - promote directly in the DB.
    $pdo->prepare("UPDATE users SET role = 'admin' WHERE email = ?")->execute([$adminEmail]);

    run_test('admin can create/update/delete a question', function () use ($baseUrl, $adminCookies) {
        $create = http_request($adminCookies, 'POST', "{$baseUrl}/api/admin/questions", [
            'question_text' => 'Smoke admin question',
            'options' => ['X', 'Y'],
            'correct_index' => 1,
            'difficulty' => 'medium',
            'category' => 'SmokeCategoryAdmin',
            'country' => 'Smokeland',
        ]);
        assert_status($create, 201, 'admin create question');
        $id = $create['body']['id'];

        assert_status(http_request($adminCookies, 'PUT', "{$baseUrl}/api/admin/questions/{$id}", [
            'question_text' => 'Smoke admin question (edited)',
        ]), 200, 'admin update question');

        assert_status(http_request($adminCookies, 'DELETE', "{$baseUrl}/api/admin/questions/{$id}"), 200, 'admin delete question');
    });

    http_request($userCookies, 'POST', "{$baseUrl}/api/auth/register", [
        'username' => 'smoke_user',
        'email' => $userEmail,
        'password' => 'password123',
    ]);
    http_request($userCookies, 'POST', "{$baseUrl}/api/auth/login", [
        'email' => $userEmail,
        'password' => 'password123',
    ]);

    $attemptId = null;
    $firstQuestionPoints = null;

    run_test('quiz/start returns a 10-question attempt', function () use ($baseUrl, $userCookies, &$attemptId) {
        $start = http_request($userCookies, 'GET', "{$baseUrl}/api/quiz/start?category=SmokeCategory");
        assert_status($start, 201, 'quiz start');
        assert_true($start['body']['total_questions'] === 10, 'attempt should track 10 questions');
        assert_true($start['body']['lives'] === 3, 'attempt should start with 3 lives');
        assert_true($start['body']['question'] !== null, 'attempt should include the current question');
        assert_true(!array_key_exists('correct_index', $start['body']['question']), 'the current question must not leak correct_index');
        $attemptId = $start['body']['attempt_id'];
    });

    run_test('correct answer scores by difficulty', function () use ($baseUrl, $userCookies, &$attemptId, &$firstQuestionPoints) {
        $question = http_request($userCookies, 'GET', "{$baseUrl}/api/quiz/start")['body']['question'];

        $answer = http_request($userCookies, 'POST', "{$baseUrl}/api/quiz/{$attemptId}/answer", [
            'question_id' => $question['id'],
            'selected_index' => 0,
        ]);
        assert_status($answer, 200, 'answer correctly');
        assert_true($answer['body']['is_correct'] === true, 'selected_index 0 should be correct for seeded questions');
        assert_true($answer['body']['points_awarded'] === points_for_difficulty($question['difficulty']), 'points should match the difficulty table');
        assert_true($answer['body']['attempt']['lives'] === 3, 'a correct answer should not cost a life');

        $firstQuestionPoints = $answer['body']['points_awarded'];
    });

    run_test('wrong answer costs a life, and 0 lives completes the attempt', function () use ($baseUrl, $userCookies, &$attemptId) {
        $lives = 3;
        $finalStatus = null;

        while ($lives > 0) {
            $question = http_request($userCookies, 'GET', "{$baseUrl}/api/quiz/start")['body']['question'];

            $answer = http_request($userCookies, 'POST', "{$baseUrl}/api/quiz/{$attemptId}/answer", [
                'question_id' => $question['id'],
                'selected_index' => 1, // seeded correct_index is always 0, so this is always wrong
            ]);
            assert_status($answer, 200, 'answer incorrectly');
            assert_true($answer['body']['is_correct'] === false, 'selected_index 1 should be wrong for seeded questions');

            $lives = $answer['body']['attempt']['lives'];
            $finalStatus = $answer['body']['attempt']['status'];
        }

        assert_true($lives === 0, 'lives should reach exactly 0');
        assert_true($finalStatus === 'completed', 'the attempt should be marked completed once lives hit 0');
    });

    run_test('leaderboard reflects total_score', function () use ($baseUrl, $userCookies, &$firstQuestionPoints) {
        $leaderboard = http_request($userCookies, 'GET', "{$baseUrl}/api/leaderboard?limit=50");
        assert_status($leaderboard, 200, 'leaderboard');

        $entry = null;
        foreach ($leaderboard['body'] as $row) {
            if ($row['username'] === 'smoke_user') {
                $entry = $row;
                break;
            }
        }

        assert_true($entry !== null, 'smoke_user should appear on the leaderboard');
        assert_true($entry['total_score'] === $firstQuestionPoints, 'total_score should equal the points from the one correct answer (wrong answers score 0)');
    });

    run_test('non-admin and anonymous requests are blocked from admin endpoints', function () use ($baseUrl, $userCookies, $anonCookies) {
        assert_status(http_request($userCookies, 'GET', "{$baseUrl}/api/admin/questions"), 403, 'admin endpoint as logged-in non-admin');
        assert_status(http_request($anonCookies, 'GET', "{$baseUrl}/api/admin/questions"), 401, 'admin endpoint with no session');
    });
} finally {
    purge_smoke_data($pdo);
    @unlink($adminCookies);
    @unlink($userCookies);
    @unlink($anonCookies);
    proc_terminate($process);
    proc_close($process);
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
