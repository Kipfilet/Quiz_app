<?php

return [
    'up' => "
        ALTER TABLE questions
            ADD COLUMN quiz_set_id INT UNSIGNED NOT NULL AFTER id,
            DROP INDEX idx_questions_category,
            DROP INDEX idx_questions_country,
            DROP COLUMN category,
            DROP COLUMN country,
            ADD CONSTRAINT fk_questions_quiz_set FOREIGN KEY (quiz_set_id) REFERENCES quiz_sets(id) ON DELETE RESTRICT,
            ADD INDEX idx_questions_quiz_set (quiz_set_id);
    ",
    'down' => "
        ALTER TABLE questions
            DROP FOREIGN KEY fk_questions_quiz_set,
            DROP INDEX idx_questions_quiz_set,
            DROP COLUMN quiz_set_id,
            ADD COLUMN category VARCHAR(100) NOT NULL AFTER id,
            ADD COLUMN country VARCHAR(100) NULL AFTER category,
            ADD INDEX idx_questions_category (category),
            ADD INDEX idx_questions_country (country);
    ",
];
