# Next step 03: Run CI and verify everything on `develop`

> **Priority:** blocking, do this first · **Effort:** small (unblocking) + unknown (fixes)
> **Owner:** repository owner (Actions settings), then development

## Why

PRs #1–#3 are merged into `develop`, but **none of their code has been executed**: not the tests, the migrations or the pages. GitHub Actions on `Mohammadtrabelsi/Fakkroun` fails every job about 3 seconds after it starts, with no runner assigned (`runner_id` 0) and no logs. That includes the untouched starter code, so the cause is a repository or account setting, not the code.

## Tasks

1. **Owner:** open a failed run and read the annotation. Usual causes:
   - spending limit reached or billing lock (private repositories use Actions minutes);
   - Actions disabled under **Settings → Actions → General**.
2. Re-run CI on `develop`.
3. Fix every real failure. Expect some: around 9,000 lines were written without being run. Likely areas, from most to least probable:
   - Livewire 4 single-file component details (`#[Computed]` access, route-model binding, `wire:poll` placement);
   - Carbon 3 `diffInMilliseconds` signs in `GameEngine`;
   - SQLite specifics in migrations (foreign keys added to existing tables, `timestamp(3)`);
   - factory state and seeders running with model events muted;
   - Pint style and PHPStan level-5 findings in `app/Domain`.
4. **No skipped or disabled tests to get green.**

## Also add

- A **MySQL 8 CI job** running a `@group concurrency` subset with real parallel requests (double start, double answer, concurrent joins at capacity, concurrent ad billing), because SQLite ignores `lockForUpdate()`.
- A **local runner** option: the Docker image is PHP 8.4 (`docker compose run app php artisan test`).

## Done when

- CI is green on `develop` (tests, Pint, PHPStan).
- The walkthrough in [`02-mvp.md`](02-mvp.md) §2 works by hand in a browser in all three languages, including Arabic RTL on a phone-sized screen.
