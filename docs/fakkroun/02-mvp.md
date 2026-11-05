# Fakkroun MVP: First Playable Version

> **Date:** 2026-11-06 · **Branch:** `ccr-fc788502-hcpb6b` (draft PR #1)
> **Builds on:** Phase 1 ([`01-phase-1-report.md`](01-phase-1-report.md))
> **Next steps:** one file each, `03-…` to `11-…` in this folder (index at the end).

## ⚠️ Status: written, not yet executed

Neither the MVP code nor its tests have been run. This environment has PHP 8.3, the project needs PHP 8.4, and dependencies cannot be installed here. GitHub Actions on the repository currently fails before starting any job (see [`03-next-step-run-ci.md`](03-next-step-run-ci.md)).

What was checked: `php -l` on every PHP file, including the PHP part of each Livewire component; Blade directive balance on every changed template; translation-key parity across en/fr/ar; and a stand-alone run of the duplicate-detection normalisation. **Treat the MVP as unverified until CI is green.**

## 1. What the MVP does

One complete loop: **write a question → moderate it → play it live with friends → see the results.**

| Area | In the MVP |
| --- | --- |
| **Question bank** (spec §7) | Four-choice questions with stable choice ids. The correct answer is stored as a choice id. Original text and translations live in separate rows. Difficulty, source/reference, origin. Full lifecycle: draft → pending → under review → approved/rejected → published ⇄ unpublished. Revision snapshots on every content change. Editing an approved question sends it back for review. Soft delete keeps game history. |
| **Translations** (spec §5) | Each language is approved on its own. Only *published question × approved translation* pairs are ever playable (`question_language_pool`). Editing the original text sends the other languages back for review. |
| **Quality checks** (spec §8) | Required fields, exactly four distinct answers, valid correct answer, subcategory belongs to its category, reference URL format, reference required where the category demands it. Exact duplicates are rejected using an Arabic- and accent-aware normalised hash. Near-duplicates (word overlap) and warnings ("answer appears in the question", flagged terms) are shown to moderators. |
| **Moderation** (spec §3.3, §7.4) | A queue with three tabs: questions, translations and player reports. It is scoped to the moderator's categories, and the category validation policy is enforced (admin-only categories cannot be approved by moderators, though moderators may still reject). Every action is logged with actor, status change and reason. |
| **Community submissions** (spec §9) | Players submit from `/questions/submit` and track status on `/questions/mine`. A daily limit applies, categories can opt in or out, duplicates are rejected, and nothing is auto-published unless an admin enables it. Authors get an in-app notification of the decision. |
| **Reports** | `POST /api/questions/{uuid}/reports`, one open report per player per question, rate-limited. Moderators resolve or dismiss reports in the queue. |
| **Import / export** (spec §11) | Backend complete: CSV, XLSX and JSON with automatic column mapping, preview, queued processing, per-row error report and duplicate skipping. Imports never publish. Export of approved and published questions with every translation (CSV/XLSX/JSON) is available from the admin list. *The import screen is a next step.* |
| **Rooms** (spec §12) | Create a room (topics, question count, seconds per question, difficulty, language, duplicate rule, max players) within the admin-set limits. It gets a 6-character code without look-alike characters and an invitation link `/join/{code}`. Guests are sent to log in and then brought back. Joining is limited by capacity, with a clear message when full. The lobby offers copy, Web Share and WhatsApp sharing. Rooms are capped by a platform-wide concurrent limit. If the host leaves the lobby, the room passes to the next player, or is cancelled if nobody is left. |
| **Live game** (spec §13) | **Server-authoritative.** The server sets deadlines and times answers on arrival; clients never send times or scores. One answer per player, enforced by a database unique index. Late answers and choices from other questions are refused. A question closes at its deadline or as soon as every present player has answered. The correct answer is never sent before a question closes. Results show for 6 s, then the next question opens. Reloading the page reconnects: recorded answers and the remaining time come back from the server. Two simultaneous starts are prevented by `games.room_id` being unique. |
| **Duplicate-answer rule** (spec §14) | Per room, explained in the lobby. When ≥ threshold players (admin setting, default 2) give the same answer, none of those answers score, correct or not. The reveal marks excluded answers. |
| **Scoring and results** (spec §15) | Points by difficulty plus a linear speed bonus (admin settings). Points are stored per answer. There's a running leaderboard and final results with rank, winner, correct/wrong/excluded counts and total time. Questions get `times_played` and `times_correct`. |
| **Question selection** (spec §16) | An index seek on `random_key` in the pool, never loading the bank. Questions the players answered in the last 30 days are avoided first. Chosen rows are reshuffled and stamped, which spreads usage across rooms. |
| **Sample content** | `FakkrounDemoQuestionSeeder` (demo tier) adds the design brief's six sample questions in en/fr/ar. They are **pending review with no source**, so a moderator must verify each one and add its reference before approving and publishing it. Until then, no question is playable on a fresh install. *(Changed after the MVP: the first version published 18 questions I had written, which the brief does not allow.)* |

### Deliberate MVP simplifications

These are listed so nobody mistakes them for finished features. Each has a next-step file.

1. **Polling instead of WebSockets.** The room page polls every second (`wire:poll`), which is simple and robust but heavier than broadcasting. → [`04`](04-next-step-realtime-reverb.md)
2. **No QR code image yet.** The invitation link, copy, share and WhatsApp work; the QR picture needs a library. → [`05`](05-next-step-qr-codes.md)
3. **No import screen.** The service, job and tests exist. (done since: **Admin → Questions → Import**)
4. **One language per room.** Everyone plays in the room's language. Each player's own question language is a next step. → [`07`](07-next-step-gameplay-depth.md)
5. **One difficulty or "any".** Difficulty *distribution* (e.g. 50/30/20) is a next step. → [`07`](07-next-step-gameplay-depth.md)
6. **Game progress is driven by players' polls.** If nobody has the page open, the game simply waits. → [`07`](07-next-step-gameplay-depth.md)
7. **Admin and moderation copy** exists in all three languages, but some form labels still come from MajidaStarter partials in English.

## 2. How to try it

```bash
composer install && npm ci && npm run build       # PHP 8.4
cp .env.example .env && php artisan key:generate
php artisan migrate --seed                        # roles, admin, 13 categories, 6 sample questions (pending review), demo users
php artisan storage:link
php artisan queue:work &                          # imports (sync queue also works locally)
php artisan serve
```

1. Sign in as `admin@fakkroun.test` / `password` (see `AdminUserSeeder`). Admins hold every permission, including `play games`.
2. Register a second account in another browser (new accounts are players).
3. **Moderation** → open each sample question, add a source link on its edit page, then *Approve and publish* it (and approve its French and Arabic translations).
4. **New game** → pick *Marine Biodiversity*, *Blue Economy*, *Renewable Energy* and *Sustainable Development*, English, 6 questions → *Create room*.
5. Open the invitation link in the second browser → the player joins the lobby → *Start the game*.
6. Answer on both screens. Try the same answer on both with the duplicate rule on.
7. Submit a question as the player, then approve and publish it from **Moderation** as the admin.

> Demo users created by `DatabaseSeeder` (before Phase 1) have **no role**. Give them `player` from **Users → Edit**, or register new accounts.

## 3. Code map

| Path | Content |
| --- | --- |
| `app/Domain/Questions/` | `QuestionService`, `ModerationService`, `QuestionValidator`, `DuplicateDetector` (+ `Contracts/SimilarQuestionFinder`, `Support/TokenOverlapFinder`), `QuestionPool`, `QuestionQueries`, `SubmissionService`, `ReportService`, `Imports/{QuestionImportService,QuestionExportService}`, `Jobs/ProcessQuestionImport`, `Notifications/QuestionReviewed`, `Models/*`, `Enums/*` |
| `app/Domain/Gameplay/` | `RoomService`, `GameEngine`, `QuestionSelector`, `ScoringEngine`, `DuplicateAnswerRule`, `RoomCodeGenerator`, `Models/*`, `Enums/RoomStatus` |
| `database/migrations/2026_11_05_*`, `2026_11_06_*` | Question bank (9 tables) and multiplayer (6 tables) |
| `resources/views/pages/admin/questions/`, `⚡moderation` | Question list and editor, moderation queue |
| `resources/views/pages/questions/` | Player submission and "My questions" |
| `resources/views/pages/rooms/` | Create, join, room (lobby → game → results) |
| `lang/{en,fr,ar}/{questions,play}.php` | All new UI text |
| `tests/Feature/Questions/`, `tests/Feature/Gameplay/` | 66 new tests (see §5) |

### Routes

| Route | Who |
| --- | --- |
| `/play`, `/play/join`, `/rooms/{ulid}`, `/join/{code}` | `play games` |
| `/questions/submit`, `/questions/mine` | `submit questions` |
| `/admin/questions…`, `/admin/questions/export/{csv,xlsx,json}` | `manage questions` |
| `/admin/moderation` | `moderate questions` (scoped by category) |
| `GET/POST /api/questions/submissions`, `POST /api/questions/{uuid}/reports` | `submit questions` / `play games` |

## 4. Database

Question bank: `questions`, `question_choices`, `question_translations`, `question_revisions`, `question_moderation_logs`, `question_reports`, `question_language_pool`, `question_import_batches`, `question_import_errors`.
Multiplayer: `rooms`, `room_players`, `games`, `game_questions`, `player_answers`, `game_results`.

Invariants the database itself guarantees:
- one answer per (question in game, player);
- one game per room;
- one membership row per (room, player);
- unique room codes;
- one translation per (question, language);
- one pool row per (question, language).

## 5. Tests written (not yet run)

| File | Tests | Covers |
| --- | --- | --- |
| `Questions/QuestionLifecycleTest` | 20 | stable choices, structural rules, subcategory check, exact and near duplicates, warnings, approve/publish → pool, independent translation approval, revalidation on edit + revisions, no-op save, admin-only policy, moderator scope, reference requirement, rejection reason + log, invalid transitions, unpublish, delete, admin editor end-to-end, access control |
| `Questions/SubmissionsAndReportsTest` | 11 | submission → queue, submit page, daily limit, closed categories, permission, auto-publish setting, author notification, withdraw, API, reports (once, resolve), report rate limit |
| `Questions/QuestionImportExportTest` | 6 | header mapping, CSV import (create, duplicate skip, bad row, translations, letter answers, file cleanup), preview, nested JSON, required mapping, export + permissions |
| `Gameplay/RulesTest` | 4 | duplicate rule (spec example, thresholds, edge cases), scoring |
| `Gameplay/RoomTest` | 11 | code/invitation, limits, concurrent cap, join by code, idempotent join, full room message, invitation for guests, unknown code, join page, host hand-over/cancel, permission, pages |
| `Gameplay/GameEngineTest` | 14 | start rules, double start, rejoin vs newcomer, pool-only selection by language and topic, empty pool, hidden answer, one timed answer, late/foreign answers, early close + reveal + advance, deadline close, duplicate rule in play, full game ranking + stats, reconnection state, Livewire room page |

## 6. Security notes

- Scores, timers, correct answers and rankings are all computed on the server. The client sends only a choice id.
- Correct answers leave the server only after a question closes; a test asserts the payload shape.
- Membership is checked on every answer. Choices must belong to the current question.
- Invitation, submission and report endpoints are throttled. Room codes avoid look-alike characters and are not sequential.
- Exports neutralise spreadsheet formulas (inherited from `SpreadsheetService`). Uploaded import files are deleted once processed.

## 7. Next steps (one file each)

| # | File | Summary |
| --- | --- | --- |
| 03 | [`03-next-step-run-ci.md`](03-next-step-run-ci.md) | Unblock GitHub Actions, run everything on PHP 8.4, fix what fails. **Blocking.** |
| 04 | [`04-next-step-realtime-reverb.md`](04-next-step-realtime-reverb.md) | Replace polling with Laravel Reverb broadcasting |
| 05 | [`05-next-step-qr-codes.md`](05-next-step-qr-codes.md) | QR codes for room invitations |
| 06 | *(removed: done)* | Admin import screen on top of the finished import service |
| 07 | [`07-next-step-gameplay-depth.md`](07-next-step-gameplay-depth.md) | Per-player languages, difficulty mix, scheduled game driver, history, public rooms, achievements |
| 08 | *(removed: done)* | Phase 4: AI-assisted question generation |
| 09 | *(removed: done)* | Phase 5: advertising, credits, impressions, ledger |
| 10 | [`10-next-step-analytics-and-hardening.md`](10-next-step-analytics-and-hardening.md) | Phase 6: analytics, load tests, security review, deployment |
| 11 | [`11-next-step-player-experience.md`](11-next-step-player-experience.md) | In-app notifications for players, polish, accessibility, remaining copy |
