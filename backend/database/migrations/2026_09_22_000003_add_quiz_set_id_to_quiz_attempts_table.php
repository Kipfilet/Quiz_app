<?php

return [
    'up' => "
        ALTER TABLE quiz_attempts
            ADD COLUMN quiz_set_id INT UNSIGNED NULL AFTER score,
            DROP COLUMN category,
            DROP COLUMN country,
            ADD CONSTRAINT fk_quiz_attempts_quiz_set FOREIGN KEY (quiz_set_id) REFERENCES quiz_sets(id) ON DELETE RESTRICT,
            ADD INDEX idx_quiz_attempts_quiz_set (quiz_set_id);
    ",
    'down' => "
        ALTER TABLE quiz_attempts
            DROP FOREIGN KEY fk_quiz_attempts_quiz_set,
            DROP INDEX idx_quiz_attempts_quiz_set,
            DROP COLUMN quiz_set_id,
            ADD COLUMN category VARCHAR(100) NULL,
            ADD COLUMN country VARCHAR(100) NULL;
    ",
];
