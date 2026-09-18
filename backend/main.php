<?php

declare(strict_types=1);

require __DIR__ . '/database/connection.php';
require __DIR__ . '/src/response.php';
require __DIR__ . '/src/auth.php';
require __DIR__ . '/src/scoring.php';
require __DIR__ . '/src/routes/auth.php';
require __DIR__ . '/src/routes/quiz.php';
require __DIR__ . '/src/routes/leaderboard.php';
require __DIR__ . '/src/routes/questions_meta.php';
require __DIR__ . '/src/routes/admin_questions.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

// When run via `php -S ... backend/main.php` as a router script, returning
// false for non-API paths tells the built-in server to serve the requested
// file as-is (e.g. the static frontend) instead of running this script.
if (strpos($path, '/api/') !== 0) {
    return false;
}

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);

$routes = [
    ['POST', '#^/api/auth/register$#', 'handle_register'],
    ['POST', '#^/api/auth/login$#', 'handle_login'],
    ['POST', '#^/api/auth/logout$#', 'handle_logout'],
    ['GET', '#^/api/auth/me$#', 'handle_me'],

    ['GET', '#^/api/quiz/start$#', 'handle_quiz_start'],
    ['POST', '#^/api/quiz/(\d+)/answer$#', 'handle_quiz_answer'],

    ['GET', '#^/api/leaderboard$#', 'handle_leaderboard'],

    ['GET', '#^/api/questions/categories$#', 'handle_categories'],
    ['GET', '#^/api/questions/countries$#', 'handle_countries'],

    ['GET', '#^/api/admin/questions$#', 'handle_admin_list_questions'],
    ['POST', '#^/api/admin/questions$#', 'handle_admin_create_question'],
    ['PUT', '#^/api/admin/questions/(\d+)$#', 'handle_admin_update_question'],
    ['DELETE', '#^/api/admin/questions/(\d+)$#', 'handle_admin_delete_question'],
];

$method = $_SERVER['REQUEST_METHOD'];
$pdo = get_pdo_connection();

try {
    foreach ($routes as [$routeMethod, $pattern, $handler]) {
        if ($routeMethod !== $method || !preg_match($pattern, $path, $matches)) {
            continue;
        }

        array_shift($matches);
        $handler($pdo, ...array_map('intval', $matches));
        exit;
    }

    json_error('Not found', 404);
} catch (Throwable $e) {
    error_log($e->getMessage());
    json_error('Internal server error', 500);
}
