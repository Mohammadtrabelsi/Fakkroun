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
            $gq = GameQuestion::query()->where('game_id', $game->id)->where('position', $state['position'])->with('question')->first();
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
            'categoryNames' => Category::query()->whereIn('id', (array) $this->room->setting('category_ids', []))->get()->pluck('name')->all(),
        ];
    }
};
?>

@php($viewData = $this->viewData)
@php(extract($viewData))
@php($finished = $room->status === RoomStatus::Cancelled || ($state !== null && $state['phase'] === 'completed'))
@php($dir = config('app.available_locales.'.$room->setting('language').'.dir', 'ltr'))
@php($avatarColors = ['bg-sun text-ink', 'bg-leaf text-ink', 'bg-[#8FC6D0] text-ink', 'bg-[#E7B8A0] text-ink', 'bg-[#C9B6E4] text-ink'])
<div class="min-h-[calc(100vh-80px)] bg-sea-deep text-sand" @unless ($finished) wire:poll.{{ (int) config('fakkroun.gameplay.poll_seconds', 1) }}s="refresh" @endunless>
    <div class="mx-auto flex max-w-[1320px] flex-col gap-6 px-6 pt-8 pb-16">
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
                    <h1 class="fk-h2 m-0 text-[40px]">{{ __('play.game2.waiting_title') }}</h1>
                    <div class="flex flex-col gap-1">
                        <span class="text-sm font-semibold tracking-[.08em] text-sun uppercase">{{ __('play.game2.code_label') }}</span>
                        <span class="font-display text-[clamp(64px,10vw,120px)] leading-none font-extrabold tracking-[0.12em]">{{ $room->code }}</span>
                    </div>
                    <div class="flex w-full flex-wrap gap-2" x-data="{ copied: false, url: @js($room->invitationUrl()) }">
                        <input type="text" readonly value="{{ $room->invitationUrl() }}" aria-label="{{ __('play.room.invite') }}" class="min-h-11 min-w-0 flex-1 rounded-xl border-2 border-sea-line bg-sea-deep px-3 text-sea-mist">
                        <button type="button" class="fk-btn bg-sun text-ink" x-on:click="navigator.clipboard?.writeText(url); copied = true; setTimeout(() => copied = false, 2000)">
                            <span x-show="! copied">{{ __('play.room.copy') }}</span><span x-show="copied" x-cloak>{{ __('play.room.copied') }}</span>
                        </button>
                        <button type="button" class="fk-btn border-2 border-sea-line text-sand" x-show="navigator.share" x-cloak x-on:click="navigator.share({ title: @js($room->name), text: @js(__('play.room.share_text', ['code' => $room->code])), url })">{{ __('play.room.share') }}</button>
                        <a class="fk-btn border-2 border-sea-line text-sand no-underline" href="https://wa.me/?text={{ rawurlencode(__('play.room.share_text', ['code' => $room->code]).' '.$room->invitationUrl()) }}" target="_blank" rel="noopener noreferrer">WhatsApp</a>
                    </div>
                    <dl class="m-0 grid w-full gap-3 text-[15px] [grid-template-columns:repeat(auto-fit,minmax(180px,1fr))]">
                        <div><dt class="text-sea-mist">{{ __('play.create.categories') }}</dt><dd class="m-0 font-semibold">{{ implode(', ', $categoryNames) }}</dd></div>
                        <div><dt class="text-sea-mist">{{ __('play.lobby.questions') }}</dt><dd class="m-0 font-semibold">{{ $room->setting('question_count') }} · {{ __('play.room.seconds_each', ['seconds' => $room->setting('seconds_per_question')]) }}</dd></div>
                        <div><dt class="text-sea-mist">{{ __('play.create.language') }}</dt><dd class="m-0 font-semibold">{{ config('app.available_locales.'.$room->setting('language').'.label') }}</dd></div>
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
                        @foreach ($players as $index => $player)
                            <li class="flex items-center gap-2.5 rounded-xl bg-sea-deep px-3 py-2" wire:key="p-{{ $player->id }}">
                                <span class="flex h-8 w-8 items-center justify-center rounded-full font-bold {{ $avatarColors[$index % count($avatarColors)] }}">{{ mb_substr($player->name, 0, 1) }}</span>
                                <span class="flex-1 font-medium">{{ $player->name }}</span>
                                @if ($player->id === $room->host_id)<span class="rounded-full bg-sun px-2 py-0.5 text-xs font-semibold text-ink">{{ __('play.room.host') }}</span>@endif
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
            @php($reveal = $state['reveal'])
            @php($answered = count($state['answered_user_ids']))
            <div class="flex flex-wrap items-start gap-6" wire:key="q-{{ $state['position'] }}-{{ $state['phase'] }}">
                <main class="flex min-w-0 flex-[999_1_620px] flex-col gap-6">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="inline-flex items-center gap-2 rounded-full bg-tint-sea px-3.5 py-2 text-[15px] font-semibold text-[#0B5A55]">{{ $state['category'] }} · {{ __('questions.difficulty.'.$state['question']['difficulty']) }}</span>
                            @if ($state['sponsor'])<span class="rounded-full bg-sun px-3 py-1.5 text-sm font-semibold text-ink">{{ __('advertising.sponsored_by', ['name' => $state['sponsor']]) }}</span>@endif
                            @if ($state['round_sponsor'] ?? null)<span class="rounded-full bg-sun px-3 py-1.5 text-sm font-semibold text-ink">{{ __('advertising.round_presented_by', ['name' => $state['round_sponsor']]) }}</span>@endif
                            <span class="text-sea-mist">{{ __('play.game.question_of', ['n' => $state['position'], 'total' => $state['total']]) }}</span>
                        </div>
                        @if ($state['phase'] === 'question')
                            @php($seconds = (int) ceil($state['remaining_ms'] / 1000))
                            <div class="relative h-[84px] w-[84px]"
                                 x-data="{ end: Date.now() + {{ $state['remaining_ms'] }}, total: {{ max(1, $state['duration_ms']) }}, left: {{ $seconds }}, frac: {{ $state['duration_ms'] > 0 ? round($state['remaining_ms'] / $state['duration_ms'], 3) : 0 }} }"
                                 x-init="setInterval(() => { const ms = Math.max(0, end - Date.now()); left = Math.ceil(ms / 1000); frac = ms / total }, 200)">
                                <svg viewBox="0 0 84 84" class="absolute inset-0 -rotate-90" aria-hidden="true">
                                    <circle cx="42" cy="42" r="36" fill="none" stroke="#1E525B" stroke-width="8"/>
                                    <circle cx="42" cy="42" r="36" fill="none" stroke="#F2A33A" stroke-width="8" stroke-linecap="round" stroke-dasharray="226.2" :stroke-dashoffset="226.2 * (1 - frac)" stroke-dashoffset="{{ 226.2 * (1 - ($state['duration_ms'] > 0 ? $state['remaining_ms'] / $state['duration_ms'] : 0)) }}"/>
                                </svg>
                                <span role="timer" :aria-label="@js(__('play.game2.timer', ['seconds' => '__S__'])).replace('__S__', left)" aria-label="{{ __('play.game2.timer', ['seconds' => $seconds]) }}" class="absolute inset-0 flex items-center justify-center font-display text-[30px] font-extrabold" x-text="left">{{ $seconds }}</span>
                            </div>
                        @endif
                    </div>

                    @if (($state['round_intro'] ?? false) && $state['phase'] === 'question')
                        {{-- Sponsored round intro (story 16 S12): labelled, never covering answers or the timer. --}}
                        <p role="note" class="m-0 rounded-2xl border-2 border-sun bg-sea-deep-2 px-4 py-2 text-[15px] font-semibold text-sun">{{ __('advertising.round_intro', ['name' => $state['round_sponsor']]) }}</p>
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
                        @foreach ($state['question']['choices'] as $choice)
                            @php($isCorrect = $reveal && $reveal['correct_choice_id'] === $choice['id'])
                            @php($isMine = $state['my_choice_id'] === $choice['id'])
                            @php($tag = $isCorrect ? __('play.game2.correct_tag') : ($isMine ? __('play.game2.your_answer') : null))
                            @php($style = match (true) {
                                    $isCorrect => 'border-correct bg-correct text-white',
                                    $reveal && $isMine => 'border-wrong bg-wrong text-white',
                                    ! $reveal && $isMine => 'border-sun bg-sea text-paper',
                                    $reveal || $state['my_choice_id'] !== null => 'border-sea-line bg-sea-deep-2 text-sea-mist',
                                    default => 'border-paper bg-paper text-ink shadow-[0_5px_0_#A9B9B5] hover:bg-sand',
                                })
                            @php($badge = $isCorrect ? 'bg-white text-[#1F6A2C]' : ($reveal && $isMine ? 'bg-white text-wrong' : ($reveal || $state['my_choice_id'] !== null ? 'bg-sea-line text-sea-mist' : 'bg-sand-200 text-ink')))
                            <button type="button" data-answer wire:click="answer({{ $choice['id'] }})" aria-keyshortcuts="{{ $loop->iteration }}" @disabled($state['phase'] !== 'question' || $state['my_choice_id'] !== null)
                                    class="flex min-h-[76px] w-full cursor-pointer items-center gap-4 rounded-[20px] border-2 px-5 py-3.5 text-[19px] font-semibold disabled:cursor-default {{ $style }}">
                                <span class="flex h-11 w-11 flex-none items-center justify-center rounded-xl font-display text-[22px] font-extrabold {{ $badge }}">{{ $choice['letter'] }}</span>
                                <span class="flex-1 text-start">{{ $choice['text'] }}</span>
                                @if ($tag)<span class="text-sm font-semibold">{{ $tag }}</span>@endif
                                @if ($reveal)<span class="text-xs opacity-80">{{ $reveal['distribution'][$choice['id']] ?? 0 }}@if (in_array($choice['id'], $reveal['excluded_choice_ids'], true)) · {{ __('play.game.excluded_short') }}@endif</span>@endif
                            </button>
                        @endforeach
                    </div>

                    @if ($state['phase'] === 'question' && $state['my_choice_id'] === null)
                        <p class="m-0 text-sm text-sea-mist max-[720px]:hidden">{{ __('play.game2.keyboard_hint') }}</p>
                    @endif

                    @if ($reveal)
                        @php($mine = $reveal['mine'])
                        @php([$verdict, $color] = match (true) {
                                $mine === null => [__('play.game2.verdict_none'), 'text-ink'],
                                $mine['excluded'] => [__('play.game2.verdict_excluded'), 'text-sun-text'],
                                $mine['correct'] => [__('play.game2.verdict_right'), 'text-correct'],
                                default => [__('play.game2.verdict_wrong'), 'text-wrong'],
                            })
                        <section aria-live="polite" class="flex flex-wrap items-center gap-x-8 gap-y-5 rounded-3xl bg-paper px-7 py-6 text-ink">
                            <div class="flex flex-[1_1_420px] flex-col gap-2">
                                <b class="font-display text-[26px] {{ $color }}">{{ $verdict }}</b>
                                @if ($reveal['explanation'])<p class="m-0 leading-relaxed text-ink-muted" dir="{{ $dir }}">{{ $reveal['explanation'] }}</p>@endif
                                <span class="text-[13px] text-ink-soft">
                                    @if ($reveal['reference_url'])<a href="{{ $reveal['reference_url'] }}" target="_blank" rel="noopener noreferrer nofollow" class="fk-link">{{ __('play.game.source') }}</a>@else{{ __('play.game2.source_missing', ['source' => config('fakkroun.brand.source_placeholder')]) }}@endif
                                    @if ($state['source'] === 'community' && $state['author']) · {{ __('play.game2.question_by', ['author' => $state['author']]) }}@endif
                                </span>
                            </div>
                            <div class="flex flex-col items-end gap-2.5">
                                <span class="font-display text-[34px] font-extrabold text-sea">{{ __('play.game2.points', ['points' => $mine['points'] ?? 0]) }}</span>
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
                        <span class="text-sm text-sea-mist">{{ __('play.game2.answered_of', ['count' => $answered, 'total' => $state['player_count']]) }}</span>
                    </div>
                    {{-- Phones: the compact line above is enough; the full list shows from md up (Mobile.dc.html). --}}
                    <ol class="m-0 hidden list-none flex-col gap-2 p-0 md:flex">
                        @foreach ($state['leaderboard'] as $index => $row)
                            @php($me = $row['user_id'] === auth()->id())
                            <li class="flex items-center gap-2.5 rounded-xl px-2.5 py-2 {{ $me ? 'bg-sea-line' : '' }}">
                                <span class="w-[22px] text-sm font-semibold text-sea-mist">{{ $index + 1 }}</span>
                                <span class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold {{ $me ? 'bg-sand text-ink' : $avatarColors[$index % count($avatarColors)] }}">{{ mb_substr($row['name'] ?? '?', 0, 1) }}</span>
                                <span class="min-w-0 flex-1 font-medium">{{ $me ? __('play.game2.you') : $row['name'] }}@if ($row['left']) <span class="text-sea-mist">({{ __('play.game.left') }})</span>@endif</span>
                                @if ($state['phase'] === 'question')
                                    <span class="text-xs text-sea-mist">{{ in_array($row['user_id'], $state['answered_user_ids'], true) ? __('play.game2.answered') : __('play.game2.thinking') }}</span>
                                @endif
                                <b class="min-w-[52px] text-end tabular-nums">{{ number_format($row['score']) }}</b>
                            </li>
                        @endforeach
                    </ol>
                </aside>
            </div>
        @endif
    </div>
</div>
