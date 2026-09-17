# Quiz App

A quiz app with a plain PHP backend and a static frontend. No framework, no
Composer packages — everything is hand-rolled on purpose.

## Structure

- `frontend/` — static frontend (`index.html`).
- `backend/main.php` — entry point (currently empty, not yet wired up).
- `backend/config/database.php` — local DB credentials. **Gitignored.** Copy
  `database.example.php` to create it on a new machine.
- `backend/database/connection.php` — `get_pdo_connection()`, returns a PDO
  instance built from `backend/config/database.php`.
- `backend/database/migrations/` — one file per migration, named
  `YYYY_MM_DD_NNNNNN_description.php`. Each file returns `['up' => sql, 'down' => sql]`.
- `backend/database/migrate.php` — the migration runner (see below).

## Database

Running MySQL (not MariaDB). Database name: `quiz_app`.

The database itself is not created automatically — it must exist before
migrations can run. On a fresh machine:

```
mysql -u root -p -e "CREATE DATABASE quiz_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

(or equivalent through phpMyAdmin/another GUI).

### Migrations

Plain-PHP migration system (no framework): a `migrations` table tracks which
migration files have already run, keyed by filename.

```
php backend/database/migrate.php            # run pending migrations
php backend/database/migrate.php rollback   # roll back the most recent one
```

Tables so far:
- `users` — `id`, `username`, `email`, `password_hash`, `total_score`,
  `created_at`. Unique on `username` and `email`.

## Conventions

- No Composer, no framework. Keep additions plain PHP unless there's a
  concrete reason to add a dependency.
- Never commit real DB credentials — `backend/config/database.php` is
  gitignored for this reason; keep `database.example.php` in sync with its
  shape (not its values) when the config changes.
