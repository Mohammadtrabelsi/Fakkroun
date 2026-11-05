# Next step 07: Gameplay depth

> **Priority:** medium · **Effort:** medium · **Spec:** §5.1, §12.1, §13.4, §16
> **Depends on:** 03 (04 for push updates)

Already done and removed from this list: the scheduled game driver, lobby expiry, game history, achievements, the weekly and all-time leaderboard, and public rooms in the lobby.

## 1. Per-player question language (spec §5.1, assessment D5)

- New room policy `per_player`: each player receives the question in their `preferredQuestionLocale()`.
- Selection: only questions whose pool rows cover **every** participating language (`GROUP BY question_id HAVING COUNT(DISTINCT language) = n` over the pool, bounded by the random-key seek).
- Fallback policy (`translations.missing_policy`): `fallback` serves `translations.fallback_locale` to players whose language is missing; `exclude` keeps the strict rule. Never an unapproved translation.
- Store the language per answer (`player_answers.language`) for analytics.

## 2. Difficulty mix (spec §12.1)

- Room setting such as `{easy: 50, medium: 30, hard: 20}`, turned into per-bucket quotas in `QuestionSelector`. Shortfalls are filled from neighbouring difficulties using the same index seek.

## 3. Reconnection rules (spec §13.4)

- After `game.reconnect_grace_seconds` the player is marked *abandoned*: they keep their points, appear in results as "left", and no longer block early closing.

## 4. Rounds (spec §12.1)

- `game_questions.round`, round summaries, and the `game.max_players_per_round` rule. This is also the hook for sponsored rounds (see [`16`](16-user-story-smart-ads.md)).

## 5. Leaderboards at scale

- Per-category leaderboards (weekly and all-time).
- A scheduled rollup of `game_results` instead of live aggregation.
- A `player_question_history` table to replace the 30-day `player_answers` scan once volumes grow.

## 6. Public rooms

- Filter the open-rooms list by language and topic.

## Done when

Each item has feature tests, and the selection queries are checked with `EXPLAIN` on MySQL against a 500k-question pool.
