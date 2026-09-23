<?php

require __DIR__ . '/bootstrap.php';

require_method('GET');

$pdo = get_pdo_connection();
$user = current_user($pdo);

respond([
    'logged_in' => $user !== null,
    'user' => $user,
]);
