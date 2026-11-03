# Spatie integrations: SEO, tags, feeds, spam protection, backups, health, PDF and spreadsheets

This guide covers the packages added on top of the existing stack (permission,
activitylog, translatable, sitemap). Each section says what the feature does,
where the code lives, how to configure it and how to operate it.

| Package | Version | Used for |
| --- | --- | --- |
| `spatie/laravel-sluggable` | ^4.0 | Unique, stable URL slugs for posts and categories |
| `spatie/schema-org` | ^4.0 | JSON-LD structured data (5.x needs PHP 8.4, 4.x works on 8.3 and 8.4) |
| `spatie/laravel-tags` | ^4.12 | Translatable tags on posts |
| `spatie/laravel-feed` | ^4.5 | RSS feed of published posts |
| `spatie/laravel-honeypot` | ^4.7 | Spam protection on public forms |
| `spatie/laravel-backup` | ^10.3 | Database and upload backups |
| `spatie/laravel-health` | ^1.40 | Health checks, admin page, monitoring endpoint |
| `spatie/laravel-pdf` + `dompdf/dompdf` | ^2.14 / ^3.1 | PDF documents (DomPDF driver: pure PHP, no browser) |
| `spatie/simple-excel` | ^3.11 | Streaming CSV/XLSX export and validated import |

Composer also pulled their own dependencies (openspout, db-dumper,
eloquent-sortable, temporary-directory, …). No other package was added.

