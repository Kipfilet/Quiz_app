<?php

return [
    'up' => "
        CREATE TABLE IF NOT EXISTS quiz_sets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(120) NOT NULL,
            description VARCHAR(500) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_quiz_sets_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    'down' => "DROP TABLE IF EXISTS quiz_sets;",
];
