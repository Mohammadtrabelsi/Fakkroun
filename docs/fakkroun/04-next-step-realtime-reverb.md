# Next step 04: Real-time with Laravel Reverb

> **Priority:** high · **Effort:** medium · **Spec:** §13, §27, assessment §6
> **Depends on:** 03

## Why

The MVP room page polls `/livewire/update` every second per player. That is correct (the server stays authoritative) but costs one request per player per second. It also adds up to one second of latency between "question closed" and everyone seeing it, and it stops progress when nobody has the page open.

## Design

- **Transport:** `laravel/reverb` (first-party, Pusher protocol, self-hosted) + `laravel-echo` + `pusher-js`. Run it in Docker next to php-fpm. Use Redis for horizontal scaling.
- **Channels:** presence channel `room.{ulid}` (authorised by `RoomService::isMember`). Presence `here/joining/leaving` replaces the `last_seen_at` heartbeat for "present players".
- **Events** (all `ShouldBroadcast`, payloads built from `GameEngine::state()`):
  `PlayerJoined`, `PlayerLeft`, `GameStarted`, `QuestionOpened` (**never** containing the correct answer), `AnswerCountChanged` (count only), `QuestionClosed` (reveal + leaderboard), `GameCompleted`.
- **Driver for time:** a queued `AdvanceGame` job dispatched with a delay at each `open()` and `close()` (delay = deadline, and closed_at + reveal). It calls the existing idempotent `GameEngine::tick()`. Duplicate or late jobs are harmless by construction. Keep a 5-second polling fallback for clients whose WebSocket fails.
- **Per-player data** (my answer, my points) stays out of broadcasts. Clients fetch it with a single request on `QuestionClosed`, or through a private `user.{id}` channel.

## Tasks

1. Install Reverb (`php artisan install:broadcasting`). Add `config/broadcasting.php`, `routes/channels.php`, and Echo bootstrap in `resources/js/app.js`.
2. Events and `routes/channels.php` authorisation, plus tests with `Event::fake()` and `Broadcast` assertions.
3. `AdvanceGame` job, queue `games` (use the Redis queue in production; the database queue's polling makes deadlines late).
4. Room page: listen with Alpine + Echo and call `$wire.$refresh()` on events. Lower `wire:poll` to the 5-second fallback.
5. Health check for Reverb (spatie/laravel-health custom check) and a Docker service, supervisor entries `reverb:start` and `queue:work --queue=games,default`.
6. Load test with 100 rooms × 10 players (see [`10`](10-next-step-analytics-and-hardening.md)).

## Done when

- A question closes for everyone within ≈200 ms of its deadline with no page open on the host.
- No payload before `QuestionClosed` contains `correct_choice_id` (test).
- Request rate per player drops from 1/s to event-driven.
