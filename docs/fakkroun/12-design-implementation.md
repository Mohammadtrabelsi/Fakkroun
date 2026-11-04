# Design implementation (fakkroun-design)

> **Branch:** `claude/fakkroun-next-steps` (from `develop` @ `abc2223`)
> **Source:** `fakkroun-design/fakkroun-design/FAKKROUN_PROMPT.md` and the six mockups in `fakkroun-design/fakkroun-design/design/`
> **Status:** step 1 (foundation + home) written, **not yet executed** (CI still cannot start runners; see [`03`](03-next-step-run-ci.md)).

The brief arrived after the MVP. Most of its domain model already exists under the repository's own names, as the brief says to "adapt names to repo conventions". This file tracks how each part of the brief maps to the code, what has changed, and which decisions are yours to make.

## Step 1: done in this branch

| Brief | Implementation |
| --- | --- |
| Design tokens | `resources/css/app.css` `@theme`: `sand…`, `paper`, `ink…`, `sea…`, `sun`, `sun-text`, `leaf`, `tint-*`, `correct`, `wrong`, `font-display` (Baloo Bhaijaan 2), `font-body` (Readex Pro). Helpers `.fk-btn`, `.fk-btn-primary` (pressable bottom edge), `.fk-input`, `.fk-eyebrow`, `.fk-h1/.fk-h2`, `.fk-container`. Focus ring in `sun`, plus `prefers-reduced-motion`. |
| Fonts | Google Fonts `<link>` in the public layout (no new dependency). |
| Mascot / logo | `<x-fakkroun.mascot size :details :dark :label>` (two front-facing eyes; `#13857E` shell on dark) and `<x-fakkroun.logo>`. The app-shell `brand-mark` now renders the mascot. |
| Public layout | `<x-layouts.fakkroun>`: header (logo, Play / Categories / Learn / Community, Leaderboard once the route exists, EN · FR · عربي segmented control with `aria-current`, Sign in, Play now) and the footer from `Main.dc.html`. Logical properties for RTL. |
| Home (`Main.dc.html`) | All ten sections in order: hero with the join-code form (`GET /play/join?code=`), "Why a turtle?", how it works, the priority category grid from the DB with the dark "…and everything else" card, learn-as-you-play reveal card, who plays, community band, partners from the DB with `[PARTNER LOGO]` fallback, orange CTA, footer. |
| Data | `categories.is_priority` (the seven planet themes, backfilled by the migration) and the `partners` table + `App\Models\Partner`. Category descriptions and Arabic names now follow the design. |
| Placeholders, never invented | `config('fakkroun.brand')`: `[YEAR]`, `[PARTNER LOGO]`, `[ADD SOURCE LINK]`. |
| Seed questions | Replaced my 18 published demo questions with the brief's six samples (en/fr/ar), **pending review, no source**. The answer display order is varied so the correct answer is not always "A". |
| Scoring | Defaults now follow the brief: `50 + round(100 × remaining / duration)` (base 50 at every difficulty, speed bonus 100). Still admin-tunable. |
| Arabic letters | أ ب ج د in Arabic rooms (`QuestionChoice::letter($language)`). |
| Name meaning | About page fixed: *fakkroun* is Tunisian Arabic for "turtle", and sounds like فكّر ("think"). |
| Tests | `Fakkroun/HomePageTest` (sections, two eyes, priority grid, partners fallback, join form, Arabic RTL), `Gameplay/AnswerLettersTest`. The navbar tests now target `/docs`, which still uses the configurable marketing navbar. |

## Step 2: play screens

| Screen | Status |
| --- | --- |
| **Lobby** (`Lobby.dc.html`) | Done at **`/play`** (`rooms.create`): category chips with `aria-pressed`, segmented controls (Mixed/Easy/Medium/Hard, 10/15/20 questions, 10/20/30 s, EN/FR/عربي, all clipped to the admin limits), duplicate-rule toggle, private toggle (unticked creates a **public** room), and "Create room · N questions". The aside holds join-by-code (pre-filled by `?code=`) and **Open rooms**, which lists public waiting rooms and refreshes every 10 s. |
| **Game** (`Game.dc.html`, `Mobile.dc.html`) | Done: dark `sea-deep` room; waiting state with the big code, copy, share and WhatsApp, room settings and players; category · difficulty pill; **countdown ring** driven by the server's remaining time (a relative value, so no client-clock skew); answer states (default pressable, *Your answer* while waiting, then *Correct* in green and *Your answer* in red with text labels, others dimmed); reveal panel (`aria-live`) with verdict, explanation, source or `[ADD SOURCE LINK]`, "Question by @author" for community questions, and points; players sidebar with rank, avatar, answered/thinking (never which answer) and live score, which on phones collapses to the "N of M answered" line. |
| **Results** (`Results.dc.html`) | Done at **`/rooms/{ulid}/results`** (the room page redirects there at the end): podium in 2-1-3 order, standings, and "What we learned" with ✓/✗ plus text labels. *Play again* opens a **new room with the same settings** (one game per room keeps history and the double-start guard simple, so the code isn't reused, unlike the brief's "same room"). *New room* and *Share* (Web Share, with a copy-link fallback). |
| **Community** (`Community.dc.html`) | **Next:** restyle the submit page and "Your questions" (≤ 160 characters, required source URL, Save draft / Send for review, "What happens next", Live / In review / Needs changes badges with the moderator's note). |
| **Admin** | **Next:** CRUD for partners. |

Tests: `Gameplay/PlayScreensTest`, covering the lobby (create with chips and segments, category required, public/private listing, join by code and query prefill), results (podium, standings, recap, members only, redirect before the end), play-again, and the answered-status state without leaking answers.

## Decisions for you (the brief differs from the master specification or from the current code)

| # | Topic | Brief | Current | Proposal |
| --- | --- | --- | --- | --- |
| B1 | **Guest players** | Guests join with a nickname and a session token | Master spec §12: registered players only; `room_players.user_id` is required | Allow guests **only to join** (not to host), with a nickname, rate-limited by IP; results are not kept on any profile. Needs your confirmation, since it changes the schema and the anti-abuse model. |
| B2 | **Room code length** | 4 characters | 6 characters, unique for the platform's lifetime | 4 characters are fine if codes are **recycled** once a room has finished (unique only among open rooms). Confirm and I'll switch. |
| B3 | **Question statuses** | `draft / needs_review / changes_requested / approved / rejected` | `draft / pending / under_review / approved / rejected / published / unpublished` | Keep the richer lifecycle (spec §7.3) and add a **"Request changes"** action that sends the question back to its author with a note (shown as "Needs changes"). |
| B4 | **Questions per locale** | One row per locale | One question with per-language translations approved separately (spec §5.2/§5.4) | Keep the current model; it satisfies the brief's "provide each question in ar, fr and en". |
| B5 | **Real time** | Reverb + Echo | Polling (MVP) | Reverb is named in the brief, so it's allowed, but it needs `composer require`/`npm install`, which can't run in this environment. Do it as soon as CI or a local PHP 8.4 is available ([`04`](04-next-step-realtime-reverb.md)). |
| B6 | **"Draft with AI"** | `QuestionDrafter` via the Anthropic API, hidden without `ANTHROPIC_API_KEY` | Not built | Implement with Laravel's HTTP client (no SDK dependency), as part of [`08`](08-next-step-ai-question-generation.md). |
