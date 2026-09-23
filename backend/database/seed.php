<?php

// One-off data import: loads the question bank into the database from the
// JSON files under frontend/source/. Safe to re-run — it skips seeding a
// source once its questions already exist in the table.
//
//   php backend/database/seed.php

require __DIR__ . '/connection.php';

function seed_categories(PDO $pdo, array $categoryQuestions): array
{
    $slugToId = [];

    $select = $pdo->prepare('SELECT id FROM categories WHERE slug = ?');
    $insert = $pdo->prepare('INSERT INTO categories (slug, name, emoji, color) VALUES (?, ?, ?, ?)');

    foreach ($categoryQuestions as $category) {
        $select->execute([$category['slug']]);
        $id = $select->fetchColumn();

        if ($id === false) {
            $insert->execute([$category['slug'], $category['name'], $category['emoji'], $category['color']]);
            $id = (int) $pdo->lastInsertId();
            echo "Created category: {$category['name']}\n";
        }

        $slugToId[$category['slug']] = (int) $id;
    }

    return $slugToId;
}

function seed_general_questions(PDO $pdo, array $questions): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM questions WHERE category_id IS NULL')->fetchColumn();
    if ($count > 0) {
        echo "General question pool already seeded ({$count} rows), skipping.\n";
        return;
    }

    $insert = $pdo->prepare('
        INSERT INTO questions (category_id, difficulty, question, option_a, option_b, option_c, option_d, correct_option)
        VALUES (NULL, ?, ?, ?, ?, ?, ?, ?)
    ');

    foreach ($questions as $q) {
        $insert->execute([
            $q['difficulty'],
            $q['question'],
            $q['options']['A'],
            $q['options']['B'],
            $q['options']['C'],
            $q['options']['D'],
            $q['answer'],
        ]);
    }

    echo 'Seeded ' . count($questions) . " general trivia questions.\n";
}

function seed_category_questions(PDO $pdo, array $categoryQuestions, array $slugToId): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM questions WHERE category_id IS NOT NULL')->fetchColumn();
    if ($count > 0) {
        echo "Category question bank already seeded ({$count} rows), skipping.\n";
        return;
    }

    $insert = $pdo->prepare('
        INSERT INTO questions (category_id, difficulty, question, option_a, option_b, option_c, option_d, correct_option)
        VALUES (?, \'normal\', ?, ?, ?, ?, ?, ?)
    ');

    $total = 0;
    foreach ($categoryQuestions as $category) {
        $categoryId = $slugToId[$category['slug']];
        foreach ($category['questions'] as $q) {
            $insert->execute([
                $categoryId,
                $q['question'],
                $q['options']['A'],
                $q['options']['B'],
                $q['options']['C'],
                $q['options']['D'],
                $q['answer'],
            ]);
            $total++;
        }
    }

    echo "Seeded {$total} category questions.\n";
}

$pdo = get_pdo_connection();

$questionsJsonPath = __DIR__ . '/../../frontend/source/questions.json';
$categoryQuestionsJsonPath = __DIR__ . '/../../frontend/source/category_questions.json';

$questions = json_decode(file_get_contents($questionsJsonPath), true);
$categoryQuestions = json_decode(file_get_contents($categoryQuestionsJsonPath), true);

$pdo->beginTransaction();
try {
    $slugToId = seed_categories($pdo, $categoryQuestions);
    seed_general_questions($pdo, $questions);
    seed_category_questions($pdo, $categoryQuestions, $slugToId);
    $pdo->commit();
    echo "Done.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    echo 'Seeding failed: ' . $e->getMessage() . "\n";
    exit(1);
}
