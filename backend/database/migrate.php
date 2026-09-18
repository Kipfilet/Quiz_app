<?php

require __DIR__ . '/connection.php';

function ensure_migrations_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

function applied_migrations(PDO $pdo): array
{
    return $pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
}

function migration_files(): array
{
    $files = glob(__DIR__ . '/migrations/*.php');
    sort($files);
    return $files;
}

function migrate_up(PDO $pdo): void
{
    ensure_migrations_table($pdo);
    $applied = applied_migrations($pdo);

    foreach (migration_files() as $file) {
        $name = basename($file);
        if (in_array($name, $applied, true)) {
            continue;
        }

        $migration = require $file;
        $pdo->exec($migration['up']);
        $pdo->prepare('INSERT INTO migrations (migration) VALUES (?)')->execute([$name]);

        echo "Migrated: {$name}\n";
    }
}

function migrate_rollback(PDO $pdo): void
{
    ensure_migrations_table($pdo);
    $last = $pdo->query('SELECT migration FROM migrations ORDER BY id DESC LIMIT 1')->fetchColumn();

    if (!$last) {
        echo "Nothing to roll back.\n";
        return;
    }

    $migration = require __DIR__ . '/migrations/' . $last;
    $pdo->exec($migration['down']);
    $pdo->prepare('DELETE FROM migrations WHERE migration = ?')->execute([$last]);

    echo "Rolled back: {$last}\n";
}

$pdo = get_pdo_connection();
$command = $argv[1] ?? 'migrate';

match ($command) {
    'migrate' => migrate_up($pdo),
    'rollback' => migrate_rollback($pdo),
    default => print("Unknown command: {$command}\n"),
};