After deploying, run:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force                                  # creates tags + taggables
php artisan db:seed --class=RolesAndPermissionsSeeder --force   # adds the new permissions to the admin role
```

New permissions (granted to `admin` by the seeder): `manage tags`,
`view system health`, `manage backups`. Imports and exports reuse
`manage posts` / `manage categories` and the existing policies.

---

## Slugs (laravel-sluggable)

`App\Models\Concerns\HasUniqueSlug` is used by `Post` and `Category`. It
replaced the two hand-written `uniqueSlug()` methods the services had, and keeps
their rules:

- a new record gets a slug from its English title/name (or the first non-empty
  translation, or `post`/`category` if nothing is sluggable);
- a supplied slug is normalised (`My Post!` → `my-post`);
- slugs are unique across the table **including trashed rows**, so restoring
  never collides; duplicates become `-2`, `-3`, …;
- **an existing slug never changes when only the title changes**, so published
  URLs stay stable. To change a URL, edit the slug; to regenerate it from the
  title, clear the slug field (or send `"slug": null` to the API).

## Structured data and SEO metadata (schema-org)

`App\Services\StructuredDataService` builds one `@graph` per page, so every
entity appears once and the others reference it by `@id`:

| Page | Types |
| --- | --- |
| `/` | Organization, WebSite (with a `SearchAction` to `/blog?q=`) |
| `/about`, `/docs`, `/blog` | Organization, WebSite, BreadcrumbList |
| `/blog/{slug}` | Organization, WebSite, BreadcrumbList, BlogPosting |

Only types the application has content for are produced (no Product, no
Review…). The organisation name and email come from **Admin → Settings**; the
logo and social profiles from `config/seo.php` (`SEO_ORGANIZATION_LOGO`,
`SEO_SAME_AS`). `SEO_STRUCTURED_DATA=false` turns it off.

Rendering: `<x-seo.structured-data :graph="$graph" />` (JSON is encoded with
`JSON_HEX_TAG`, so a title containing `</script>` cannot break out) and
`<x-seo.meta>` (description, canonical, Open Graph, Twitter card). Both are
wired into the marketing layout; pages pass `description`, `canonical`,
`image`, `og-type` and `structured-data` to `<x-layouts.marketing>`. Static
pages get their graph from a view composer in `AppServiceProvider`, blog pages
from `BlogController`.

Validate with Google's Rich Results Test or https://validator.schema.org.

## Tags (laravel-tags)

- **Model:** `App\Models\Tag` (configured in `config/tags.php`), activity-logged.
  Names and slugs are translatable. Tags are always looked up and created in
  the **fallback locale** (`APP_FALLBACK_LOCALE`), whatever language the editor
  uses, so one tag never splits into several.
- **Posts** use `HasTags`. Unlike the package default, a post keeps its tags when
  it is moved to the trash (so restoring brings them back); they are detached
  only on permanent deletion.
- **Editing:** the post create/edit forms embed the reusable
  `<livewire:admin.tag-selector wire:model="tags" />` (type a name and press
  Enter, or paste a comma-separated list; existing tags are suggested). Limits:
  20 tags per post, 50 characters per tag (`TagService::MAX_*`).
- **Managing:** **Admin → Tags** (`/admin/tags`, `manage tags`): search, create,
  rename, translate per locale, delete (removed from all posts).
- **Filtering:** admin post list (tag select), public `/blog?tag={slug}`
  (unknown tags return 404), API `GET /api/posts?tag={id}`.
- **API:** `POST/PUT /api/posts` accept `"tags": ["Laravel", "PHP"]`; omit the key
  to leave tags unchanged, send `[]` to remove them all. Post responses include
  `tags`.

## RSS feed (laravel-feed)

`GET /feed` (route `feeds.blog`) returns RSS 2.0 with the newest published,
non-trashed posts — exactly what `/blog/{slug}` serves — via
`PostService::feedItems()`. Every public page advertises it with
`<link rel="alternate">`, and `/blog` links to it.

Settings (`config/feed.php` / `.env`): `FEED_ENABLED`, `FEED_LIMIT` (20),
`FEED_TITLE`, `FEED_DESCRIPTION`, `FEED_LANGUAGE`, `FEED_IMAGE`.

## Spam protection (laravel-honeypot)

The public **register** and **forgot-password** forms (Livewire) include a
hidden honeypot field and an encrypted timestamp. A filled honeypot, a missing
or tampered timestamp, or a submission faster than `HONEYPOT_SECONDS` (1 s)
returns 403 and fires `Spatie\Honeypot\Events\SpamDetectedEvent`.

The **login** form is deliberately not protected: password managers fill and
submit it instantly, which the timing check would block; it is already
rate-limited per email and IP. Authenticated forms are not protected either.
`HONEYPOT_ENABLED=false` disables it. The test suite sets `HONEYPOT_SECONDS=0`
so Livewire tests can submit instantly; `HoneypotTest` re-enables it.

To protect another Livewire form: `use UsesSpamProtection;`, add
`public HoneypotData $extraFields;` initialised in `mount()`, call
`$this->protectAgainstSpam();` first in the submit action, and put
`<x-honeypot livewire-model="extraFields" />` inside the form.

## Backups (laravel-backup)

**What is backed up:** every database connection in
`backup.backup.source.databases` (gzipped dump) plus `storage/app/public`
(uploaded cover images). The application source and **`.env` are deliberately
not included** — code comes from git, and credentials must not sit in archives.

**Where:** the `backups` disk (`storage/app/backups`, private, no URL, not under
`public/`). Add off-site destinations with `BACKUP_DISKS=backups,s3` (configure
the `s3` disk in `.env`). Set `BACKUP_ARCHIVE_PASSWORD` to AES-256 encrypt
archives — keep that password somewhere else, or the backups are unusable.

**Server requirements:** `mysqldump` (MySQL/MariaDB), `pg_dump` (PostgreSQL) or
`sqlite3` (SQLite) on the PATH of the scheduler/queue user, and the `zip` PHP
extension.

**Schedule** (`routes/console.php`, needs the scheduler):

| When (`BACKUP_RUN_AT`, default 01:30) | Command |
| --- | --- |
| run_at | `backup:run` |
| run_at + 1 h | `backup:clean` (retention below) |
| run_at + 1 h 30 | `backup:monitor` (mails if the newest backup is too old or too big) |

Disable with `BACKUP_SCHEDULE_ENABLED=false`. Retention: keep all for 7 days,
dailies for 16 days, weeklies 8 weeks, monthlies 4 months, yearlies 2 years,
and never more than 5000 MB (`BACKUP_KEEP_*`, `BACKUP_MAX_STORAGE_MB`).
Failures (backup, cleanup, unhealthy destination) are mailed to
`BACKUP_NOTIFICATION_EMAIL` (falls back to `ADMIN_EMAIL`); successes are not.

**Admin → Backups** (`/admin/backups`, `manage backups`) shows each
destination's health, the newest backups and storage used, and can queue a
database-only or full backup (`RunBackupJob`, runs on the queue worker, one
pending run per mode). Archives are never downloadable from the browser.

```bash
php artisan backup:run            # database + files
php artisan backup:run --only-db  # database only
php artisan backup:list           # status of every destination
php artisan backup:monitor        # check health now
php artisan backup:clean          # apply retention now (deletes old archives!)
```

### Restoring

1. Put the site in maintenance mode (Admin → Settings, or `php artisan down`).
2. Copy the archive locally, e.g. `storage/app/backups/<APP_NAME>/2026-11-03-01-30-00.zip`
   (or download it from S3).
3. Unzip it (`unzip backup.zip`, plus the password if encrypted). It contains
   `db-dumps/<connection>-<database>.sql.gz` and `public/…`.
4. Database — **this overwrites current data**:
   - MySQL: `gunzip < db-dumps/mysql-<db>.sql.gz | mysql -u USER -p DBNAME`
   - PostgreSQL: `gunzip < db-dumps/pgsql-<db>.sql.gz | psql -U USER DBNAME`
   - SQLite: `gunzip < db-dumps/sqlite-<file>.sql.gz | sqlite3 database/database.sqlite`
     (into an empty file)
5. Uploads: `rsync -a public/ /path/to/app/storage/app/public/`, then
   `php artisan storage:link` if needed.
6. `php artisan migrate --force` (if the backup predates newer migrations),
   `php artisan optimize:clear`, and bring the site back up.

Test a restore on a staging copy periodically; a backup is only as good as its
last successful restore.

## Health monitoring (laravel-health)

Checks are registered in `App\Providers\HealthServiceProvider` from the
`checks` section of `config/health.php`:

| Check | Notes |
| --- | --- |
| Database, Cache | always |
| Used disk space | warn > 70 %, fail > 90 % (`HEALTH_DISK_*`) |
| Queue | only when `QUEUE_CONNECTION` is not `sync`; a heartbeat job must be processed within 5 min |
| Schedule | the scheduler heartbeat must be under 2 min old |
| Backups (per backup disk) | newest archive younger than `BACKUP_MAX_AGE_DAYS` |
| Debug mode, environment, optimized app | production only |

Each can be turned off (`HEALTH_CHECK_DISK`, `HEALTH_CHECK_QUEUE`,
`HEALTH_CHECK_SCHEDULE`, `HEALTH_CHECK_BACKUPS`). The scheduler runs
`health:check`, `health:queue-check-heartbeat` and
`health:schedule-check-heartbeat` every minute; results are kept in the cache
store. Failures are mailed (at most hourly) to `HEALTH_NOTIFICATION_EMAIL` when
it is set.

Access:

- **Admin → Health** (`/admin/health`, `view system health`): latest results
  and a "Run checks now" button (never sends notifications).
- **`GET /health.json`** for uptime monitors: returns 404 until
  `HEALTH_SECRET_TOKEN` is set, then requires
  `Authorization: Bearer <token>` (or `X-Health-Token`), compared in constant
  time; 30 requests/min. It answers 200 when all checks pass and 503 otherwise;
  add `?fresh=1` to re-run the checks first.
- `/up` is unchanged (unauthenticated liveness, no details), and the admin
  dashboard's lightweight "System health" card (`SystemHealthService`) still
  probes live.

```bash
php artisan health:check   # run now (add --no-notification to stay quiet)
php artisan health:list    # latest results
curl -H "Authorization: Bearer $HEALTH_SECRET_TOKEN" https://example.com/health.json
```

## PDF documents (laravel-pdf)

`App\Services\PdfService::document($view, $data, $filename)` returns a
`PdfBuilder` (Responsable) with the defaults from `config/pdf.php` (A4,
portrait, margins, page numbers). Every document view extends
`resources/views/pdf/layout.blade.php`, which draws the header (site name and
document type) and footer (generation date, support email, "Page X / Y").

Current use: **post PDF** — the "PDF" button on each row of Admin → Posts
(`GET /admin/posts/{post}/pdf`, `?preview=1` to open inline), authorized by
`PostPolicy::view`. Files are generated on the fly with
`Cache-Control: private, no-store` and never stored.

Adding a document type:

```php
// resources/views/pdf/report.blade.php
@extends('pdf.layout')
@section('header', 'Monthly report')
@section('content') ... @endsection

