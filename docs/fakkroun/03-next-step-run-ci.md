# Next step 03: Run CI and verify Phases 1 and MVP

> **Priority:** blocking, do this first · **Effort:** small (unblocking) + unknown (fixes)
> **Owner:** repository owner (Actions settings), then development

## Why

Nothing written so far (Phase 1 and the MVP, 132 new tests plus ~368 inherited ones) has been executed. GitHub Actions on `Mohammadtrabelsi/Fakkroun` fails every job about 3 seconds after start, with no runner assigned and no logs. That includes `develop`, which holds the unmodified MajidaStarter code that is green in its own repository. So the cause is a repository or account setting, not code.

## Tasks

1. **Owner:** open a failed run (e.g. [run on `develop`](https://github.com/Mohammadtrabelsi/Fakkroun/actions/runs/41805478287)) and read the annotation. Usual causes:
   - spending limit reached or billing lock (private repositories consume Actions minutes; MajidaStarter is public);
   - Actions disabled under **Settings → Actions → General**.
2. Re-run the jobs on PR #1.
3. Fix every real failure. Expect some: the code was written without ever being executed. Likely areas, from most to least probable:
   - Livewire 4 single-file component details (`#[Computed]` access, route-model binding for `?Question $question`, `wire:poll` inside a Blade `@unless`);
   - Carbon 3 `diffInMilliseconds` signs in `GameEngine`;
   - SQLite specifics in migrations (`ALTER TABLE` foreign keys on `users`/`categories`, `timestamp(3)`);
   - factory state (`QuestionFactory::withText`) and seeder runs with model events muted;
   - Pint style and PHPStan level-5 findings in the new `app/Domain` code.
4. Keep the rule from the PR process: **no skipped or disabled tests to get green.**

## Also add

- A **MySQL 8 CI job** that runs a `@group concurrency` subset with real parallel requests (double start, double answer, concurrent joins at capacity), because SQLite ignores `lockForUpdate()` (assessment risk R2).
- A **local runner** option: the Docker image is now PHP 8.4 (`docker compose run app php artisan test`).

## Done when

- CI is green on PR #1 (tests, Pint, PHPStan).
- The MVP walkthrough in [`02-mvp.md`](02-mvp.md) §2 works by hand in a browser, in all three languages, including Arabic RTL on a phone-sized screen.
- PR #1 is reviewed and merged into `develop`.
