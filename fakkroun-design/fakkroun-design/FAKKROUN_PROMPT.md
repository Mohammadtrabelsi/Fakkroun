# Fakkroun (فكّرون) — Implementation prompt for Claude Code

> Paste this whole file into Claude Code at the root of the **majidastarter** repo, or save it as `docs/FAKKROUN_PROMPT.md` (with the `design/` folder next to it at `docs/design/`) and tell Claude Code: *"Read docs/FAKKROUN_PROMPT.md and start Phase 0."*

---

## Your role

You are implementing **Fakkroun (فكّرون)**, a multilingual (Arabic / French / English), real-time multiplayer quiz and learning platform focused on the green and blue economy. Build it **inside the existing MajidaStarter repository** (Laravel + Livewire admin/auth starter). Reuse what the starter already has: auth, roles/permissions, admin layout, settings, and translation helpers. Don't rebuild them.

Tagline: **Think, Answer, Learn, Win!** — Arabic: **فكّر، جاوب، تعلّم… واربح!**
*Fakkroun* is Tunisian Arabic for "turtle". The mascot is a turtle that thinks before it answers.

## Ground rules

1. **Explore before you write.** Start Phase 0 by reading `composer.json`, `package.json`, `routes/`, `app/Models`, `app/Livewire` (or `app/Http/Livewire`), `resources/views/layouts`, `lang/`, the Tailwind/Vite config, and existing migrations and tests. Then summarize the stack and conventions you found (Laravel/Livewire versions, Tailwind version, roles package, translation approach, test framework) **before** changing anything.
2. **Follow the repo's conventions** for naming, folders, component style, testing (Pest or PHPUnit, whichever exists), and code style (run Pint if present).
3. **Work in phases** (below). At the end of each phase, run migrations and the full test suite, make sure the build passes (`npm run build`), commit with a clear message, and post a short summary of what was done and what's next. Work on a branch `feature/fakkroun`.
4. **Never invent facts or stats.** Keep the design's placeholders as translatable strings or config: `[PARTNER LOGO]`, `[ADD SOURCE LINK]`, `[YEAR]`. Seed questions only with the sample questions listed in this file, and mark them `status = needs_review` so a moderator verifies them.
5. **Server-authoritative game logic.** Never trust the client for timing, correctness, or scores.
6. **Ask me** before adding a new major dependency other than the ones named here.

## Design reference

The `design/` folder holds six screens exported from the design canvas:

| File | Screen | Route |
|---|---|---|
| `Main.dc.html` | Home / landing | `/` |
| `Lobby.dc.html` | Play lobby — create or join a room | `/play` |
| `Game.dc.html` | Live game room (host and player view) | `/room/{code}` |
| `Results.dc.html` | Results, podium, recap | `/room/{code}/results` |
| `Community.dc.html` | Submit a question | `/community/submit` (auth) |
| `Mobile.dc.html` | Phone view of a live question, in Arabic (RTL) | same as Game, at phone width |

**How to read these files:** they are design-canvas mockups, not production code.
- The markup and **inline styles are the visual spec**: match colors, spacing, radii, font sizes, and layout.
- `{{ ... }}`, `<sc-for>`, `<sc-if>`, `<x-dc>` and `<helmet>` are mockup templating. Translate them to Blade (`@foreach`, `@if`) and Livewire.
- The `<script type="text/x-dc">` class at the bottom of each file holds **sample data and UI state logic only**. Use it to understand behavior (selected states, answer reveal, timer ring math), then reimplement that logic in Livewire/Alpine.
- Don't load `support.js` or ship these files.
- Convert inline styles into **Tailwind utility classes** backed by the theme tokens below. Avoid copying long inline style strings.

## Design tokens

Add these to the Tailwind theme (v4 `@theme` or v3 `theme.extend`, whichever the repo uses), named exactly as below:

