<?php

use App\Domain\Advertising\AdService;
use App\Domain\Gameplay\Enums\RoomStatus;
use App\Domain\Gameplay\Models\GameQuestion;
use App\Domain\Gameplay\GameEngine;
use App\Domain\Gameplay\Models\Room;
use App\Domain\Gameplay\RoomService;
use App\Models\Category;
use App\Services\PlatformSettings;
use App\Support\Livewire\InteractsWithToasts;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Live room (fakkroun-design/design/Game.dc.html + Mobile.dc.html): waiting
 * room, question, reveal; results live on their own page. MVP transport is a
 * one-second poll; the server decides everything (GameEngine).
 */
new #[Layout('components.layouts.fakkroun')] #[Title('Room')] class extends Component
{
    use InteractsWithToasts;

    public Room $room;

    public function mount(Room $room, RoomService $rooms): void
    {
        $this->authorize('play games');
        $this->room = $room;

        if (! $rooms->isMember($room, auth()->user())) {
            $this->redirect(route('rooms.invitation', $room->code));
        }
    }

    /**
     * wire:poll: heartbeat, let the server advance the game, leave for the
     * results page once the game is over.
     */
    public function refresh(RoomService $rooms, GameEngine $engine): void
    {
        $this->room->refresh();
        $rooms->touch($this->room, auth()->user());

        if ($game = $this->room->game) {
            if ($engine->tick($game)->isCompleted()) {
                $this->redirect(route('rooms.results', $this->room), navigate: true);
            }
        }
    }

    public function start(GameEngine $engine): void
    {
        try {
            $engine->start($this->room, auth()->user());
            $this->room->refresh();
        } catch (\DomainException|AuthorizationException $e) {
            $this->toast($e->getMessage(), 'error');
        }
    }

    public function answer(int $choiceId, GameEngine $engine): void
    {
        if (($game = $this->room->game) === null) {
            return;
        }

        try {
            $engine->submitAnswer($game, auth()->user(), $choiceId);
        } catch (\DomainException|AuthorizationException $e) {
            $this->toast($e->getMessage(), 'error');
        }
    }

    /**
     * Called by the browser once the ad has really been on screen. The
     * server-issued token makes it single-use; billing happens only here.
     */
    public function confirmImpression(string $token, AdService $ads): void
    {
        $ads->confirm($token, auth()->user());
    }

    public function leave(RoomService $rooms): void
    {
        $rooms->leave($this->room, auth()->user());
        $this->redirect(route('dashboard'), navigate: true);
    }

    #[Computed]
    public function viewData(): array
    {
        $rooms = app(RoomService::class);
        $game = $this->room->game;

        $state = $game ? app(GameEngine::class)->state($game, auth()->user()) : null;
        $ad = null;

        if ($state !== null && $state['phase'] === 'reveal') {
            $gq = app(\App\Domain\Gameplay\GameQuestionService::class)->query()->where('game_id', $game->id)->where('position', $state['position'])->with('question')->first();
            $ad = $gq ? app(AdService::class)->offer($this->room, $gq, auth()->user())?->load('creative') : null;
        } elseif ($state === null && $this->room->status === RoomStatus::Waiting) {
            // Waiting room (story 16 S3): once per player and room.
            $ad = app(AdService::class)->offerLobby($this->room, auth()->user())?->load('creative');
        }

        return [
            'ad' => $ad,
            'state' => $state,
            'players' => $this->room->players()->get(['users.id', 'users.name', 'users.avatar_path']),
            'isHost' => $this->room->host_id === auth()->id(),
            'ready' => $rooms->isReady($this->room),
            'minPlayers' => (int) app(PlatformSettings::class)->get('game.min_players'),
            'categoryNames' => app(\App\Services\CategoryService::class)->query()->whereIn('id', (array) $this->room->setting('category_ids', []))->get()->pluck('name')->all(),
        ];
    }

    /**
     * Everything the template shows, already decided: it only renders.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $data = $this->viewData;
        $state = $data['state'];
        $reveal = $state['reveal'] ?? null;
        $me = (int) auth()->id();
        $language = (string) $this->room->setting('language');
        $picked = $state['my_choice_id'] ?? null;
        $inQuestion = $state !== null && $state['phase'] === 'question';

        return $data + [
            'finished' => $this->room->status === RoomStatus::Cancelled || ($state !== null && $state['phase'] === 'completed'),
            'dir' => (string) config('app.available_locales.'.$language.'.dir', 'ltr'),
            'languageLabel' => (string) config('app.available_locales.'.$language.'.label', $language),
            'pollSeconds' => max(1, (int) config('fakkroun.gameplay.poll_seconds', 1)),
            'invitationUrl' => $this->room->invitationUrl(),
            // Waiting room only: the invitation link as a scannable code.
            'qr' => $state === null ? app(RoomService::class)->invitationQr($this->room) : null,
            'whatsappUrl' => 'https://wa.me/?text='.rawurlencode(__('play.room.share_text', ['code' => $this->room->code]).' '.$this->room->invitationUrl()),
            'lobbyPlayers' => $data['players']->values()->map(fn ($player, int $index): array => [
                'id' => $player->id,
                'name' => $player->name,
                'initial' => mb_substr((string) $player->name, 0, 1),
                'tone' => $index % 5,
                'isHost' => $player->id === $this->room->host_id,
            ])->all(),
            'reveal' => $reveal,
            'answeredCount' => count($state['answered_user_ids'] ?? []),
            'timer' => $inQuestion ? $this->timer($state) : null,
            'choices' => $state !== null && isset($state['question']) ? $this->choices($state, $reveal, $picked) : [],
            'verdict' => $reveal ? $this->verdict($reveal['mine']) : null,
            'scoreboard' => collect($state['leaderboard'] ?? [])->values()->map(fn (array $row, int $index): array => [
                'rank' => $index + 1,
                'name' => $row['user_id'] === $me ? __('play.game2.you') : $row['name'],
                'initial' => mb_substr((string) ($row['name'] ?? '?'), 0, 1),
                'tone' => $row['user_id'] === $me ? 'me' : $index % 5,
                'isMe' => $row['user_id'] === $me,
                'left' => $row['left'],
                'status' => $inQuestion
                    ? (in_array($row['user_id'], $state['answered_user_ids'], true) ? __('play.game2.answered') : __('play.game2.thinking'))
                    : null,
                'score' => number_format($row['score']),
            ])->all(),
        ];
    }

    /**
     * Countdown ring values. The server sends what is left, never a clock
     * time, so a wrong clock on the phone cannot shift the countdown.
     *
     * @param  array<string, mixed>  $state
     * @return array{seconds: int, remaining_ms: int, duration_ms: int, fraction: float, dashoffset: float}
     */
    private function timer(array $state): array
    {
        $duration = max(1, (int) $state['duration_ms']);
        $fraction = round(min(1, max(0, $state['remaining_ms'] / $duration)), 3);

        return [
            'seconds' => (int) ceil($state['remaining_ms'] / 1000),
            'remaining_ms' => (int) $state['remaining_ms'],
            'duration_ms' => $duration,
            'fraction' => $fraction,
            'dashoffset' => round(226.2 * (1 - $fraction), 1),
        ];
    }

    /**
     * Answer buttons with their display state (see .fk-answer in fakkroun.css).
     *
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>|null  $reveal
     * @return array<int, array<string, mixed>>
     */
    private function choices(array $state, ?array $reveal, ?int $picked): array
    {
        return array_map(function (array $choice) use ($state, $reveal, $picked): array {
            $isCorrect = $reveal !== null && $reveal['correct_choice_id'] === $choice['id'];
            $isMine = $picked === $choice['id'];

            return [
                'id' => $choice['id'],
                'letter' => $choice['letter'],
                'text' => $choice['text'],
                'state' => match (true) {
                    $isCorrect => 'correct',
                    $reveal !== null && $isMine => 'wrong',
                    $isMine => 'picked',
                    $reveal !== null || $picked !== null => 'dim',
                    default => 'open',
                },
                'tag' => $isCorrect ? __('play.game2.correct_tag') : ($isMine ? __('play.game2.your_answer') : null),
                'disabled' => $state['phase'] !== 'question' || $picked !== null,
                'count' => $reveal !== null ? (int) ($reveal['distribution'][$choice['id']] ?? 0) : null,
                'excluded' => $reveal !== null && in_array($choice['id'], $reveal['excluded_choice_ids'], true),
            ];
        }, $state['question']['choices']);
    }

    /**
     * @param  array<string, mixed>|null  $mine
     * @return array{text: string, tone: string, points: int}
     */
    private function verdict(?array $mine): array
    {
        [$text, $tone] = match (true) {
            $mine === null => [__('play.game2.verdict_none'), 'none'],
            $mine['excluded'] => [__('play.game2.verdict_excluded'), 'excluded'],
            $mine['correct'] => [__('play.game2.verdict_right'), 'right'],
            default => [__('play.game2.verdict_wrong'), 'wrong'],
        };

        return ['text' => $text, 'tone' => $tone, 'points' => (int) ($mine['points'] ?? 0)];
    }
};
?>

