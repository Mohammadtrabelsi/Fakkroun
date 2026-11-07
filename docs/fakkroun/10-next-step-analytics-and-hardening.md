# Next step 10: Performance, security and launch readiness

> **Priority:** before public launch · **Effort:** large · **Spec:** §24, §27, §28, §31
> **Depends on:** 03

Already done and removed from this list: the admin analytics screen (live queries) and the deployment guide [`13`](13-deployment.md).

**Done in this step (PR #8):**
- **Daily roll-ups:** `platform_daily_stats`, written by `analytics:rollup` (scheduled daily at 00:20, re-checking the last 3 days, idempotent, never today). The games-per-day chart reads it for past days.
- **Difficulty calibration:** "easy" questions answered correctly less than `quality.easy_min_correct_rate` % of the time, and "hard" ones more than `quality.hard_max_correct_rate` %, after `quality.calibration_min_answers` plays. Listed on Admin → Analytics.
- **Moderator health:** questions waiting, oldest waiting (hours), decisions per week. Shown on Admin → Analytics.
- **Anti-cheating (first part):** answers faster than `quality.fast_answer_ms` (default 400 ms) are **flagged, never re-scored**. Players with at least `quality.fast_answer_review_threshold` flags in the period are listed for review.
- **Failed background jobs** notify administrators, at most once per job type every 10 minutes.
- **CI:** `composer audit` and `npm audit --omit=dev --audit-level=high`.
- **Load-test scripts** (k6): `tests/load/public-pages.js` and `tests/load/room-polling.js` (see the header of each file). They are written but not run yet: they need a staging server.
- **Security pass** (code reading, see below).

## Analytics at scale (spec §24)

- ~~Daily rollup jobs~~ (done, see above). Still to do: retention cohorts and per-question rollups once volumes need them:
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
- Anti-cheating: impossible response times are flagged (done). Still to do: several accounts from one device in the same room (needs a privacy decision: device fingerprints are personal data).
- Re-encode uploaded images (strip metadata) once an image library is approved.
- `composer audit` and `npm audit` run in CI (done).

### Security pass findings (code reading, 2026-11-07)

| Area | Finding |
| --- | --- |
| Raw Blade output (`{!! !!}`) | Only two outside PDFs: `nl2br(e(...))` in blog posts (escaped first) and schema.org JSON-LD (`json_encode` escapes `/`, so `</script>` can't break out). PDF views escape user text with `e()`. OK. |
| Ads | Links must be `https://`, reviewed by an admin, and opened through `/ads/click/{token}` (`rel="noopener noreferrer sponsored"`). Billing only on server-issued, owner-bound, expiring tokens. OK. |
| Invoices / statements | PDFs only for the owning advertiser or `manage advertising`; generated on the fly, `Cache-Control: private, no-store`. OK. |
| Answers | One answer per player per question (unique index), timing taken on the server; a client can't change scores. OK. |
| Rate limits | Joins by link 30/min, ad clicks 60/min, sign-in throttled. **To add:** an explicit limit on Livewire actions for answers and AI drafts per IP (drafts already have a per-user limit). |
| Uploads | Images validated by type and size, random names; **not re-encoded** (needs an image library). |
| AI prompts | Player and advertiser text is wrapped in tags and treated as content, never as instructions; the AI never decides (drafts stay drafts, ad notes are advisory). |

## Launch operations (spec §31)

- Choose the hosting provider (assessment D7, still open).
- Error monitoring (for example, Sentry) and queue-failure alerts.
- A tested backup restore, following [`13`](13-deployment.md) §6.

## Done when

- Load tests meet the targets above with no errors.
- The security pass has no open high-severity findings.
- Monitoring alerts reach a person.