```
colors:
  sand        #F5EFE3   page background
  sand-200    #EBE2D0   segmented control track, active nav pill
  sand-300    #E3D8C3   borders, dividers
  sand-400    #CDBF9F   input borders
  paper       #FFFBF3   cards
  ink         #10262E   primary text, dark buttons
  ink-muted   #3E5257   body copy on sand
  ink-soft    #4A5E63   captions (passes 4.5:1 on sand)
  sea         #0B6E69   primary brand / primary buttons
  sea-dark    #084F4B   primary button bottom shadow, hover
  sea-deep    #0E3B43   game room background, dark sections
  sea-deep-2  #164A53   cards on dark backgrounds
  sea-line    #1E525B   borders on dark
  sea-mist    #B8C7C4   muted text on dark
  sun         #F2A33A   accent (ink text on top, never white)
  sun-text    #C2611A   orange eyebrow labels on sand
  leaf        #7DB57A   mascot skin, avatars
  tint-green  #E4EFE6   tint-sea #DDEDEE   tint-sun #FBE6D2
  correct     #2F8A3E   wrong #9E2B1E

fonts:
  display: "Baloo Bhaijaan 2" (500/700/800) — headings, numbers, timer
  body:    "Readex Pro" (400/500/600) — everything else
  Both support Arabic and Latin. Load them from Google Fonts or self-host with @fontsource.

radius:   10, 12, 14 (inputs/buttons), 16, 20–24 (cards), 28–36 (big panels), 999 (pills)
buttons:  min-height 44px (52–56px for primary). Primary = bg sea, text paper,
          box-shadow 0 6px 0 sea-dark (a "pressable" bottom edge)
container: max-width 1240px, side padding 24px (1320px in the game room)
```

Section eyebrows: 14px, semibold, uppercase, letter-spacing .08em, `sun-text`.
Headings: display font, weight 800, tight line-height (~1–1.05).

## Mascot

Make the turtle a Blade component, `<x-fakkroun.mascot size="..." :details="true" />`. Copy the SVG from `Main.dc.html`: the big hero turtle has thought bubbles, eye shines and cheeks; the logo version is smaller and simpler.

The turtle is drawn top-down: teal shell (`#0B6E69`) with a central orange hexagon and six light radial lines, and four `leaf` flippers. Its head on the right **faces the viewer with TWO eyes** (white eyes, ink pupils) and a smile. Keep it that way: the client asked for the two-eyed, front-facing look. On dark backgrounds the shell uses `#13857E`.

Also make `<x-fakkroun.logo />`: the mascot plus the wordmark "Fakkroun" and "فكّرون" under it.

## Internationalization (do this from the start)

