<?php

function handle_categories(PDO $pdo): void
{
    $categories = $pdo->query('SELECT DISTINCT category FROM questions ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
    json_response($categories);
}

function handle_countries(PDO $pdo): void
{
    $countries = $pdo->query('SELECT DISTINCT country FROM questions WHERE country IS NOT NULL ORDER BY country')->fetchAll(PDO::FETCH_COLUMN);
    json_response($countries);
}
