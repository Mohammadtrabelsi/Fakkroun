# Next step 10: Analytics, performance, security, deployment (Phase 6)

> **Priority:** before public launch · **Effort:** large · **Spec:** §24, §27, §28, §31
> **Depends on:** 03–07 (09 for advertising analytics)

## Analytics (spec §24)

- Daily rollup jobs into compact tables (no heavy queries on dashboards):
  - platform: registrations, active users, games, rooms, players per room, durations, retention cohorts;
  - questions: usage, correct rate, average response time, difficulty calibration (flag "easy" questions answered correctly < 30% of the time), reports, translation usage.
- The existing admin dashboard gains Fakkroun cards. The moderator dashboard shows queue age and decisions per week.
- Advertising analytics arrive with [`09`](09-next-step-advertising-and-billing.md), keeping attempted deliveries and confirmed impressions apart.

## Performance (spec §27)

- **Load tests** (k6 or Artillery): 100 concurrent rooms × 10 players answering within the same second; question selection on a 500k-row pool; a 20k-row import running concurrently.
- Redis for cache, queues and Reverb scaling, with Horizon if Redis queues are adopted.
- `EXPLAIN` review of the selection, moderation-queue and leaderboard queries on MySQL 8. Add composite indexes where needed.
- Consider MySQL `FULLTEXT` (or a search engine) for admin question search and near-duplicate candidates, which use `LIKE '%…%'` today.

## Security review (spec §28)

- A pass over every endpoint: authorisation, validation, rate limits, CSRF, file uploads (import, avatar), XSS in user-generated questions (Blade escapes, but check every `{!! !!}`).
- Anti-cheating: per-player answer rate, impossible response times, multi-account detection in the same room.
- Re-encode uploaded avatars (strip metadata) once an image library is added.
- Run `/security-review` and a dependency audit (`composer audit`, `npm audit`) in CI.

## Deployment (spec §31)

- Document processes: php-fpm, nginx, Reverb, queue workers (`default`, `games`, `imports`), scheduler; supervisor or systemd units; health checks (`/up`, `/health.json` + Reverb and Redis checks).
- Zero-downtime migrations and backups (spatie/laravel-backup is already scheduled), with a tested restore procedure.
- Secrets in the host's secret store. `APP_DEBUG=false` and production password rules (already in `AppServiceProvider`).
- Error monitoring (e.g. Sentry) and queue failure alerts.
- Choose the hosting provider (assessment D7, still open).
