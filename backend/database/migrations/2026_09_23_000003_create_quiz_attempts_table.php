<?php

return [
    'up' => "
        CREATE TABLE IF NOT EXISTS quiz_attempts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            category_id INT UNSIGNED NULL,
            score INT NOT NULL DEFAULT 0,
            correct_answers INT NOT NULL DEFAULT 0,
            hearts_used INT NOT NULL DEFAULT 0,
            played_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_quiz_attempts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            CONSTRAINT fk_quiz_attempts_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    'down' => "DROP TABLE IF EXISTS quiz_attempts;",
];
