<?php

return [
    'up' => "
        CREATE TABLE IF NOT EXISTS quiz_attempts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            status ENUM('in_progress','completed') NOT NULL DEFAULT 'in_progress',
            lives TINYINT UNSIGNED NOT NULL DEFAULT 3,
            score INT UNSIGNED NOT NULL DEFAULT 0,
            category VARCHAR(100) NULL,
            country VARCHAR(100) NULL,
            started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            CONSTRAINT fk_quiz_attempts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_quiz_attempts_user (user_id),
            INDEX idx_quiz_attempts_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    'down' => "DROP TABLE IF EXISTS quiz_attempts;",
];