// controller, after authorizing
return $pdf->document('pdf.report', ['rows' => $rows], 'report-2026-11')->download();
```

Engine: `LARAVEL_PDF_DRIVER=dompdf` (default) needs no binaries but supports
CSS 2.1 and does **not shape Arabic/RTL text correctly**. For Arabic documents
or modern CSS, install Chrome/Puppeteer and `spatie/browsershot`, then set
`LARAVEL_PDF_DRIVER=browsershot` — no code changes. For large batches, use
`saveQueued()` so generation runs on the queue.

## CSV / Excel import and export (simple-excel)

`App\Services\SpreadsheetService` provides:

- `download($rows, $basename, $format)` — streams any iterable of associative
  rows as CSV or XLSX straight to the response (constant memory). Cells
  starting with `= + - @` are prefixed with `'` to prevent formula injection.
- `import($path, $format, $rules, $persist, $requiredHeaders)` — reads lazily,
  normalises headers to snake_case, validates each row with Laravel rules,
  writes valid rows in chunked transactions (one savepoint per row), stops at
  `IMPORT_MAX_ROWS`, and returns an `ImportResult` (created, updated, rejected
  rows with their line numbers and messages).

Available now:

| Where | What | Permission |
| --- | --- | --- |
| Admin → Posts → "Export CSV/XLSX" | posts matching the current filters (search, status, category, tag) | `manage posts` |
| Admin → Categories → "Export CSV/XLSX" | all categories, in the import layout | `manage categories` |
| Admin → Categories → "Import" | create/update categories (matched by slug) | `manage categories` |

Category import columns: `slug`, `name_en` (required), `name_ar`, `name_fr`,
`description_*`, `is_active` (1/0/yes/no). An exported file can be edited and
imported back. A slug that belongs to a trashed category is rejected rather
than silently restoring it. Uploads must be `.csv` or `.xlsx` (extension and
detected MIME type), at most `IMPORT_MAX_KB` (5 MB); the temporary upload is
deleted after processing.

For very large files, move the `import()` call into a queued job and store the
`ImportResult` for the user to view later; the service needs no changes.
