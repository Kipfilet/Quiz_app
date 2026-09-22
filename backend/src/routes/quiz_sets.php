<?php

function handle_list_quiz_sets(PDO $pdo): void
{
    $rows = $pdo->query('SELECT id, name, slug, description, created_at FROM quiz_sets ORDER BY name')->fetchAll();

    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
    }

    json_response($rows);
}

function validate_quiz_set_input(array $body, bool $partial = false): array
{
    $errors = [];
    $data = [];

    if (!$partial || array_key_exists('name', $body)) {
        $data['name'] = trim($body['name'] ?? '');
        if ($data['name'] === '') {
            $errors[] = 'name is required';
        }
    }

    if (!$partial || array_key_exists('slug', $body)) {
        $data['slug'] = trim($body['slug'] ?? '');
        if ($data['slug'] === '' || !preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $data['slug'])) {
            $errors[] = 'slug is required and must be lowercase letters, numbers and hyphens only';
        }
    }

    if (array_key_exists('description', $body)) {
        $description = trim((string) $body['description']);
        $data['description'] = $description !== '' ? $description : null;
    }

    if ($errors) {
        json_error(implode('; ', $errors));
    }

    return $data;
}

function handle_admin_create_quiz_set(PDO $pdo): void
{
    require_admin($pdo);

    $data = validate_quiz_set_input(read_json_body());

    try {
        $stmt = $pdo->prepare('INSERT INTO quiz_sets (name, slug, description) VALUES (?, ?, ?)');
        $stmt->execute([$data['name'], $data['slug'], $data['description'] ?? null]);
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000) {
            json_error("slug '{$data['slug']}' is already in use", 409);
        }
        throw $e;
    }

    $data['id'] = (int) $pdo->lastInsertId();
    json_response($data, 201);
}

function handle_admin_update_quiz_set(PDO $pdo, int $id): void
{
    require_admin($pdo);

    $stmt = $pdo->prepare('SELECT id FROM quiz_sets WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        json_error('Quiz set not found', 404);
    }

    $data = validate_quiz_set_input(read_json_body(), true);

    if (!$data) {
        json_error('No fields to update');
    }

    $fields = [];
    $params = [];
    foreach ($data as $column => $value) {
        $fields[] = "{$column} = ?";
        $params[] = $value;
    }
    $params[] = $id;

    try {
        $pdo->prepare('UPDATE quiz_sets SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000) {
            json_error("slug '{$data['slug']}' is already in use", 409);
        }
        throw $e;
    }

    json_response(['success' => true]);
}

function handle_admin_delete_quiz_set(PDO $pdo, int $id): void
{
    require_admin($pdo);

    try {
        $stmt = $pdo->prepare('DELETE FROM quiz_sets WHERE id = ?');
        $stmt->execute([$id]);
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000) {
            json_error('This quiz set still has questions assigned to it and cannot be deleted', 409);
        }
        throw $e;
    }

    if ($stmt->rowCount() === 0) {
        json_error('Quiz set not found', 404);
    }

    json_response(['success' => true]);
}
