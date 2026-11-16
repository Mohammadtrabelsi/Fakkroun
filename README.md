# Fakkroun (فكّرون)

**Think, Answer, Learn, Win!** Fakkroun is a multilingual (Arabic, French, English) real-time quiz platform focused on the green economy, the blue economy and environmental awareness. Players join live games with a room code or an invitation link, and no account is needed to play.

Fakkroun is built on the **MajidaStarter** kit (Laravel 13, Livewire 4). The kit's own reference is in [`docs/starter-kit.md`](docs/starter-kit.md).

## Status

- **Playable MVP:** question bank, moderation, community submissions, live multiplayer rooms, scoring, results, advertising and analytics are implemented.
- **Tests:** the suite passed locally on 2026-11-11 (664 tests). PHPStan still reports open errors.
- **CI:** GitHub Actions had not started a job when that status was recorded. Confirm the current run before relying on it. See [`docs/fakkroun/03-next-step-run-ci.md`](docs/fakkroun/03-next-step-run-ci.md).
- **Roadmap and phase reports:** [`docs/fakkroun/`](docs/fakkroun), starting with [`02-mvp.md`](docs/fakkroun/02-mvp.md) and [`15-roadmap.md`](docs/fakkroun/15-roadmap.md).

## Features

| Area | What it does |
| --- | --- |
| **Live games** | Hosts create a room (topics, number of questions, seconds per question, difficulty, language, maximum players). Each room gets a 6-character code, an invitation link and a QR code. The lobby offers copy, Web Share and WhatsApp. The server sets all deadlines and scores; clients only send a choice. |
| **Guest play** | Anyone can join a room with a code or invitation link and a nickname. Creating a room needs an account. |
| **Duplicate-answer rule** | When enough players give the same answer, none of those answers score. The threshold is set in admin settings. |
| **Scoring and results** | Points by difficulty, plus a speed bonus. Running leaderboard, final ranking and per-player statistics. Game history, achievements and a public leaderboard (weekly and all-time). |
| **Question bank** | Four-choice questions with a translation per language, each approved on its own. Lifecycle: draft, pending, review, approved, published. Revision history, exact and near-duplicate detection, and import and export (CSV, XLSX, JSON). |
| **Moderation** | A queue for questions, translations and player reports. Moderators are limited to their categories. Every decision is logged. |
| **Community** | Players submit questions and follow their status. Authors are notified of the decision. |
| **AI drafting** | Staff can generate question drafts with the Anthropic API. Drafts always start as drafts in review. The feature is hidden when no API key is set. |
| **Advertising and billing** | Advertiser dashboard, campaigns, partners, plans and ad click tracking. Payments use a pluggable provider; the default is `cash`. |
| **Analytics** | Admin dashboards for games, players, answers, correct rate, response time, categories and languages, plus question-bank health and advertising. |
| **Kit foundation** | Sign-in with two-factor, roles and permissions, activity log, notifications, backups, health checks, blog, RSS, sitemap, REST API, light and dark themes, and English, French and Arabic with right-to-left layout. See [`docs/starter-kit.md`](docs/starter-kit.md). |

A recorded clip of a live game is in [`docs/fakkroun/demo/fakkroun-demo.mp4`](docs/fakkroun/demo/fakkroun-demo.mp4) (1 min 40 s, host on a laptop, two guests on phones).

## Requirements

- PHP 8.4 or higher, with `mbstring`, `pdo`, `intl` and `bcmath` (CI also uses `pdo_sqlite` and `sqlite3`)
- Composer
- Node.js 20 or newer and npm

## Quick start

```bash
# 1. Install dependencies, create .env, generate the app key, run migrations and build assets.
#    Set your database in .env first (see below), because migrations run here.
composer setup

# 2. Seed the database. Pick one:
php artisan db:seed --class=ConfigDatabaseSeeder  # roles, permissions, admin, 14 categories, advertiser packs
php artisan db:seed                               # the above, plus demo users, published sample questions and a demo game

# 3. Make uploaded files (cover images, logos) public.
php artisan storage:link

# 4. Start the development environment (PHP server, queue worker, log tail and Vite).
composer dev
```

To try it with SQLite, set this in `.env` before `composer setup`, then create the file:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

```bash
touch database/database.sqlite
```

### Sign in

| Who | Where | Account |
| --- | --- | --- |
| Staff (admins, moderators, question writers) | `/admin/login` | `admin@fakkroun.test` / `password` |
| Players | `/login` | Register at `/register`, or use `test@example.com` / `password` after the full seed |

