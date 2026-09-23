<?php

return [
    'up' => "
        CREATE TABLE IF NOT EXISTS quizzes (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(150) NOT NULL,
            description TEXT NULL,
            category_id INT UNSIGNED NULL,
            difficulty ENUM('easy', 'normal', 'hard') NOT NULL DEFAULT 'normal',
            created_by INT UNSIGNED NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_quizzes_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
            CONSTRAINT fk_quizzes_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    'down' => "DROP TABLE IF EXISTS quizzes;",
];