- Locales: `ar` (RTL), `fr`, `en`. Store the locale in the session, use it for logged-in users, and switch it with the EN / FR / عربي segmented control in the header.
- Set `<html lang dir>` per locale. Use logical CSS (`ms-`/`me-`/`ps-`/`pe-`, `text-start`) so the layout mirrors in RTL. The mobile screen in `Mobile.dc.html` shows the expected RTL result.
- All UI strings live in `lang/{ar,fr,en}`. Category names come from the DB with translations (use the starter's approach, or `spatie/laravel-translatable` if nothing exists — ask first).
- Arabic answer letters are أ ب ج د. Latin ones are A B C D. Eastern Arabic digits in the Arabic UI are optional, behind a config flag.

## Domain model (adapt names to repo conventions)

- `categories`: slug, name (translatable), description (translatable), icon, color token, is_priority (the 7 planet themes), sort, sponsor_id nullable
- `questions`: category_id, locale, difficulty (easy/medium/hard), body, explanation, source_url, author_id nullable, origin (`admin`/`community`/`ai_assisted`), status (`draft`/`needs_review`/`changes_requested`/`approved`/`rejected`), moderator_note, reviewed_by, reviewed_at, times_played, times_correct
- `question_options`: question_id, label, is_correct, position (exactly 4 per question, exactly 1 correct — validate this)
- `rooms`: code (4 chars, unambiguous alphabet without 0/O/1/I), name, host_id, locale, difficulty, question_count (10/15/20), seconds_per_question (10/20/30), is_private, max_players, status (`lobby`/`playing`/`finished`), current_index, started_at, finished_at
- `room_category` pivot
- `room_players`: room_id, user_id nullable (guests are allowed: nickname + session token), nickname, avatar_color, score, correct_count, joined_at, last_seen_at
- `room_questions`: room_id, question_id, position, shown_at, closes_at
- `player_answers`: room_player_id, room_question_id, question_option_id, answered_at, is_correct, points (unique on player + room_question)
- `partners`: name, logo_path, url, type (`partner`/`sponsor`), is_active

Use enums for statuses and difficulty. Add factories for every model.

## Game rules

- The host creates a room and gets a code. Players join by code, by link `/room/{code}`, or from the public "Open rooms" list (public rooms in `lobby` status only).
- The host presses Start. The server picks `question_count` approved questions from the selected categories, in the room's locale and difficulty, with no repeats.
- Per question, the server sets `shown_at` / `closes_at`. Clients show a countdown ring computed from the server time; correct for clock skew with a server-time offset.
- Each player gets one answer, locked once submitted. Answers after `closes_at` are rejected.
- Scoring (put it in config): `correct ? 50 + round(100 × remaining_seconds / seconds_per_question) : 0`.
- Reveal happens when everyone has answered or time runs out. It shows the correct option in green, the player's wrong pick in red, the others dimmed, then the explanation, the source and "Question by @author".
- The players sidebar shows rank, avatar initial, name, an answered/thinking status (never *which* answer), and the score, sorted live.
- After the last question the room status becomes `finished` and everyone is redirected to Results: podium (2-1-3 layout), standings table, and "What we learned" recap with ✓/✗ per question. "Play again, same room" reuses the code with new questions.

## Real-time

Use **Laravel Reverb + Laravel Echo** with Livewire's `#[On('echo-presence:...')]` listeners.
- Presence channel `room.{code}` tracks who is in the room.
- Events: `PlayerJoined`, `PlayerLeft`, `GameStarted`, `QuestionShown` (no `is_correct` in the payload!), `PlayerAnswered` (player id only), `QuestionRevealed`, `GameFinished`.
- A queued job or scheduled check closes questions when time is up, so a room never stalls when the host leaves. Pass host role to the next player if the host disconnects in the lobby.
- Rate-limit joins and answers. Authorize channels for both guests and users.

## Screens, in build order

**Phase 0 — Recon and plan.** Summarize the repo, then propose the file and component structure and the migrations list. Wait for my OK.

**Phase 1 — Foundation.** Tokens, fonts, mascot and logo components, public layout (header with nav: Play, Categories, Learn, Community, Leaderboard, language switch, Sign in, "Play now"; footer as in `Main.dc.html`), locale switching and RTL.

**Phase 2 — Home (`Main.dc.html`).** Every section in order:
1. Hero, with the join-code form posting to the join flow
2. "Why a turtle?" band
3. How it works
4. Categories grid, from the DB (the 7 priority themes plus the dark "…and everything else" card with general topic chips)
5. Learn as you play, with a sample reveal card
6. Who plays
7. Community band, linking to submit
8. Partners, from the `partners` table, falling back to placeholders
9. Orange CTA band
10. Footer

Make it responsive down to 360px.

**Phase 3 — Data and admin.** Migrations, models, enums, factories, and the seeder: the 13 categories with ar/fr/en names, plus the sample questions below. Then admin CRUD in the starter's admin panel for categories, questions (with an options repeater and a 4-options/1-correct rule), partners, and a **moderation queue** (approve / request changes with a note / reject).

**Phase 4 — Lobby (`Lobby.dc.html`).** A Livewire component with category multi-select chips, segmented controls (difficulty, number of questions, seconds, language), a private toggle, and a "Create room · N questions" button. The aside has join-by-code and the live list of open rooms. Guests pick a nickname before joining.

**Phase 5 — Live game (`Game.dc.html` + `Mobile.dc.html`).** Waiting-room state (player list, code shown big, host Start button), question state, reveal state, and the players sidebar. On phones the sidebar collapses to a compact "6 of 8 answered" line. Add Reverb wiring and the timer.

**Phase 6 — Results (`Results.dc.html`).** Podium, standings, recap, Play again / New room / Share (Web Share API with copy-link fallback).

**Phase 7 — Community (`Community.dc.html`).** Submission form (category, difficulty, language, question ≤160 chars, 4 answers with a radio for the correct one, explanation, required source URL), Save draft / Send for review, "What happens next" panel, and "Your questions" with status badges (Live / In review / Needs changes + moderator note).

"Draft with AI" posts the topic and fact to a service class behind an interface (`QuestionDrafter`). Its first implementation uses the Anthropic API via HTTP, with the key in `.env` as `ANTHROPIC_API_KEY`. It returns a draft for the user to edit and never auto-publishes. It's hidden when no key is set.

**Phase 8 — Polish.** Global leaderboard page (weekly / all-time), user profile with stats, SEO meta and Open Graph per locale, a 404 page with the mascot, `prefers-reduced-motion` support, and a Lighthouse/a11y pass.

## Seed questions (status `needs_review`; a moderator must verify them and add the sources)

1. *Marine Biodiversity, medium*
   - Q: Which seagrass, found only in the Mediterranean, forms vast meadows in the Gulf of Gabès?
   - Options: Posidonia oceanica ✔ / Zostera marina / Halophila stipulacea / Caulerpa taxifolia
   - Why: Posidonia grows only in the Mediterranean; its meadows shelter young fish, reduce beach erosion and store carbon in the seabed.
2. *Marine Biodiversity, easy*
   - Q: Which sea turtle species nests on the Kuriat Islands, off Monastir?
   - Options: Loggerhead (Caretta caretta) ✔ / Leatherback / Green turtle / Hawksbill
3. *Blue Economy, easy*
   - Q: What does the "blue economy" mean?
   - Options: Using ocean resources sustainably for growth and jobs while keeping marine ecosystems healthy ✔ / plus 3 distractors
4. *Sustainable Development, easy*
   - Q: How many Sustainable Development Goals did the UN adopt in 2015?
   - Options: 17 ✔ / 8 / 12 / 21
5. *Renewable Energy, easy*
   - Q: What does a photovoltaic panel turn into electricity?
   - Options: Sunlight ✔ / Wind / Heat from the ground / Tides
6. *Blue Economy, medium*
   - Q: Which archipelago off Sfax is known for its traditional "charfia" fixed fishing traps?
   - Options: Kerkennah ✔ / Djerba / Zembra / Galite

Provide each question in ar, fr and en as separate locale rows.

## Accessibility and quality bar

- Use real `<button>`, `<a href>`, `<label for>` elements and `aria-pressed` on toggles, plus `role="timer"` with an `aria-label` on the countdown and `aria-live="polite"` on the reveal panel. Icon-only buttons get an `aria-label`.
- Text contrast is at least 4.5:1, and touch targets are at least 44px.
- Never use colour alone for meaning: show "Correct" / "Your answer" text labels next to green/red.
- Feature tests for: room creation, join by code (valid, invalid and full), the answer locking and timing window, scoring, a question never leaking `is_correct` before reveal, moderation transitions, and locale/RTL switching.
- No N+1 queries on the lobby or leaderboard (eager load; check with the debugbar or `preventLazyLoading`).

## Definition of done (for each phase)

Migrations run on a fresh DB, seeders work, tests are green, `npm run build` passes, the pages match the design at 1440px and 390px in EN and AR, and you post a short changelog.
