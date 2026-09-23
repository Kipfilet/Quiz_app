<?php

return [
    'up' => "
        ALTER TABLE questions
            ADD COLUMN quiz_id INT UNSIGNED NULL AFTER category_id,
            ADD CONSTRAINT fk_questions_quiz FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE;
    ",
    'down' => "
        ALTER TABLE questions
            DROP FOREIGN KEY fk_questions_quiz,
            DROP COLUMN quiz_id;
    ",
];
