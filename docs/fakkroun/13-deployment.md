# Deployment and operations

> **Spec reference:** §31 (deployment), §27 (performance), §28 (security)
> **Status:** written from the code in this branch, **not yet exercised on a server**. Hosting (assessment D7) is still undecided, so this describes processes, not a provider.

## 1. Requirements

- PHP **8.4** with `pdo_mysql`, `mbstring`, `intl`, `gd` (or `imagick`), `zip`, `bcmath`, `pcntl` (needed for `schedule:work` and queue signals).
- MySQL 8 (the Docker setup) or PostgreSQL. SQLite only for tests.
- Node 20+ to build assets (`npm ci && npm run build`).
- A writable `storage/` and `bootstrap/cache/`. `php artisan storage:link` for avatars, partner logos and ad creatives (all on the `public` disk).

## 2. First deploy

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate            # first time only
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=FakkrounCategorySeeder --force
php artisan storage:link
php artisan optimize                # config, route, view and event caches
```

Every later deploy: `composer install …`, `npm run build`, `php artisan migrate --force`, `php artisan optimize`, then `php artisan queue:restart` so workers load the new code.

All Fakkroun migrations are additive. The only one that rewrites data is `2026_11_07_000001_add_priority_and_partners` (it backfills `categories.is_priority`).

## 3. Processes

| Process | Command | Notes |
| --- | --- | --- |
| Web | php-fpm + nginx (`docker/nginx/default.conf`) | Stateless; scale horizontally once sessions and cache are shared (database or Redis). |
| Queue worker | `php artisan queue:work --tries=3 --max-time=3600` | Runs AI generation batches (`GenerateQuestionBatch`), queued notifications and mail. `QUEUE_CONNECTION=database` works; Redis is better under load. Keep at least one worker running. |
| Scheduler | `php artisan schedule:work` (or cron `* * * * * php artisan schedule:run`) | **Required for gameplay**: `games:advance` runs every 5 s, so questions close and games end even when no browser is polling. `schedule:run` from cron handles sub-minute tasks by staying alive for the minute. |
| Reverb | *not installed yet* | See [`04`](04-next-step-realtime-reverb.md). Until then, room pages poll every second. |

Example systemd unit for the worker (the scheduler is the same with `schedule:work`):

```ini
[Unit]
Description=Fakkroun queue worker
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/fakkroun
ExecStart=/usr/bin/php artisan queue:work --tries=3 --max-time=3600
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
```

### Scheduled tasks (`routes/console.php`)

| Command | Frequency | Purpose |
| --- | --- | --- |
| `games:advance` | every 5 s | Closes expired questions, opens the next one, finishes games (idempotent `GameEngine::tick`). |
| `rooms:expire` | every 5 min | Cancels lobbies past `game.room_expiry_minutes`. |
| `advertising:reconcile` | hourly, one server | Completes ended campaigns, then checks impressions/deliveries against the ledger and wallets against ledger totals. The report is shown in **Admin → Advertising → Reconciliation**. |
| `health:check` + heartbeats | every minute | Feeds `/admin/health` (queue and schedule checks fail if those processes stop). |
| `backup:run`, `backup:clean`, `backup:monitor` | daily, when `BACKUP_SCHEDULE_ENABLED` | spatie/laravel-backup (database + `storage/app/public`). |
| `activitylog:clean`, `sitemap:generate` | daily / configured | Inherited from the starter. |

Use `onOneServer()` tasks only with a shared cache store (database or Redis), which is how the scheduler takes its lock.

## 4. Configuration

Required `.env` beyond the Laravel defaults:

| Key | Purpose |
| --- | --- |
| `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` | `APP_URL` is used in invitation links. |
| `DB_*`, `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` | `database` for all three works out of the box. Switch to Redis when there are several web servers. |
| `MAIL_*`, `BACKUP_NOTIFICATION_EMAIL` | Password resets, backup alerts. |
| `ANTHROPIC_API_KEY` (optional) | Enables AI drafting. Without it the AI screens show as disabled. `FAKKROUN_AI_MODEL`, `FAKKROUN_AI_EFFORT` and `FAKKROUN_AI_BATCH_COST_CEILING` tune it. |

Game, scoring, submission, import, translation and **advertising** rules (frequency caps, prices per credit, currency, low-balance threshold) are **not** env vars. Administrators change them in **Admin → Game settings**, and they are stored in `settings.platform`.

## 5. Health checks

- `GET /up`: Laravel's liveness endpoint (boots the app; no database). Use it for the load balancer.
- `/admin/health`: spatie/laravel-health checks (database, cache, disk, queue and schedule heartbeats). It's the readiness view for people.
- Fakkroun-specific signals to alert on:
  - queue heartbeat older than 5 minutes (AI batches and notifications stop);
  - schedule heartbeat older than 2 minutes (**games stop advancing**);
  - reconciliation report `ok = false` (billing mismatch: investigate before adjusting credits).

## 6. Backups and restore

- `config/backup.php` backs up the database and `storage/app/public` (avatars, logos, creatives). Keep at least one off-site destination.
- **Restore drill** (do it once before launch, then quarterly): restore the latest archive into a staging database, run `php artisan migrate:status`, sign in as an admin, and run `php artisan advertising:reconcile`. The report must be `ok`; the ledger is the source of truth for credits.
- Never edit `advertising_ledger_entries` by hand. Corrections are adjustments or reversals from the admin screen, and the model refuses updates and deletes.

## 7. Security checklist before launch

- `APP_DEBUG=false`. HTTPS only (`SESSION_SECURE_COOKIE=true`).
- Sign-up throttling and login throttling are inherited. Room join (`/join/{code}`) is throttled to 30/min.
- Uploaded files: avatars, partner logos and ad creatives are images only, size-limited and stored under random names. They are **not re-encoded** (no image library installed).
- Ad creative links must be `https://` and are reviewed by an admin before any player sees them.
- Run `composer audit` and `npm audit` in CI once Actions work ([`03`](03-next-step-run-ci.md)).
- Error monitoring (e.g. Sentry) is not configured. Add it before launch.

## 8. Capacity notes

- Polling is the main load: one Livewire request per player per second in a running game. Plan for `players × 1 req/s` until Reverb lands.
- `games:advance` touches only running games due for a transition (`LeaderboardService::gamesDue`), so its cost scales with live games, not history.
- The analytics page queries raw tables over the selected period (default 30 days). That's fine for a launch-sized platform. Add the daily roll-ups from [`10`](10-next-step-analytics-and-hardening.md) when `player_answers` passes a few million rows.
