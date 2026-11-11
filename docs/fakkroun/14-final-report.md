# Final report: Fakkroun phases 1–6

> **Spec reference:** §30 (phases), §33 (report format)
> **Branches:** `ccr-fc788502-hcpb6b` (Phase 1 + MVP, PR #1 → `develop`) and `claude/fakkroun-next-steps` (design brief, phases 4–6, draft PR #2 → `develop`)
> **Date:** 2026-11-05 · open work: [`15-roadmap.md`](15-roadmap.md)

## ⚠️ Nothing in this report had been executed when it was written

> **Update 2026-11-11:** the suite has since been run and passes, and the game has been played in a browser on phone screens. See [`03`](03-next-step-run-ci.md) for what ran, what it found and what is still unverified (CI runners, PHP 8.4). The text below is kept as written.

Not one test, migration or page in either branch has run. GitHub Actions assigns no runner to any job in this repository, including on the untouched `develop` branch (see [`03`](03-next-step-run-ci.md)). Locally there is only PHP 8.3, while the lockfile needs 8.4, and installing Composer dependencies wasn't permitted in this environment.

What was checked: `php -l` on every PHP file; the PHP part of every Livewire single-file component extracted and linted; Blade directive balance checked with a script; translation-key parity across en/fr/ar; and every `__('advertising.*')` key referenced in code verified to exist.

**Treat every feature below as "written", not "complete", until `php artisan test`, Pint and PHPStan pass on PHP 8.4.** Expect a first run to surface bugs: around 9,000 lines have never been executed.

## 1. What exists, by phase

| Phase | Scope | Where it is documented |
| --- | --- | --- |
| 1 Foundation | Roles and permissions, back-office access, category moderators, user language preferences, suspension, categories with subcategories and policies, 13 seeded categories, platform settings, branding | [`01`](01-phase-1-report.md) |
| 2–3 MVP | Question bank (statuses, translations, revisions, moderation, reports, import/export, language pool), rooms, server-authoritative game engine, scoring with the duplicate-answer rule, results | [`02`](02-mvp.md) |
| Design brief | Tokens, mascot, public layout, home; lobby, live room, results; community screen; partners | [`12`](12-design-implementation.md) |
| Player experience | Game history (`/play/history`), achievements (awarded when a game ends, shown on the profile and home), player home with stats, keyboard answering (1–4, A–D, أ–د). Scheduled game driver (`games:advance` every 5 s), lobby expiry, leaderboard (this week / all time), public player profiles, player inbox and bell, 404 page with the mascot | [`11`](11-next-step-player-experience.md) |
| 4 AI | `QuestionDrafter` (one draft from a fact, rate-limited) and batch generation (queued, chunked, cost ceiling, duplicate check). Output always lands as **draft** with `source = ai`. Anthropic Messages API over Laravel's HTTP client with structured JSON output and retries. Hidden without `ANTHROPIC_API_KEY`. | §1 (step 08 removed: done) |
| 5 Advertising | See §2 | §2 (step 09 removed: done); future work in [`16`](16-user-story-smart-ads.md) |
| 6 Analytics and operations | Admin analytics (games, players, answers, correct rate, response time, per category and language, hardest/easiest/most played/most reported questions, question bank health, AI usage and cost, advertising). Deployment guide. | [`13`](13-deployment.md), [`10`](10-next-step-analytics-and-hardening.md) |

## 2. Advertising and billing (Phase 5)

**Data:**
- `advertisers` (linked one-to-one to a user with the `advertiser` role).
- `advertising_packages`.
- `advertiser_wallets`: one row per advertiser **per credit type**. The balance is unsigned.
- `advertising_campaigns` and `advertising_creatives`.
- `advertising_ledger_entries`: append-only; the model refuses updates and deletes. Every row has a unique `billing_reference`.
- `ad_deliveries`, `advertising_impressions` and `sponsored_question_deliveries`.

**How the money rules from spec §18 are enforced:**

| Rule | Mechanism |
| --- | --- |
| Two separate units | Impression credits and sponsored-question credits sit in separate wallets. A package purchase posts one money entry and one credit entry per type. Nothing converts one into the other. |
| Never trust the client | The server picks the ad (`AdService::offer`) and issues a single-use 48-character token. The browser only reports "it was visible for ≥ 1 s" with that token. The server checks owner and expiry, then bills. |
| No double billing | Billing references are `impression:<delivery id>` and `sponsored:<game question id>`, unique in the ledger. Refreshing, reconnecting, retrying or running two workers returns the existing entry. |
| No negative balance | The balance moves with `UPDATE … WHERE balance >= n` inside the same transaction as the ledger insert. If the update fails, nothing is written. |
| Exhaustion | A failed charge marks the campaign **exhausted** and notifies the advertiser. Offers already filter out campaigns with no balance. |
| Sponsored questions | Excluded from normal selection. Up to `advertising.max_sponsored_per_game` are mixed into a new game (never all of it). Each is billed **once per room** when it opens. |
| Corrections | Admin adjustments (non-zero quantity plus a mandatory note) and reversals of a consumption are new ledger rows. |
| Reconciliation | `advertising:reconcile` runs hourly. It checks every impression and delivery against its ledger entry, every consumption entry against an event, and every wallet against the ledger sum. The report appears in **Admin → Advertising**. |

**Screens:**
- **Advertiser dashboard** (`/advertiser`): balances; confirmed impressions; sponsored reach; spend; filters by date, campaign, category and language; per-day, per-category and per-language figures; campaigns with creatives, submit and pause; billing history.
- **Admin → Advertising**: campaign review (approve, reject with a reason, pause); advertisers (link an account, which assigns the role); packages; record a purchase (unique payment reference); adjust credits; reconciliation.
- **Question editor**: sponsor assignment.
- **Game settings**: frequency caps (every N questions, per player per game, minimum seconds between ads), prices per credit, currency and low-balance threshold.

**Payments** are recorded by an administrator after they arrive outside the platform. No payment gateway is integrated (none was specified or chosen).

## 2b. Later additions (PRs #3–#7)

- **Player progress:** game history, achievements, player home, keyboard play.
- **Terms of use** with the advertiser policy chart; **paid ad-free accounts** (cash payments, price in settings); **three-strike** advertiser policy.
- **Smart ads (story 16)**, all seven slices: relevance and fatigue, results-page and waiting-room placements, every ad rule configurable, invoices and statements, "Learn more" clicks and reporting, sponsored rounds, rotation by performance, and AI review notes. Details in [`16`](16-user-story-smart-ads.md) §9.

## 3. Tests written (not executed)

Phase 1 and the MVP: 132 new tests (reports [`01`](01-phase-1-report.md), [`02`](02-mvp.md)). This branch adds 53 more:

| File | Tests | Covers |
| --- | --- | --- |
| `Fakkroun/HomePageTest`, `Gameplay/AnswerLettersTest`, `Gameplay/PlayScreensTest` | design steps 1–2 | home sections, letters, lobby/game/results |
| `Questions/CommunityFlowTest` | community | 160-character limit, source rule, draft/edit/submit, request changes |
| `Ai/AiGenerationTest` | AI | request shape and parsing, retries on rate limits, refusals, no retry on client errors, cost ceiling, drafts stay drafts, hidden without a driver, rate limit |
| `Fakkroun/PartnersAndImportScreenTest` | admin | partners CRUD, import screen |
| `Gameplay/SchedulerAndLeaderboardTest` | 5 | scheduled advancing, lobby expiry, leaderboard and profile, inbox, 404 |
| `Advertising/AdvertisingTest` | 13 | separate balances, duplicate payment, idempotent consumption, no overdraft, immutable ledger and reversal, impression only after confirmation and once, wrong player, expiry, exhaustion, sponsored billed once per room, sponsored excluded from selection, reconciliation, dashboard privacy, dashboard flow, admin flow, access |
| `Analytics/PlatformAnalyticsTest` | 3 | zeros on an empty platform, figures from a real game, access |

## 4. Not done, and why

| Item | Reason / next action |
| --- | --- |
| **Running anything** | CI has no runners ([`03`](03-next-step-run-ci.md)). The repository owner must fix the Actions/billing setting. Then run the suite on PHP 8.4 and fix what it finds. |
| Real time (Reverb) | Needs `composer require laravel/reverb` and `npm install laravel-echo pusher-js`, which can't run here. The game polls every second and the scheduler advances games regardless ([`04`](04-next-step-realtime-reverb.md)). |
| QR-code image | Needs a QR library (new dependency, your approval). The link, copy, share and WhatsApp work ([`05`](05-next-step-qr-codes.md)). |
| Guest players (B1), 4-character codes (B2) | Waiting on your decision ([`12`](12-design-implementation.md)). |
| Payment gateway | Not specified. Purchases are recorded manually by admins. |
| Analytics roll-ups, load tests, anti-cheating, Sentry, security pass | [`10`](10-next-step-analytics-and-hardening.md). The analytics screen queries raw tables, which is fine at launch scale. |
| Image re-encoding of uploads | No image library installed. |
| Per-player question language, difficulty mix, rounds, notification preferences | [`07`](07-next-step-gameplay-depth.md), [`11`](11-next-step-player-experience.md). |
| About page dedication | Your decision ([`01`](01-phase-1-report.md) §9). |

## 5. Dependencies

None added. Every feature uses packages already in `composer.json` (Livewire, Spatie permission, translatable, activity log, backup, health, simple-excel) and Laravel's HTTP client for the Anthropic API.

## 6. Deploying this branch

Follow [`13-deployment.md`](13-deployment.md). In short: migrate, reseed roles and permissions, and run **a queue worker and the scheduler**. Without the scheduler, games only advance while someone has the room page open.
