# Quiz App

A quiz app with a plain PHP backend and a static frontend. No framework, no
Composer packages — everything is hand-rolled on purpose.

## Structure

- `frontend/` — static frontend (`index.html`). Not yet wired up to the API.
- `backend/main.php` — front controller / router. All `/api/*` requests are
  dispatched from here to handler functions in `backend/src/routes/`.
- `backend/config/database.php` — local DB credentials. **Gitignored.** Copy
  `database.example.php` to create it on a new machine.
- `backend/database/connection.php` — `get_pdo_connection()`, returns a PDO
  instance built from `backend/config/database.php`. Also creates the
  `quiz_app` database itself (`CREATE DATABASE IF NOT EXISTS`) if it doesn't
  exist yet, before connecting to it.
- `backend/database/migrations/` — one file per migration, named
  `YYYY_MM_DD_NNNNNN_description.php`. Each file returns `['up' => sql, 'down' => sql]`.
- `backend/database/migrate.php` — the migration runner (see below).
- `backend/database/promote_admin.php` — CLI helper: `php backend/database/promote_admin.php you@example.com`
  sets that user's role to `admin`. There's no signup flow for admins on
  purpose; you register a normal account then promote it.
- `backend/src/response.php` — `json_response()`, `json_error()`, `read_json_body()`.
- `backend/src/auth.php` — session helpers: `current_user()`, `require_auth()`,
  `require_admin()`.
- `backend/src/scoring.php` — `QUESTIONS_PER_QUIZ` (10), `STARTING_LIVES` (3),
  `DIFFICULTY_POINTS` (easy 10 / medium 15 / hard 20).
- `backend/src/routes/*.php` — one file per resource (`auth`, `quiz`,
  `leaderboard`, `quiz_sets`, `admin_questions`), each just a set of
  `handle_*` functions taking `PDO $pdo` (and any route params) and calling
  `json_response`/`json_error`.

## Running it locally

```
php -S 127.0.0.1:8000 -t frontend backend/main.php
```

This serves `frontend/` as static files and routes everything else through
`backend/main.php` as a router script — PHP's built-in server returns static
files as-is when the router returns `false` for non-`/api/` paths (see the
top of `main.php`). Frontend and API end up on the same origin
(`http://127.0.0.1:8000`), so no CORS setup is needed. This is the intended
way to run the app; there's no other web server config (no `.htaccess`, no
nginx config).

## Tests

```
php backend/tests/smoke_test.php
```

A dependency-free smoke test (`backend/tests/smoke_test.php`): it starts its
own dev server on port 8099, seeds throwaway quiz sets/questions/users
prefixed `smoke-`, drives the real HTTP API (register, login, admin question
CRUD, quiz set CRUD, quiz start/answer/scoring/lives, leaderboard, admin auth
gating) with curl, then deletes everything it created and shuts its server
down — safe to run against your real dev database. Prints `[PASS]`/`[FAIL]`
per check and a final `N passed, M failed`; exits non-zero if anything
failed. Add new checks as another `run_test('description', function () {
... })` block; use `assert_status()`/`assert_true()` for assertions.

## Database

Running MySQL (not MariaDB). Database name: `quiz_app`. The database is
created automatically on first connect (see `connection.php` above), so a
fresh machine just needs a working `backend/config/database.php` and MySQL
running.

### Migrations

Plain-PHP migration system (no framework): a `migrations` table tracks which
migration files have already run, keyed by filename.

```
php backend/database/migrate.php            # run pending migrations
php backend/database/migrate.php rollback   # roll back the most recent one
```

### Schema

- `users` — `id`, `username`, `email`, `password_hash`, `role` (`user`/`admin`,
  default `user`), `total_score`, `created_at`. Unique on `username` and
  `email`. `total_score` is the sum of `score` across all of a user's
  *completed* `quiz_attempts` — it's incremented once, when an attempt
  finishes, not tracked live during the quiz.
