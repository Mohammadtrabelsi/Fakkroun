# Next step 07: Gameplay depth

> **Priority:** medium · **Effort:** medium · **Spec:** §5.1, §5.3, §12.1, §13.4, §16, §15
> **Depends on:** 03 (04 for the scheduled driver)

The MVP plays a complete, fair game. These items bring it to the full spec.

## 1. Per-player question language (spec §5.1, assessment D5)

- New room policy `per_player`: each player receives the question in their `preferredQuestionLocale()`.
- Selection: only questions whose pool rows cover **every** participating language (a `GROUP BY question_id HAVING COUNT(DISTINCT language) = n` over the pool, bounded by the random-key seek).
- Fallback policy (`translations.missing_policy`): `fallback` serves `translations.fallback_locale` to players whose language is missing; `exclude` keeps the strict rule. Never an unapproved translation.
- Store the language per answer (`player_answers.language`) for analytics.

## 2. Difficulty distribution (spec §12.1)

- Room setting e.g. `{easy: 50, medium: 30, hard: 20}` → per-bucket quotas in `QuestionSelector`, filling shortfalls from neighbouring difficulties, in the same index-seek pattern.

## 3. Game driver without clients (spec §13)

- Covered by the `AdvanceGame` job in [`04`](04-next-step-realtime-reverb.md). Until then, a scheduled `games:advance` command every 5 s ticks games with an expired deadline.
- `rooms:expire` (scheduled): cancel waiting rooms past `expires_at`, and complete abandoned in-progress games.

## 4. Reconnection rules (spec §13.4)

- Define and implement what happens after `game.reconnect_grace_seconds`: the player is marked *abandoned*, keeps their points, appears in results as "left", and no longer blocks early closing. The current behaviour already approximates this through `last_seen_at`.

## 5. Rounds (spec §12.1)

- `game_questions.round`, round summaries, and a "maximum players per round" rule (`game.max_players_per_round`). This is also the hook for sponsored rounds (Phase 5).

## 6. History, leaderboards, achievements

- **Done:** `/play/history` lists finished games with a link to each results page. **Done:** achievements (`user_achievements`: first game, first win, 10 games, perfect game, hat-trick, quick thinker). Still to do: per-category leaderboards and a rollup.
- `/play/history`: past games with per-question review (question text from the revision snapshot at `question_version`, so later edits don't change history).
- Global and per-category leaderboards (weekly and all-time), computed from `game_results` by a scheduled rollup.
- Achievements (`user_achievements`): first win, 10 games, perfect game, category streaks.
- `player_question_history` table to replace the 30-day `player_answers` scan once volumes grow.

## 7. Public rooms

- `visibility = public` + a lobby browser filtered by language and topic, with rate-limited joins.

## Done when

Each item has feature tests. The selection queries are checked with `EXPLAIN` on MySQL against a 500k-question pool.