Change the admin password before deploying. Set `ADMIN_EMAIL`, `ADMIN_NAME` and `ADMIN_PASSWORD` in `.env` and re-run `AdminUserSeeder`. Details are in [`docs/starter-kit.md`](docs/starter-kit.md#admin-login).

### Try a game

1. Sign in at `/admin/login`, then open **Moderation** to check the sample questions.
2. In a second browser, register a player account or open the room's invitation link.
3. Create a room from `/play/host` and start the game from the lobby.

## Configuration

Common settings are in `.env.example`. The keys below are specific to Fakkroun.

| Variable | Default | Purpose |
| --- | --- | --- |
| `ANTHROPIC_API_KEY` | empty | Enables AI question drafting. Without it the AI screens stay hidden. |
| `FAKKROUN_AI_MODEL` | `claude-opus-5-5` | Model used for drafting. |
| `FAKKROUN_AI_EFFORT` | `medium` | Reasoning effort for drafting. |
| `FAKKROUN_AI_BATCH_COST_CEILING` | `20.0` | Maximum estimated cost of one batch run. |
| `FAKKROUN_PAYMENT_DRIVER` | `cash` | Payment provider behind the billing contract. |
| `FAKKROUN_TERMS_DRAFT` | `true` | Marks the terms page as a draft. |
| `OWNER_PASSWORD` | random | Password for `OwnerUserSeeder` (run it on its own). |

The starter kit's settings (queues, backups, health checks, sitemap, feed, PDF, honeypot and others) are listed in [`docs/starter-kit.md`](docs/starter-kit.md#environment-variables).

For production, see [`docs/fakkroun/13-deployment.md`](docs/fakkroun/13-deployment.md) and the notes in [`docs/starter-kit.md`](docs/starter-kit.md#production-notes). The Docker setup is described in [`README.docker.md`](README.docker.md).

## Architecture

```
app/
├── Domain/                 # Fakkroun domain code, one folder per area
│   ├── Advertising/        # Campaigns, ad delivery and clicks
│   ├── Ai/                 # Question drafting (Anthropic API)
│   ├── Analytics/          # Admin statistics
│   ├── Billing/            # Plans, ledger and payment provider contract
│   ├── Gameplay/           # Rooms, game engine, scoring, duplicate rule
│   └── Questions/          # Question bank, moderation, submissions, imports
├── Services/               # Starter-kit services, one per model
├── Http/Controllers/Api/   # The only controllers: JSON API
└── Models/, Policies/, Observers/, Notifications/, ...

resources/views/pages/      # Livewire single-file components: every web page
database/                   # Migrations, factories and seeders
routes/web.php              # Web routes, grouped by access level
routes/api.php              # JSON API
lang/{en,fr,ar}/            # Translations
docs/                       # Project documentation
```

- **Pages are Livewire components** in `resources/views/pages/`. They validate input, check authorization and delegate to services.
- **Business logic lives in services** (`app/Services` and `app/Domain`). Models are read and written through their own service. `tests/Feature/Architecture/ServiceLayerTest.php` checks this.
- **Access control** uses policies per model and permissions from `spatie/laravel-permission`. Admin routes sit behind the `backoffice` middleware.
- **Translations** are per feature, with `__()` for every visible string. `php artisan translations:sync --dry-run` lists missing keys.

## API

JSON endpoints live under `/api` and are defined in [`routes/api.php`](routes/api.php). Admin endpoints use the same permission gates as the web screens. Question submission endpoints need the `submit questions` permission.

- **Authentication:** `POST /api/auth/token` returns a bearer token (`Authorization: Bearer <token>`), and `DELETE /api/auth/token` revokes it. Session-cookie sign-in is available at `POST /api/auth/login`. Two-factor codes are required when two-factor is on.
- **Throttling:** register, login and token requests are limited to 6 per minute per email and IP.
- **Resources:** profile, question submissions and reports, categories, posts, users (including suspend and reactivate) and settings.
- **Errors** are always JSON. In maintenance mode, every endpoint returns `503` except for administrators.

## Testing and quality

```bash
composer test          # or: php artisan test
vendor/bin/pint --dirty   # code style, changed files only
composer analyse          # Larastan (PHPStan)
```

Tests run against an in-memory SQLite database (see `phpunit.xml`).

## Contributing

- Branch from `develop`. Open pull requests into `develop`. Do not target `main` directly.
- Commit messages use `type(scope): subject`, for example `feat(questions): add bulk import`.
- House rules for code and reviews are in [`CLAUDE.md`](CLAUDE.md).

## Continuous integration

`.github/workflows/ci.yml` runs on pushes to `develop` and `main`, and on every pull request. It runs the test suite on PHP 8.4 (after building front-end assets with Node 20), then Pint and Larastan. Check the latest run on GitHub, since the status above may be out of date.

## Documentation

| Document | Contents |
| --- | --- |
| [`docs/fakkroun/02-mvp.md`](docs/fakkroun/02-mvp.md) | The playable MVP: features, how to try it, code map, tests |
| [`docs/fakkroun/14-final-report.md`](docs/fakkroun/14-final-report.md) | Report across phases 1 to 6 |
| [`docs/fakkroun/13-deployment.md`](docs/fakkroun/13-deployment.md) | Deployment guide |
| [`docs/fakkroun/15-roadmap.md`](docs/fakkroun/15-roadmap.md) | Open work and next steps |
| [`docs/starter-kit.md`](docs/starter-kit.md) | MajidaStarter kit reference: features, configuration, roles, localization, scaffolding |
| [`docs/spatie-integrations.md`](docs/spatie-integrations.md) | Slugs, SEO, tags, RSS, honeypot, backups, health, PDF, spreadsheets |
| [`docs/feature-audit-and-improvements.md`](docs/feature-audit-and-improvements.md) | Feature audit and improvement backlog |
| [`docs/questions/`](docs/questions) | Question-writing guides |

## License

Fakkroun is built on MajidaStarter, which is open-source under the [MIT license](https://opensource.org/licenses/MIT). It is built on the [Laravel framework](https://laravel.com), which is also MIT licensed.
