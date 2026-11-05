# Next step 11: Player experience and polish

> **Priority:** medium, before public launch · **Effort:** medium · **Spec:** §22, §23
> **Depends on:** 03

Already done and removed from this list: the player inbox and bell, the leaderboard, public profiles, the 404 page, game history, achievements, stats on the player home, and keyboard answering.

## Notifications (spec §23)

- Remaining events: room invitation (when inviting a known user), game starting, game completed, and translation approved (for translators). Use the `broadcast` channel once [`04`](04-next-step-realtime-reverb.md) lands.
- Per-user preferences for e-mail and in-app notifications (spec §3.1), defaulting to quiet.

## Interface (spec §22)

- Show "submissions waiting for review" on the player home.
- Turtle mascot illustrations for lobby waiting and results.
- Labels from the starter partials that are hard-coded in English (for example "Full name", "Save"): move them to translation files and run `php artisan translations:sync`.
- Update the in-app guide (`/docs`), which still describes starter-kit features.
- **Your decision:** the About page dedication (see [`01-phase-1-report.md`](01-phase-1-report.md) §9).

## Accessibility

- Gameplay: focus management when a question opens, an `aria-live` announcement for "time's up", correct/wrong markers that don't rely on colour alone (icons as well), and `prefers-reduced-motion`.
- Check dark-mode contrast for the answer states.

## Done when

- The room page scores at least 95 for accessibility in Lighthouse, in all three languages, on a mobile viewport.
