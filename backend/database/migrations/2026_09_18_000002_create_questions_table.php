<?php

return [
    'up' => "
        CREATE TABLE IF NOT EXISTS questions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(100) NOT NULL,
            country VARCHAR(100) NULL,
            difficulty ENUM('easy','medium','hard') NOT NULL,
            question_text TEXT NOT NULL,
            options JSON NOT NULL,
            correct_index TINYINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_questions_category (category),
            INDEX idx_questions_country (country),
            INDEX idx_questions_difficulty (difficulty)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    'down' => "DROP TABLE IF EXISTS questions;",
];
