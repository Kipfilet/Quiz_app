<?php

return [
    'up' => "
        CREATE TABLE IF NOT EXISTS quiz_attempt_questions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            quiz_attempt_id INT UNSIGNED NOT NULL,
            question_id INT UNSIGNED NOT NULL,
            order_index TINYINT UNSIGNED NOT NULL,
            selected_index TINYINT UNSIGNED NULL,
            is_correct BOOLEAN NULL,
            points_awarded INT UNSIGNED NOT NULL DEFAULT 0,
            answered_at TIMESTAMP NULL,
            CONSTRAINT fk_qaq_attempt FOREIGN KEY (quiz_attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
            CONSTRAINT fk_qaq_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE RESTRICT,
            UNIQUE KEY uniq_attempt_order (quiz_attempt_id, order_index),
            UNIQUE KEY uniq_attempt_question (quiz_attempt_id, question_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ",
    'down' => "DROP TABLE IF EXISTS quiz_attempt_questions;",
];
