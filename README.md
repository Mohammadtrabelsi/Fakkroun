# MajidaStarter

MajidaStarter is a batteries-included **Laravel 13 + Livewire 4** starter kit. It ships with everything you usually rebuild at the start of every project — authentication with two-factor support, an admin panel, role-based access control, activity logging, multilingual (LTR/RTL) support, light/dark theming, a content module (posts & categories), a matching REST API, and a clean service layer — so you can skip the boilerplate and start building features.

## Highlights

- **Full authentication flow** — registration, login, logout, password reset, and email verification, all built as single-file Livewire components. Login is rate-limited per email and IP.
- **Two-factor authentication** — TOTP apps (RFC 6238) with single-use recovery codes, managed from **Settings → Two-factor** and enforced at web and API sign-in once confirmed. No extra dependency is needed.
- **Admin panel** — a dedicated, permission-gated back office for managing users, posts, categories, reviewing activity, and editing site settings.
- **Content module** — full CRUD for **posts** and **categories** in the admin panel. Published posts appear on a public `/blog` with search, detail pages and cover images; `/docs` is the in-app guide. Deletes go to a **trash** and can be restored; bulk publish/delete and filters/sorting are built into the admin lists.
- **Role & permission based access control** — powered by [spatie/laravel-permission](https://spatie.be/docs/laravel-permission), with an `admin` role seeded out of the box.
- **Activity logging** — every meaningful change to users, posts, categories, and settings is recorded with [spatie/laravel-activitylog](https://spatie.be/docs/laravel-activitylog) and browsable from the admin panel.
- **Activity log export & retention** — download the filtered activity log as CSV or JSON; `activitylog:clean` runs daily via the scheduler.
- **CRUD notifications** — admins receive a stored notification when posts, categories, users or settings are created, updated, deleted or restored, and can review them in a bell-linked notifications page. The acting user is never notified about their own change. See [Feature notes](#feature-notes).
- **XML sitemap** — `php artisan sitemap:generate` writes `/sitemap.xml` (via [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap)) from the public pages and published posts; it is rebuilt hourly by the scheduler and after post changes, and declared in a dynamic `/robots.txt`. See [Feature notes](#feature-notes).
- **Tags** — translatable post tags via [spatie/laravel-tags](https://spatie.be/docs/laravel-tags): a reusable tag picker on the post form, an admin tag screen, and filtering in the admin list, on `/blog?tag=` and in the API.
- **SEO structured data & RSS** — schema.org JSON-LD (Organization, WebSite, BreadcrumbList, BlogPosting) with [spatie/schema-org](https://github.com/spatie/schema-org), canonical/Open Graph tags, and an RSS feed at `/feed` with [spatie/laravel-feed](https://github.com/spatie/laravel-feed). Slugs are generated and kept stable by [spatie/laravel-sluggable](https://github.com/spatie/laravel-sluggable).
- **Spam protection** — the register and forgot-password forms carry a honeypot and timing check ([spatie/laravel-honeypot](https://github.com/spatie/laravel-honeypot)).
- **Backups & health monitoring** — scheduled, monitored database + upload backups ([spatie/laravel-backup](https://spatie.be/docs/laravel-backup)) and application health checks ([spatie/laravel-health](https://spatie.be/docs/laravel-health)), each with an admin page; a token-protected `/health.json` for uptime monitors.
- **PDF & spreadsheets** — post PDFs from a shared Blade layout ([spatie/laravel-pdf](https://spatie.be/docs/laravel-pdf), DomPDF driver), streamed CSV/XLSX exports and a validated category import ([spatie/simple-excel](https://github.com/spatie/simple-excel)). See **[docs/spatie-integrations.md](docs/spatie-integrations.md)** for these features in detail.
- **Visit statistics** — page views on the public site are counted (bots, admin, API and asset requests are excluded) and shown on the dashboards.
- **System health** — the admin dashboard checks the database, cache and queue, and `/up` exposes a liveness endpoint for uptime monitors.
- **REST API** — every service is exposed as JSON endpoints under `/api`, protected by the same authorization gates as the web UI. Sessions and Sanctum personal access tokens are both accepted (see [API](#api)).
- **Localization (i18n) with RTL support** — English, Arabic, and French ship by default, with a language switcher, per-session locale persistence, and automatic text-direction handling.
- **Translatable settings** — site name and description are stored per-locale via [spatie/laravel-translatable](https://spatie.be/docs/laravel-translatable).
- **Light / dark theme toggle** — a persistent, no-flash theme switcher.
- **Maintenance mode** — toggle it in the admin settings; visitors get a 503 page (or JSON on `/api`), while administrators, sign-in, language switching and `/up` keep working.
- **Soft deletes & trash** — users, posts, and categories are soft-deleted, can be restored, and can be deleted permanently.
- **Policies** — post and category access is enforced by `PostPolicy` / `CategoryPolicy` (post editors can only change their own posts unless they are admins).
- **Queued mail** — verification and password-reset emails are sent on the queue.
- **CRUD scaffolding** — `php artisan starter:make-crud Article` generates the model, migration, factory, service, policy and a test for a new resource.
- **Service-oriented architecture** — business logic lives in dedicated service classes (`UserService`, `PostService`, `CategoryService`, `SettingService`, and others) instead of controllers or components.
- **Modern front-end** — Tailwind CSS 4 and Vite 8, wired up and ready to go.
- **Tested & analysed** — a PHPUnit feature suite covering auth, two-factor, profiles, admin pages, user/post/category management, the API, notifications, maintenance mode and localization, plus Pint (code style) and Larastan (static analysis), all run on CI.

## Tech Stack

| Layer              | Technology                                                         |
| ------------------ | ------------------------------------------------------------------ |
| Framework          | Laravel 13 (PHP 8.4+)                                              |
| UI / Interactivity | Livewire 4 (single-file components)                                |
| Styling / Build    | Tailwind CSS 4, Vite 8                                             |
| Auth & Access      | Laravel Auth, Sanctum (API tokens), spatie/laravel-permission      |
| Auditing           | spatie/laravel-activitylog                                         |
| i18n               | spatie/laravel-translatable + custom locale middleware             |
| Database           | Any Laravel-supported driver. `config/database.php` falls back to SQLite; `.env.example` is preset to MySQL |
| Cache / queue / session | `database` by default (Redis is supported through `.env`)     |
| Testing            | PHPUnit 12                                                         |
| Code quality       | Laravel Pint + Larastan (PHPStan)                                  |

## Requirements

- PHP 8.4 or higher (the `composer.lock` resolves Symfony 8.1, `spatie/laravel-activitylog` 5 and `spatie/laravel-sitemap` 8.2, which require PHP 8.4), with the `mbstring`, `pdo`, `intl` and `bcmath` extensions (CI also uses `pdo_sqlite` and `sqlite3` for the test suite)
- Composer
- Node.js 20 or newer & npm (CI builds with Node 20)

## Getting Started

```bash
# 1. Install PHP and JS dependencies, create .env, generate a key,
#    run migrations, and build assets — all in one step:
composer setup

# 2. Seed the database: the admin account, roles & permissions (always),
#    plus a test user and 10 sample users (demo data)
php artisan db:seed

# 3. Start the full development environment
#    (server + queue worker + log tailer + Vite)
composer dev
```

The `composer setup` script runs `composer install`, copies `.env.example` to `.env` (if missing), generates the application key, runs migrations, and builds the front-end assets. The `composer dev` script uses [concurrently](https://www.npmjs.com/package/concurrently) to run the PHP server, queue listener, `pail` log viewer, and the Vite dev server together.

`.env.example` is preset to MySQL on `127.0.0.1`, and `composer setup` runs migrations, so set the database first. For a quick local run with SQLite, edit `.env` before running setup:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

Then create the file with `touch database/database.sqlite` and run `composer setup`.

### Production notes

- **Cover images** are stored on the `public` disk. Run `php artisan storage:link` once per environment.
- **Queues:** mail is queued (`QUEUE_CONNECTION=database` by default). Run `php artisan queue:work` as a long-running process; `composer dev` starts one for you locally. Without a worker, verification and reset emails are not sent.
- **Scheduler:** run `php artisan schedule:work` locally, or add `* * * * * php /path/to/artisan schedule:run` to cron in production. The scheduler prunes the activity log, rebuilds the sitemap, runs the health checks every minute and the daily backup.
- **Sitemap:** set `APP_URL` to the canonical production domain (e.g. `https://www.example.com`) — every sitemap and robots.txt URL is built from it, and generation refuses `localhost`/`.test`-style hosts in production. The PHP user running the scheduler and queue worker must be able to write to `public/`. Run `php artisan sitemap:generate` once after deploying.
- **New permissions:** after upgrading, run `php artisan migrate --force` and `php artisan db:seed --class=RolesAndPermissionsSeeder --force` so the admin role gets `manage tags`, `view system health` and `manage backups`.
- **Backups:** the server needs `mysqldump`/`pg_dump`/`sqlite3` for your database driver. Set `BACKUP_NOTIFICATION_EMAIL` and, for off-site disks, `BACKUP_DISKS` and `BACKUP_ARCHIVE_PASSWORD`. Archives exclude `.env` and the source code. Restore steps: [docs/spatie-integrations.md#restoring](docs/spatie-integrations.md#restoring).
- **Health monitoring:** the scheduler runs the checks every minute. Set `HEALTH_NOTIFICATION_EMAIL` for alerts and `HEALTH_SECRET_TOKEN` to enable `/health.json` for external monitors.
- **Maintenance mode** is controlled from **Admin → Settings**; it does not need `php artisan down`.
- **Health checks:** point uptime monitors at `/up`. The admin dashboard's system-health card shows the same probes in more detail.
- **Passwords:** every password rule uses `Password::defaults()`. In production it also requires mixed case, numbers and a password that is not in a known breach list. Outside production the minimum is 8 characters.
- **Rate limits:** the API's `auth/login`, `auth/register` and `auth/token` endpoints allow 6 requests per minute per email and IP.

### Environment variables

These are the keys that change the starter kit's own behaviour. Everything else is standard Laravel configuration.

| Variable | Default | Purpose |
| --- | --- | --- |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `en` | Default and fallback UI language |
| `APP_MAINTENANCE_DRIVER` | `file` | Laravel maintenance driver (`php artisan down`); the admin toggle does not use it |
| `GITHUB_URL` | the project repository | Links to GitHub in the navbar, the welcome page and the docs page |
| `QUEUE_CONNECTION` | `database` | Where queued mail and notifications run |
| `CACHE_STORE` | `database` | Cache store, used by the health probes and rate limiting |
| `SESSION_DRIVER` | `database` | Session store behind the web login |
| `SITEMAP_SCHEDULE` | `0 * * * *` | Cron expression for scheduled sitemap regeneration |
| `SITEMAP_REGENERATE_ON_CHANGE` | `true` | Queue a sitemap rebuild when a published post changes |
| `SITEMAP_MAX_URLS_PER_FILE` | `50000` | Split into a sitemap index above this many URLs |
| `FEED_ENABLED` / `FEED_LIMIT` | `true` / `20` | RSS feed at `/feed` |
| `SEO_STRUCTURED_DATA` | `true` | JSON-LD on public pages (`SEO_ORGANIZATION_LOGO`, `SEO_SAME_AS` for the organisation) |
| `HONEYPOT_ENABLED` / `HONEYPOT_SECONDS` | `true` / `1` | Spam protection on register and forgot-password |
| `BACKUP_DISKS` | `backups` | Backup destinations (comma-separated disks) |
| `BACKUP_SCHEDULE_ENABLED` / `BACKUP_RUN_AT` | `true` / `01:30` | Daily backup, then cleanup (+1 h) and monitoring (+1 h 30) |
| `BACKUP_NOTIFICATION_EMAIL` | `ADMIN_EMAIL` | Receives backup failure mails |
| `BACKUP_ARCHIVE_PASSWORD` | empty | Encrypts archives (recommended off-site) |
| `HEALTH_NOTIFICATION_EMAIL` | empty | Receives failed health-check mails (none sent when empty) |
| `HEALTH_SECRET_TOKEN` | empty | Bearer token for `/health.json` (endpoint is 404 when empty) |
| `LARAVEL_PDF_DRIVER` | `dompdf` | PDF engine; `browsershot` for Arabic/RTL documents |
| `PDF_FORMAT` / `PDF_ORIENTATION` | `a4` / `portrait` | Default PDF page setup |
| `IMPORT_MAX_KB` / `IMPORT_MAX_ROWS` | `5120` / `5000` | Upload limits for CSV/XLSX imports |
| `MAIL_MAILER` | `log` in `.env.example` | Set to a real mailer before sending verification or reset emails |
| `CRUD_NOTIFICATIONS_ENABLED` | `true` | Turns the CRUD notification observers on or off |
| `ADMIN_EMAIL` / `ADMIN_NAME` / `ADMIN_PASSWORD` | `admin@majidastarter.com` / `Admin User` / `password` | Read by `AdminUserSeeder` only |

### Admin login

Sign in at `/login` with the seeded admin account:

| Field    | Default value            |
| -------- | ------------------------ |
| Email    | `admin@majidastarter.com` |
| Password | `password`               |

The admin is created by `database/seeders/AdminUserSeeder.php` and is always seeded, including by `php artisan db:seed --class=MinimalDatabaseSeeder` and `ConfigDatabaseSeeder`. Override the defaults before seeding by setting these in `.env`:

```dotenv
ADMIN_NAME="Admin User"
ADMIN_EMAIL=admin@majidastarter.com
ADMIN_PASSWORD=change-me-now
```

> **Change the admin password before deploying.** `password` is a well-known default. Re-running the seeder updates the existing admin's password to the current `ADMIN_PASSWORD`, so you can also set a new one with `php artisan db:seed --class=AdminUserSeeder`.

### Other seeded accounts

`php artisan db:seed` also creates the following demo accounts (these are not created by the minimal seeders):

| Role | Email              | Password   | Notes                  |
| ---- | ------------------ | ---------- | ---------------------- |
| User | `test@example.com` | `password` | Standard verified user |

Ten additional random users are created with the same `password`. Their details come from `UserFactory`.

## Project Structure

```
app/
├── Console/Commands/      # starter:make-crud, translations:sync
├── Exceptions/            # DomainActionException (returns a 422 JSON response to JSON requests)
├── Http/
│   ├── Controllers/
│   │   ├── Api/           # JSON API controllers, one per service
│   │   ├── Auth/          # Logout
│   │   ├── ActivityLogExportController.php
│   │   ├── BlogController.php     # Public /blog listing and post detail
│   │   └── LocaleController.php
│   └── Middleware/
│       ├── EnforceMaintenanceMode.php  # 503 for non-admins while maintenance is on
│       ├── EnsureUserIsAdmin.php       # Guards admin routes ('admin' alias)
│       ├── SetLocale.php               # Applies the session locale on every request
│       └── TrackVisit.php              # Records page views after the response is sent
├── Models/                # User, Post, Category, Setting, Visit
├── Notifications/         # Queued verify-email and reset-password mails, CRUD notifications
├── Observers/             # CrudNotificationObserver (configured in config/crud_notifications.php)
├── Policies/              # PostPolicy, CategoryPolicy
├── Providers/             # AppServiceProvider: password rules, rate limiter, observers
├── Services/              # All domain logic (see below)
└── Support/               # Livewire toast helper, notification recipient resolution

config/
├── crud_notifications.php # Which models notify, on which events, and who receives it
├── navigation.php         # Public navbar links
└── ...                    # Standard Laravel config, plus activitylog, permission, livewire

database/
├── factories/             # User, Post, Category
├── migrations/
└── seeders/               # DatabaseSeeder, ConfigDatabaseSeeder, MinimalDatabaseSeeder, AdminUserSeeder, RolesAndPermissionsSeeder

resources/views/
├── pages/                 # Livewire single-file page components (⚡ prefixed)
│   ├── auth/              # login, register, forgot/reset password, verify email, two-factor challenge
│   ├── admin/             # dashboard, users, posts, categories, activity log, settings
│   ├── settings/          # profile, two-factor
│   └── dashboard          # authenticated user dashboard
├── components/            # Shared Blade components, including the marketing layout
├── partials/              # Theme toggle, locale switcher, favicons
├── welcome.blade.php      # marketing landing page
├── blog.blade.php         # public blog listing (search)
├── blog-post.blade.php    # public blog post
└── docs.blade.php         # public documentation page

app/Services/
├── ActivityLogService.php # Activity log queries and export rows
├── CategoryService.php    # Category CRUD, options, stats
├── PostService.php        # Post CRUD, search, public listing, stats
├── BackupService.php      # Backup destination status, queued backup runs
├── HealthCheckService.php # Latest / fresh spatie/laravel-health results
├── PdfService.php         # Shared PDF defaults and layout
├── SitemapService.php     # Builds and atomically publishes sitemap.xml
├── SpreadsheetService.php # Streamed CSV/XLSX export, validated import
├── StructuredDataService.php # schema.org JSON-LD graphs for public pages
├── TagService.php         # Tag normalisation, syncing and management
├── SettingService.php     # Reads/updates the site settings record
├── SystemHealthService.php# Probes for the admin health card
├── TwoFactorService.php   # TOTP setup, verification, recovery codes
├── UserService.php        # Registration, CRUD, role toggling, stats
└── VisitService.php       # Visit recording and dashboard statistics

routes/
├── web.php                # Public, guest, authenticated, verified, and admin route groups
├── api.php                # JSON API endpoints (see below)
└── console.php            # Scheduled tasks (activitylog:clean, sitemap:generate)

lang/
└── en/  ar/  fr/          # PHP translation files per locale

docs/
├── feature-audit-and-improvements.md  # Feature audit and improvement backlog
└── spatie-integrations.md             # Slugs, SEO, tags, RSS, honeypot, backups, health, PDF, spreadsheets
```

## Routing Overview

### Web

- **Public** — `/` (landing), `/about`, `/docs`, `/blog` (listing, `?q=` search, `?tag=` filter), `/feed` (RSS), `/health.json` (token-protected), `/blog/{slug}` (published post), `/up` (health check), `/robots.txt`, `/sitemap.xml` (static file written by `sitemap:generate`)
- **Guest routes** — `/login`, `/register`, `/forgot-password`, `/reset-password/{token}`, `/two-factor-challenge` (second step of sign-in)
- **Authenticated routes** — `/verify-email`, `/verify-email/{id}/{hash}` (signed link), `/settings/profile`, `/settings/two-factor`, `/logout`
- **Verified routes** — `/dashboard`
- **Admin routes** (`verified` + `admin` middleware) —
  `/admin/dashboard`,
  `/admin/users`, `/admin/users/create`, `/admin/users/{user}/edit`,
  `/admin/categories`, `/admin/categories/create`, `/admin/categories/{category}/edit`,
  `/admin/posts`, `/admin/posts/create`, `/admin/posts/{post}/edit`,
  `/admin/posts/{post}/pdf`, `/admin/posts/export/{csv|xlsx}`,
  `/admin/categories/import`, `/admin/categories/export/{csv|xlsx}`, `/admin/tags`,
  `/admin/health`, `/admin/backups`,
  `/admin/activity-log`, `/admin/activity-log/export/{csv|json}`, `/admin/settings`, `/admin/notifications`
- **Locale switch** — `/language/{locale}` persists the chosen locale to the session

### API

All endpoints are prefixed with `/api` and defined in `routes/api.php`. Each maps onto a service method and is protected by the same authorization gates (`can:manage users`, `can:manage posts`, etc.) as the web UI.

**Authentication.** Authenticated endpoints accept either:

- a bearer token: `Authorization: Bearer <token>`, issued by `POST /api/auth/token` with `email`, `password`, `device_name`, and `two_factor_code` when two-factor is on. Revoke it with `DELETE /api/auth/token`.
- the session cookie, established by `POST /api/auth/login` with `email`, `password`, and `two_factor_code` when two-factor is on.

Registration, login and token issuance are throttled to 6 requests per minute per email and IP.

| Method & path                                | Guard                   | Backed by                          |
| -------------------------------------------- | ----------------------- | ---------------------------------- |
| `POST   /api/auth/register`                  | throttled               | `UserService::register`            |
| `POST   /api/auth/login`                     | throttled               | `UserService::attempt`             |
| `POST   /api/auth/token`                     | throttled               | Sanctum token issuance             |
| `DELETE /api/auth/token`                     | auth                    | Revokes the token used             |
| `GET    /api/profile`                        | auth                    | current user                       |
| `PUT    /api/profile`                        | auth                    | `UserService::updateProfile`       |
| `PUT    /api/profile/password`               | auth                    | `UserService::updatePassword`      |
| `DELETE /api/profile`                        | auth                    | `UserService::deleteOwnAccount`    |
| `GET    /api/categories`                     | `can:manage categories` | `CategoryService::searchPaginated` |
| `GET    /api/categories/options`             | `can:manage categories` | `CategoryService::options`         |
| `GET    /api/categories/stats`               | `can:manage categories` | `CategoryService::stats`           |
| `POST   /api/categories`                     | `can:manage categories` | `CategoryService::create`          |
| `PUT    /api/categories/{category}`          | `can:manage categories` | `CategoryService::update`          |
| `DELETE /api/categories/{category}`          | `can:manage categories` | `CategoryService::delete`          |
| `POST   /api/categories/{category}/restore`  | `can:manage categories` | `CategoryService::restore`         |
| `DELETE /api/categories/{category}/force`    | `can:manage categories` | `CategoryService::forceDelete`     |
| `GET    /api/posts`                          | `can:manage posts`      | `PostService::searchPaginated`     |
| `GET    /api/posts/stats`                    | `can:manage posts`      | `PostService::stats`               |
| `POST   /api/posts`                          | `can:manage posts`      | `PostService::create`              |
| `PUT    /api/posts/{post}`                   | `can:manage posts`      | `PostService::update`              |
| `DELETE /api/posts/{post}`                   | `can:manage posts`      | `PostService::delete`              |
| `POST   /api/posts/{post}/restore`           | `can:manage posts`      | `PostService::restore`             |
| `DELETE /api/posts/{post}/force`             | `can:manage posts`      | `PostService::forceDelete`         |
| `GET    /api/users`                          | `can:manage users`      | `UserService::searchPaginated`     |
| `GET    /api/users/roles`                    | `can:manage users`      | `UserService::roleNames`           |
| `GET    /api/users/stats`                    | `can:manage users`      | `UserService::stats`               |
| `POST   /api/users`                          | `can:manage users`      | `UserService::create`              |
| `PUT    /api/users/{user}`                   | `can:manage users`      | `UserService::updateUser`          |
| `DELETE /api/users/{user}`                   | `can:manage users`      | `UserService::delete`              |
| `POST   /api/users/{user}/restore`           | `can:manage users`      | `UserService::restore`             |
| `DELETE /api/users/{user}/force`             | `can:manage users`      | `UserService::forceDelete`         |
| `POST   /api/users/{user}/toggle-admin`      | `can:manage users`      | `UserService::toggleAdminRole`     |
| `GET    /api/settings`                       | `can:manage settings`   | `SettingService::current`          |
| `PUT    /api/settings`                       | `can:manage settings`   | `SettingService::update`           |

List endpoints accept `trashed=only|with`, `sort` and `direction`. Posts also accept `status`, `category_id` and `tag` (tag id), and `POST`/`PUT /api/posts` accept an optional `tags` array of names; users accept `role=admin|member`. The `stats` and `options` endpoints feed select boxes and dashboard widgets.

Errors on `/api` are always JSON. A site in maintenance mode returns `503` to everyone except administrators.

## Feature notes

These features work out of the box, but are easy to miss in the code.

- **Visit statistics.** `TrackVisit` records successful `GET` page requests after the response is sent, so tracking never slows a page. It skips the admin area, `/api`, Livewire requests, language switches, logout, `/up` and common bots. Totals are shown on the admin and user dashboards.
- **CRUD notifications.** `CrudNotificationObserver` watches the models listed in `config/crud_notifications.php` (posts, categories, users and settings by default). On create, update, delete or restore it stores a `CrudActionNotification` in the `notifications` table for every user with the `admin` role, except the person who made the change. Updates that only touch `updated_at`, `updated_by`, `remember_token` or `deleted_at` are skipped, so a "remember me" login does not notify anyone. Admins see the unread count in the topbar bell and manage the list at `/admin/notifications` (filter, mark one or all as read). To watch another model, add it to that config file.
- **System health.** `SystemHealthService` runs independent probes for the database, cache and queue and shows them in the admin dashboard's system-health card. A failing probe does not break the page.
- **Maintenance mode.** Toggled from **Admin → Settings**. `EnforceMaintenanceMode` serves a 503 page to visitors and JSON on `/api`. Administrators, the login and two-factor pages, language switching and `/up` stay reachable.
- **Date format.** The admin settings choose one format for dates shown in the activity log and the users list. The choices are defined in `Setting::DATE_FORMATS`.
- **Cover images.** Posts can have a cover image, uploaded in the post form and stored on the `public` disk. It is shown on `/blog` and on the post page. Run `php artisan storage:link` first.
- **XML sitemap.** `php artisan sitemap:generate` (`SitemapService`) lists the named routes in `config/sitemap.php` (`home`, `about`, `docs`, `blog`) and every post that `/blog/{slug}` actually serves (published, not trashed). URLs are built from `APP_URL`, never contain query strings and are de-duplicated. `<lastmod>` is the post's `updated_at` (and the newest one for `/blog`); static pages get none, and `changefreq`/`priority` are omitted. Files are rendered, validated and written to a temp file, then renamed into place, so a failed run leaves the previous sitemap intact and the command exits non-zero. Above 50,000 URLs it writes `sitemap-1.xml`, `sitemap-2.xml`, … behind a sitemap index. It is regenerated hourly (`SITEMAP_SCHEDULE`, `withoutOverlapping`) and `PostSitemapObserver` queues a de-duplicated `GenerateSitemapJob` whenever a published post changes. Auth, account, admin, API and language routes are never listed; categories have no public page, so they are not listed either. `/robots.txt` is served by `RobotsController` and adds the `Sitemap:` line once the file exists.
- **Support email.** Set in admin settings and stored with the site settings.

## Localization

Available locales are configured in `config/app.php` under `available_locales`:

```php
'available_locales' => [
    'en' => ['label' => 'English', 'dir' => 'ltr'],
    'ar' => ['label' => 'العربية', 'dir' => 'rtl'],
    'fr' => ['label' => 'Français', 'dir' => 'ltr'],
],
```

The `SetLocale` middleware applies the session's chosen locale on every request, the `LocaleController` handles switching, and text direction (`ltr`/`rtl`) is derived automatically for correct RTL rendering. To add a language, add an entry here and a matching `lang/{locale}` directory with the same files as `lang/en`.

`php artisan translations:sync` (`app/Console/Commands/SyncTranslationKeys.php`) scans `resources/` for translation keys, creates missing lang files, and fills in keys missing from other locales. Options:

- `--locales=en,fr,ar` — which locales to sync (default: all)
- `--base=en` — the reference locale

Untranslated values are prefixed with `[TODO]` so they are easy to find and complete.

## Roles & Permissions

Roles and permissions are seeded by `RolesAndPermissionsSeeder`. The `admin` role is granted the following permissions:

- `view admin dashboard`
- `manage users`
- `manage posts`
- `manage categories`
- `view activity log`
- `manage settings`
- `manage tags`
- `view system health`
- `manage backups`

Check for admin access in code via `$user->isAdmin()` or the `admin` route middleware alias.

Post and category actions additionally go through `App\Policies\PostPolicy` and `App\Policies\CategoryPolicy`. Viewing and creating posts requires `manage posts`. Editing, trashing, restoring or permanently deleting a post also requires that the user is the post's author, unless they are an admin. Categories are shared, so every category action requires only `manage categories`.

## Scaffolding a New Resource

```bash
php artisan starter:make-crud Article
```

This creates an `Article` model, migration, factory, `ArticleService`, `ArticlePolicy` and `ArticleModuleTest`, using soft deletes and activity logging. It never overwrites existing files. Afterwards add the `manage articles` permission to `RolesAndPermissionsSeeder`, then add the routes, API controller and screens you need.

## Testing & Code Quality

```bash
# Run the test suite
composer test          # or: php artisan test

# Check code style (Laravel Pint)
vendor/bin/pint --test # add --dirty to only check changed files, drop --test to fix

# Static analysis (Larastan / PHPStan)
composer analyse
```

The suite lives in `tests/Feature` and covers authentication and two-factor, email verification, profile management, user/post/category management, admin pages, the REST API, CRUD notifications, maintenance mode, search, soft deletes and localization. Tests run against an in-memory SQLite database with the array cache, array mail and synchronous queue (see `phpunit.xml`).

## Continuous Integration

A GitHub Actions workflow (`.github/workflows/ci.yml`) runs on every push to `develop`/`main` and on every pull request. It:

- runs the test suite on PHP 8.4 (building front-end assets first with Node 20), and
- checks code style with Pint and runs Larastan static analysis on PHP 8.4.

## License

MajidaStarter is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT). It is built on the [Laravel framework](https://laravel.com), which is also MIT licensed.
