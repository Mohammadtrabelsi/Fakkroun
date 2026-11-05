# Next step 11: Player experience and polish

> **Priority:** medium, before public launch · **Effort:** medium · **Spec:** §22, §23
> **Depends on:** 03
>
> **Update:** the player inbox, leaderboard, public profiles and 404 page are done. See [`14`](14-final-report.md) for what remains.

## Notifications for players (spec §23)

- `QuestionReviewed` notifications are stored in the database, but players have no inbox: the existing notifications page is in the back office. Add a player bell and `/notifications`.
- Add the remaining events: room invitation (when inviting a known user), game starting, game completed, translation approved (for translators). Use the `broadcast` channel once [`04`](04-next-step-realtime-reverb.md) lands.
- Per-user preferences for e-mail and in-app notifications (spec §3.1), defaulting to quiet.

## Interface (spec §22)

- Replace the MajidaStarter "My space" dashboard with a Fakkroun player home: play, join, recent games, rank, achievements, and submissions waiting for review.
- Turtle mascot illustrations for empty states, lobby waiting and results.
- Some form labels inherited from MajidaStarter partials are hard-coded in English (e.g. "Full name", "Save"). Move them to translation files and run `php artisan translations:sync`.
- Update the in-app guide (`/docs`), which still describes starter-kit features.
- Decide on the About page dedication (see [`01-phase-1-report.md`](01-phase-1-report.md) §9).

## Accessibility

- Gameplay: focus management when a question opens, `aria-live` announcements for "time's up" and the result, colour-independent correct/wrong markers (icons as well as colours), and respect for `prefers-reduced-motion`.
- Check contrast in dark mode for the answer states.
- Keyboard play: answers bound to 1–4 or A–D.

## Done when

- A Lighthouse accessibility score of at least 95 on the room page, in all three languages, on a mobile viewport.
- Players see the outcome of their submissions without e-mail.
