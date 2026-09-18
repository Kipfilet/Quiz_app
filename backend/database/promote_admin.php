<?php

require __DIR__ . '/connection.php';

$email = $argv[1] ?? null;

if (!$email) {
    fwrite(STDERR, "Usage: php backend/database/promote_admin.php user@example.com\n");
    exit(1);
}

$pdo = get_pdo_connection();

$stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE email = ?");
$stmt->execute([$email]);

if ($stmt->rowCount() === 0) {
    fwrite(STDERR, "No user found with email: {$email}\n");
    exit(1);
}

echo "Promoted {$email} to admin.\n";
