# Quiz App

A quiz app with a plain PHP backend and a static frontend. No framework, no
Composer packages — everything is hand-rolled on purpose.

## Structure

- `frontend/` — static frontend. `home.html` is the real entry point (there's
  no `index.html`). Logged-out and logged-in visitors get separate HTML
  files for the same page (e.g. `home.html` / `home-logedin.html`,
  `categories.html` / `categories_logout.html`,
  `leaderboard.html` / `leaderboard_logout.html`); each guards itself on load
  by calling `GET /api/me.php` and redirecting to its counterpart if the
  session doesn't match. `singin_user.html` is the canonical login page
  (`login_user.html` is a redirect stub kept for old links). `rules2.html` is
  shared by both logged-in and logged-out flows and swaps its own nav at
  runtime instead of having a duplicate file.
- `frontend/source/api.js` — shared fetch helper (`apiFetch`,
  `getCurrentUser`, `guardLoggedIn`/`guardLoggedOut`, `renderUserBadge`,
  `logoutUser`) used by every page that talks to the backend.
- `frontend/source/questions.js`, `leaderboard.js`, `categories.js`,
  `quiz-create.js` — per-page logic that calls the API below.
- `frontend/source/questions.json`, `category_questions.json` — the original
  static question banks. Only used once, by `backend/database/seed.php`, to
  populate the `questions` table; the app itself reads questions from the DB.
- `backend/main.php` — entry point (currently empty; all HTTP traffic goes
  through `backend/api/*.php` instead — see below).
- `backend/api/` — one plain-PHP file per endpoint, each starting with
  `require __DIR__ . '/bootstrap.php';`. `bootstrap.php` starts the PHP
  session, and provides `json_body()`, `respond()`/`respond_error()`,
  `require_method()`, `current_user()`/`require_auth()`, and the shared
  `validate_questions_payload()` / `resolve_category_id()` helpers. Auth is a
  plain PHP session (`$_SESSION['user_id']`) — no tokens, no framework.
  Endpoints: `signup.php`, `login.php`, `logout.php`, `me.php`,
  `forgot_password.php` (no real email sending — see its file),
  `categories.php`, `questions.php` (GET list/filter by `?category=slug`,
  POST create), `quizzes.php` (GET list, POST create — used by
  `quiz-create.html`), `quiz_attempts.php` (POST — records a finished quiz
  and bumps `users.total_score`), `leaderboard.php` (GET, ranked by
  `total_score`).
- `backend/config/database.php` — local DB credentials. **Gitignored.** Copy
  `database.example.php` to create it on a new machine.
- `backend/database/connection.php` — `get_pdo_connection()`, returns a PDO
  instance built from `backend/config/database.php`.
- `backend/database/migrations/` — one file per migration, named
  `YYYY_MM_DD_NNNNNN_description.php`. Each file returns `['up' => sql, 'down' => sql]`.
- `backend/database/migrate.php` — the migration runner (see below).
- `backend/database/seed.php` — one-off data import: loads
  `frontend/source/questions.json` and `category_questions.json` into the
  `questions`/`categories` tables. Safe to re-run (skips a source once it's
  already seeded). Run once per fresh database: `php backend/database/seed.php`.

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
  `created_at`. Unique on `username` and `email`. `total_score` is a running
  total, incremented by `POST /api/quiz_attempts.php` on every finished quiz
  by a logged-in user; it's what the leaderboard orders by.
- `categories` — `id`, `slug`, `name`, `emoji`, `color` (`sky`/`red`/`amber`,
  used for nav/card styling), `created_at`. Seeded from
  `category_questions.json` (the 8 topics behind the "Question Database" page:
  Geography & Nature, National Symbols, Culture & Traditions, Art &
  Literature, Science & Inventors, Business & Economy, History & Politics,
  Sports & Celebrities).
- `questions` — `id`, `category_id` (nullable FK → `categories`, NULL = the
  general/uncategorized trivia pool from the original `questions.json`),
  `quiz_id` (nullable FK → `quizzes`), `difficulty`
  (`easy`/`normal`/`hard`), `question`, `option_a..d`, `correct_option`
  (`A`-`D`), `created_by` (nullable FK → `users`), `created_at`.
- `quizzes` — `id`, `title`, `description`, `category_id` (nullable FK),
  `difficulty`, `created_by` (nullable FK → `users`), `created_at`. Created
  by `quiz-create.html`; its questions live in `questions` with matching
  `quiz_id`.
- `quiz_attempts` — `id`, `user_id` (nullable FK — NULL for guests, who can
  play but don't get saved to the leaderboard), `category_id` (nullable FK),
  `score`, `correct_answers`, `hearts_used`, `played_at`. One row per
  finished quiz; `leaderboard.php` joins on this for each player's
  "quizzes played" count.

After migrating, run `php backend/database/seed.php` once to populate
`categories`/`questions` from the JSON files in `frontend/source/`.

## Conventions

- No Composer, no framework. Keep additions plain PHP unless there's a
  concrete reason to add a dependency.
- Never commit real DB credentials — `backend/config/database.php` is
  gitignored for this reason; keep `database.example.php` in sync with its
  shape (not its values) when the config changes.
