# Next step 10: Performance, security and launch readiness

> **Priority:** before public launch · **Effort:** large · **Spec:** §24, §27, §28, §31
> **Depends on:** 03

Already done and removed from this list: the admin analytics screen (live queries) and the deployment guide [`13`](13-deployment.md).

## Analytics at scale (spec §24)

- Daily rollup jobs into compact tables, so dashboards stop querying raw tables:
  - platform: registrations, active users, games, rooms, players per room, durations, retention cohorts;
  - questions: usage, correct rate, average response time, reports, translation usage, and difficulty calibration (for example, flag "easy" questions that few players answer correctly; the threshold is an admin setting).
- Moderator dashboard: queue age and decisions per week.

## Performance (spec §27)

- **Load tests** (k6 or Artillery): 100 concurrent rooms × 10 players answering in the same second; question selection on a 500k-row pool; a 20k-row import running at the same time.
- Redis for cache, queues and Reverb scaling (Horizon if Redis queues are adopted).
- `EXPLAIN` review of the selection, moderation-queue, leaderboard and ad-offer queries on MySQL 8. Add composite indexes where needed.
- MySQL `FULLTEXT` (or a search engine) for admin question search and near-duplicate detection, which use `LIKE '%…%'` today.

## Security (spec §28)

- A pass over every endpoint: authorisation, validation, rate limits, CSRF, file uploads (import, avatar, partner logos, ad creatives), and XSS in user-generated questions (check every `{!! !!}`).
- Anti-cheating: per-player answer rate, impossible response times, several accounts from one device in the same room.
- Re-encode uploaded images (strip metadata) once an image library is approved.
- Run `/security-review`, `composer audit` and `npm audit` in CI.

## Launch operations (spec §31)

- Choose the hosting provider (assessment D7, still open).
- Error monitoring (for example, Sentry) and queue-failure alerts.
- A tested backup restore, following [`13`](13-deployment.md) §6.

## Done when

- Load tests meet the targets above with no errors.
- The security pass has no open high-severity findings.
- Monitoring alerts reach a person.