- `quiz_sets` — a named, curated quiz (e.g. "Croatia Geography", "Dutch
  Culture"): `id`, `name`, `slug` (unique, lowercase/hyphens), `description`
  (nullable), `created_at`.
- `questions` — `id`, `quiz_set_id` (which quiz it belongs to — every question
  belongs to exactly one set; `ON DELETE RESTRICT`, so a quiz set that still
  has questions can't be deleted, mirrored by the admin delete endpoint's 409),
  `difficulty` (`easy`/`medium`/`hard`), `question_text`, `options` (JSON
  array of strings), `correct_index` (into `options`), `created_at`.
- `quiz_attempts` — one row per quiz a user plays: `id`, `user_id`, `status`
  (`in_progress`/`completed`), `lives` (starts at 3), `score`, `quiz_set_id`
  (which quiz set the attempt was started from, nullable — null means it was
  started unfiltered across all questions), `started_at`, `completed_at`.
  This table *is* the quiz-results table — there's no separate results table.
- `quiz_attempt_questions` — the 10 questions assigned to one attempt:
  `quiz_attempt_id`, `question_id`, `order_index` (0–9, answered in order),
  `selected_index`/`is_correct`/`points_awarded`/`answered_at` (all null until
  answered). Unique on `(quiz_attempt_id, order_index)` and
  `(quiz_attempt_id, question_id)` — no repeats within an attempt.
  `question_id` has `ON DELETE RESTRICT`, so a question that's been used in
  any attempt can't be deleted (the admin delete endpoint turns that FK error
  into a 409).

## API

All request/response bodies are JSON. Auth is PHP session cookies
(`require_auth`/`require_admin` in `src/auth.php`) — no bearer tokens.

- `POST /api/auth/register` — `{username, email, password}` → creates the
  user (role `user`), logs them in (sets the session), 201.
- `POST /api/auth/login` — `{email, password}` → 200 + user, or 401.
- `POST /api/auth/logout` — destroys the session.
- `GET /api/auth/me` — current user, or 401 if not logged in.
- `GET /api/quiz/start?quiz_set_id=` — requires auth. If the user already has
  an `in_progress` attempt, **resumes** it (ignores `quiz_set_id` in that
  case) instead of starting a second one. Otherwise picks 10 random questions
  (filtered by `quiz_set_id` if given — 422 if fewer than 10 match; omit it
  to pick from all questions regardless of set) and creates a new attempt.
  Returns attempt state + the current question (without `correct_index`).
- `POST /api/quiz/{attemptId}/answer` — requires auth, must own the attempt.
  `{question_id, selected_index}`. `question_id` must be the *current*
  (first unanswered, in order) question for the attempt, or this 409s — the
  client can't skip ahead or answer out of order. Applies scoring
  (`DIFFICULTY_POINTS`) and lives (-1 on wrong), completes the attempt
  (and adds its score to `users.total_score`) once lives hit 0 or all 10
  are answered. Returns whether the answer was correct, the correct index,
  points awarded, and the updated attempt state (incl. next question).
- `GET /api/leaderboard?limit=10` — public. Top users by `total_score`.
- `GET /api/quiz-sets` — public. All quiz sets (`id`, `name`, `slug`,
  `description`, `created_at`).
- `GET /api/admin/questions` — admin only. Full question list incl.
  `correct_index` and `quiz_set_id`.
- `POST /api/admin/questions` — admin only. `{question_text, options[],
  correct_index, difficulty, quiz_set_id}`. 422 if `quiz_set_id` doesn't
  reference an existing quiz set.
- `PUT /api/admin/questions/{id}` — admin only. Partial update, any subset of
  the same fields.
- `DELETE /api/admin/questions/{id}` — admin only. 409 if the question is
  referenced by any past attempt.
- `POST /api/admin/quiz-sets` — admin only. `{name, slug, description?}`.
  `slug` must be lowercase letters/numbers/hyphens; 409 on duplicate slug.
- `PUT /api/admin/quiz-sets/{id}` — admin only. Partial update, any subset of
  the same fields.
- `DELETE /api/admin/quiz-sets/{id}` — admin only. 409 if the quiz set still
  has questions assigned to it.

## Not built yet

- Frontend integration (frontend/index.html doesn't call the API yet).
- Daily challenge and level-unlocking (both were flagged optional and
  deferred).

## Conventions

- No Composer, no framework. Keep additions plain PHP unless there's a
  concrete reason to add a dependency.
- Never commit real DB credentials — `backend/config/database.php` is
  gitignored for this reason; keep `database.example.php` in sync with its
  shape (not its values) when the config changes.
- New endpoints: add a `handle_*` function to the relevant file in
  `backend/src/routes/` (or a new file, required from `main.php`), then add
  its route to the `$routes` table in `main.php`. Use `require_auth`/
  `require_admin` from `src/auth.php` for anything that isn't public.