<div class="min-h-[calc(100vh-80px)] bg-sea-deep text-sand" @unless ($finished) wire:poll.{{ $pollSeconds }}s="refresh" @endunless>
    <div class="mx-auto flex max-w-[1320px] flex-col gap-6 px-4 pt-6 pb-16 sm:px-6 sm:pt-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-col">
                <b class="text-xl">{{ $room->name }}</b>
                <span class="text-sm text-sea-mist">{{ __('play.game2.code_label') }}: <b class="tracking-[0.2em] text-sun">{{ $room->code }}</b> · {{ __('play.room.players', ['count' => $players->count(), 'max' => $room->max_players]) }}</span>
            </div>
            @unless ($finished)
                <button type="button" wire:click="leave" wire:confirm="{{ __('play.room.leave_confirm') }}" class="fk-btn border-2 border-sea-line text-sand">{{ __('play.room.leave') }}</button>
            @endunless
        </div>

        @if ($room->status === RoomStatus::Cancelled)
            <p class="rounded-3xl bg-sea-deep-2 p-8 text-lg">{{ __('play.room.cancelled') }}</p>
        @elseif ($state === null)
            {{-- Waiting room --}}
            <div class="flex flex-wrap items-start gap-6">
                <section class="flex min-w-0 flex-[999_1_620px] flex-col items-start gap-6 rounded-[28px] bg-sea-deep-2 p-[clamp(20px,3vw,36px)]">
                    <h1 class="fk-h2 m-0 text-[clamp(30px,8vw,40px)]">{{ __('play.game2.waiting_title') }}</h1>
                    <div class="flex w-full flex-wrap items-center justify-between gap-6">
                        <div class="flex flex-col gap-1">
                            <span class="text-sm font-semibold tracking-[.08em] text-sun uppercase">{{ __('play.game2.code_label') }}</span>
                            <span class="font-display text-[clamp(40px,13vw,120px)] leading-none font-extrabold tracking-[0.12em]">{{ $room->code }}</span>
                        </div>
                        @if ($qr)
                            {{-- wire:ignore: the code never changes, no need to re-send it on every poll. --}}
                            <figure class="m-0 flex flex-col items-center gap-2" wire:ignore>
                                <svg viewBox="0 0 {{ $qr['size'] }} {{ $qr['size'] }}" role="img" aria-label="{{ __('play.room.qr_label') }}" shape-rendering="crispEdges" class="size-[clamp(168px,22vw,232px)] rounded-2xl bg-white text-black">
                                    <path d="{{ $qr['path'] }}" fill="currentColor" />
                                </svg>
                                <figcaption class="max-w-[232px] text-center text-sm text-sea-mist">{{ __('play.room.qr_hint') }}</figcaption>
                            </figure>
                        @endif
                    </div>
                    <div class="flex w-full flex-wrap gap-2" x-data="{ copied: false, url: @js($invitationUrl) }">
                        <input type="text" readonly value="{{ $invitationUrl }}" aria-label="{{ __('play.room.invite') }}" class="min-h-11 min-w-0 flex-1 rounded-xl border-2 border-sea-line bg-sea-deep px-3 text-sea-mist">
                        <button type="button" class="fk-btn bg-sun text-ink" x-on:click="navigator.clipboard?.writeText(url); copied = true; setTimeout(() => copied = false, 2000)">
                            <span x-show="! copied">{{ __('play.room.copy') }}</span><span x-show="copied" x-cloak>{{ __('play.room.copied') }}</span>
                        </button>
                        <button type="button" class="fk-btn border-2 border-sea-line text-sand" x-show="navigator.share" x-cloak x-on:click="navigator.share({ title: @js($room->name), text: @js(__('play.room.share_text', ['code' => $room->code])), url })">{{ __('play.room.share') }}</button>
                        <a class="fk-btn border-2 border-sea-line text-sand no-underline" href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer">WhatsApp</a>
                    </div>
                    <dl class="m-0 grid w-full gap-3 text-[15px] [grid-template-columns:repeat(auto-fit,minmax(180px,1fr))]">
                        <div><dt class="text-sea-mist">{{ __('play.create.categories') }}</dt><dd class="m-0 font-semibold">{{ implode(', ', $categoryNames) }}</dd></div>
                        <div><dt class="text-sea-mist">{{ __('play.lobby.questions') }}</dt><dd class="m-0 font-semibold">{{ $room->setting('question_count') }} · {{ __('play.room.seconds_each', ['seconds' => $room->setting('seconds_per_question')]) }}</dd></div>
                        <div><dt class="text-sea-mist">{{ __('play.create.language') }}</dt><dd class="m-0 font-semibold">{{ $languageLabel }}</dd></div>
                        <div><dt class="text-sea-mist">{{ __('play.rule.title') }}</dt><dd class="m-0 font-semibold">{{ $room->setting('duplicate_rule') ? __('play.rule.on') : __('play.rule.off') }}</dd></div>
                    </dl>
                    @if ($room->setting('duplicate_rule'))
                        <p class="m-0 rounded-2xl bg-sea-deep p-4 text-[15px] text-sea-mist">{{ __('play.rule.explanation') }}</p>
                    @endif
                    @if ($isHost)
                        <button type="button" wire:click="start" class="fk-btn fk-btn-primary min-h-14 rounded-2xl px-7 text-lg" @disabled(! $ready)>{{ __('play.room.start') }}</button>
                        @unless ($ready)<p class="m-0 text-sm text-sea-mist">{{ __('play.room.waiting_for_players', ['min' => $minPlayers]) }}</p>@endunless
                    @else
                        <p class="m-0 text-sea-mist">{{ __('play.room.waiting_for_host') }}</p>
                    @endif
                    @if ($ad && $ad->creative)
                        <div class="w-full">@include('partials.ads.card', ['ad' => $ad, 'dark' => false])</div>
                    @endif
                </section>
                <aside aria-labelledby="players-h" class="flex min-w-0 flex-[1_1_300px] flex-col gap-3.5 rounded-3xl bg-sea-deep-2 p-5">
                    <h2 id="players-h" class="m-0 text-lg font-semibold">{{ __('play.room.joined') }}</h2>
                    <ul class="m-0 flex list-none flex-col gap-2 p-0">
                        @foreach ($lobbyPlayers as $player)
                            <li class="flex items-center gap-2.5 rounded-xl bg-sea-deep px-3 py-2" wire:key="p-{{ $player['id'] }}">
                                <span class="fk-avatar" data-tone="{{ $player['tone'] }}">{{ $player['initial'] }}</span>
                                <span class="min-w-0 flex-1 truncate font-medium">{{ $player['name'] }}</span>
                                @if ($player['isHost'])<span class="rounded-full bg-sun px-2 py-0.5 text-xs font-semibold text-ink">{{ __('play.room.host') }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                </aside>
            </div>
        @elseif ($state['phase'] === 'completed')
            <div class="flex flex-col items-start gap-4 rounded-[28px] bg-sea-deep-2 p-8">
                <h1 class="fk-h2 m-0 text-[40px]">{{ __('play.results2.title') }}</h1>
                <a href="{{ route('rooms.results', $room) }}" wire:navigate class="fk-btn fk-btn-primary min-h-14 rounded-2xl px-7 text-lg">{{ __('play.game2.see_results') }}</a>
            </div>
        @else
            {{-- Question / reveal --}}
            <div class="flex flex-wrap items-start gap-6" wire:key="q-{{ $state['position'] }}-{{ $state['phase'] }}">
                <main class="flex min-w-0 flex-[999_1_620px] flex-col gap-6">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="inline-flex items-center gap-2 rounded-full bg-tint-sea px-3.5 py-2 text-[15px] font-semibold text-[#0B5A55]">{{ $state['category'] }} · {{ __('questions.difficulty.'.$state['question']['difficulty']) }}</span>
                            @if ($state['sponsor'])<span class="rounded-full bg-sun px-3 py-1.5 text-sm font-semibold text-ink">{{ __('advertising.sponsored_by', ['name' => $state['sponsor']]) }}</span>@endif
                            @if ($state['round_sponsor'] ?? null)<span class="rounded-full bg-sun px-3 py-1.5 text-sm font-semibold text-ink">{{ __('advertising.round_presented_by', ['name' => $state['round_sponsor']]) }}</span>@endif
                            @if ($state['tie_breaker'])<span class="rounded-full bg-sun px-3 py-1.5 text-sm font-semibold text-ink">{{ __('play.game2.tie_breaker') }}</span>@endif
                            <span class="text-sea-mist">{{ __('play.game.question_of', ['n' => $state['position'], 'total' => $state['total']]) }}</span>
                        </div>
                        @if ($state['phase'] === 'question')
                            <div class="relative h-[84px] w-[84px] flex-none max-sm:h-16 max-sm:w-16"
                                 x-data="{ end: Date.now() + {{ $timer['remaining_ms'] }}, total: {{ $timer['duration_ms'] }}, left: {{ $timer['seconds'] }}, frac: {{ $timer['fraction'] }} }"
                                 x-init="setInterval(() => { const ms = Math.max(0, end - Date.now()); left = Math.ceil(ms / 1000); frac = ms / total }, 200)">
                                <svg viewBox="0 0 84 84" class="absolute inset-0 -rotate-90" aria-hidden="true">
                                    <circle cx="42" cy="42" r="36" fill="none" stroke="#1E525B" stroke-width="8"/>
                                    <circle cx="42" cy="42" r="36" fill="none" stroke="#F2A33A" stroke-width="8" stroke-linecap="round" stroke-dasharray="226.2" :stroke-dashoffset="226.2 * (1 - frac)" stroke-dashoffset="{{ $timer['dashoffset'] }}"/>
                                </svg>
                                <span role="timer" :aria-label="@js(__('play.game2.timer', ['seconds' => '__S__'])).replace('__S__', left)" aria-label="{{ __('play.game2.timer', ['seconds' => $timer['seconds']]) }}" class="absolute inset-0 flex items-center justify-center font-display text-[30px] font-extrabold max-sm:text-2xl" x-text="left">{{ $timer['seconds'] }}</span>
                            </div>
                        @endif
                    </div>

                    @if (($state['round_intro'] ?? false) && $state['phase'] === 'question')
                        {{-- Sponsored round intro (story 16 S12): labelled, never covering answers or the timer. --}}
                        <p role="note" class="m-0 rounded-2xl border-2 border-sun bg-sea-deep-2 px-4 py-2 text-[15px] font-semibold text-sun">{{ __('advertising.round_intro', ['name' => $state['round_sponsor']]) }}</p>
                    @endif
                    @if ($state['tie_breaker'] && $state['phase'] === 'question')
                        <p role="note" class="m-0 rounded-2xl border-2 border-sun bg-sea-deep-2 px-4 py-2 text-[15px] font-semibold text-sun">{{ __('play.game2.tie_breaker_intro') }}</p>
                    @endif
                    <h1 class="m-0 font-display text-[clamp(26px,3.4vw,44px)] leading-[1.15] font-bold text-balance" dir="{{ $dir }}">{{ $state['question']['body'] }}</h1>

                    {{-- Keyboard play: 1–4, A–D or أ ب ج د pick the answer in display order. --}}
                    <div class="grid gap-3.5 [grid-template-columns:repeat(auto-fit,minmax(min(300px,100%),1fr))]" dir="{{ $dir }}"
                         x-data="{ keys: { '1': 0, '2': 1, '3': 2, '4': 3, a: 0, b: 1, c: 2, d: 3, 'أ': 0, 'ب': 1, 'ج': 2, 'د': 3 } }"
                         x-on:keydown.window="
                            if ($event.ctrlKey || $event.metaKey || $event.altKey || $event.target.closest('input, textarea, select, [contenteditable]')) return;
                            const index = keys[$event.key.toLowerCase()];
                            const button = index === undefined ? null : $el.querySelectorAll('button[data-answer]')[index];
                            if (button && ! button.disabled) { $event.preventDefault(); button.click(); }
                         ">
                        @foreach ($choices as $choice)
                            <button type="button" data-answer data-state="{{ $choice['state'] }}" wire:click="answer({{ $choice['id'] }})" aria-keyshortcuts="{{ $loop->iteration }}" @disabled($choice['disabled']) class="fk-answer">
                                <span class="fk-answer-letter">{{ $choice['letter'] }}</span>
                                {{-- The status sits under the text, so long answers keep the full width on phones. --}}
                                <span class="flex min-w-0 flex-1 flex-col gap-0.5 text-start">
                                    <span class="break-words">{{ $choice['text'] }}</span>
                                    @if ($choice['tag'])<span class="text-sm font-semibold opacity-90">{{ $choice['tag'] }}</span>@endif
                                </span>
                                @if ($choice['count'] !== null)<span class="flex-none text-xs opacity-80">{{ $choice['count'] }}@if ($choice['excluded']) · {{ __('play.game.excluded_short') }}@endif</span>@endif
                            </button>
                        @endforeach
                    </div>

                    @if ($state['phase'] === 'question' && $state['my_choice_id'] === null)
                        <p class="m-0 text-sm text-sea-mist max-[720px]:hidden">{{ __('play.game2.keyboard_hint') }}</p>
                    @endif

                    @if ($reveal)
                        <section aria-live="polite" class="flex flex-wrap items-center gap-x-8 gap-y-5 rounded-3xl bg-paper px-5 py-5 text-ink sm:px-7 sm:py-6">
                            <div class="flex min-w-0 flex-[1_1_420px] flex-col gap-2">
                                <b class="fk-verdict" data-tone="{{ $verdict['tone'] }}">{{ $verdict['text'] }}</b>
                                @if ($reveal['explanation'])<p class="m-0 leading-relaxed text-ink-muted" dir="{{ $dir }}">{{ $reveal['explanation'] }}</p>@endif
                                <span class="text-[13px] text-ink-soft">
                                    @if ($reveal['reference_url'])<a href="{{ $reveal['reference_url'] }}" target="_blank" rel="noopener noreferrer nofollow" class="fk-link">{{ __('play.game.source') }}</a>@else{{ __('play.game2.source_missing', ['source' => config('fakkroun.brand.source_placeholder')]) }}@endif
                                    @if ($state['source'] === 'community' && $state['author']) · {{ __('play.game2.question_by', ['author' => $state['author']]) }}@endif
                                </span>
                            </div>
                            <div class="flex flex-col items-end gap-2.5">
                                <span class="font-display text-[34px] font-extrabold text-sea">{{ __('play.game2.points', ['points' => $verdict['points']]) }}</span>
                                <span class="text-sm text-ink-soft">{{ __('play.game.next_soon') }}</span>
                            </div>
                        </section>

                        @if ($ad && $ad->creative)
                            {{-- Ad below the results, never over answers or the timer (spec §17.2). --}}
                            @include('partials.ads.card', ['ad' => $ad, 'dark' => true])
                        @endif
                    @else
                        <p class="m-0 text-[15px] text-sea-mist">{{ __('play.game2.hint') }}@if ($state['my_choice_id']) · <b class="text-sun">{{ __('play.game.locked_in') }}</b>@endif</p>
                    @endif
                </main>

                <aside aria-labelledby="players-h" class="flex min-w-0 flex-[1_1_300px] flex-col gap-3.5 rounded-3xl bg-sea-deep-2 p-5">
                    <div class="flex items-center justify-between">
                        <h2 id="players-h" class="m-0 text-lg font-semibold">{{ __('play.game2.players') }}</h2>
                        <span class="text-sm text-sea-mist">{{ __('play.game2.answered_of', ['count' => $answeredCount, 'total' => $state['player_count']]) }}</span>
                    </div>
                    {{-- Phones: the compact line above is enough; the full list shows from md up (Mobile.dc.html). --}}
                    <ol class="m-0 hidden list-none flex-col gap-2 p-0 md:flex">
                        @foreach ($scoreboard as $row)
                            <li @class(['flex items-center gap-2.5 rounded-xl px-2.5 py-2', 'bg-sea-line' => $row['isMe']])>
                                <span class="w-[22px] text-sm font-semibold text-sea-mist">{{ $row['rank'] }}</span>
                                <span class="fk-avatar text-sm" data-tone="{{ $row['tone'] }}">{{ $row['initial'] }}</span>
                                <span class="min-w-0 flex-1 truncate font-medium">{{ $row['name'] }}@if ($row['left']) <span class="text-sea-mist">({{ __('play.game.left') }})</span>@endif</span>
                                @if ($row['status'])<span class="text-xs text-sea-mist">{{ $row['status'] }}</span>@endif
                                <b class="min-w-[52px] text-end tabular-nums">{{ $row['score'] }}</b>
                            </li>
                        @endforeach
                    </ol>
                </aside>
            </div>
        @endif
    </div>
</div>
