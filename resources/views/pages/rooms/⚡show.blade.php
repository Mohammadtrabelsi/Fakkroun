<?php

use App\Domain\Gameplay\Enums\RoomStatus;
use App\Domain\Gameplay\GameEngine;
use App\Domain\Gameplay\Models\Room;
use App\Domain\Gameplay\RoomService;
use App\Models\Category;
use App\Support\Livewire\InteractsWithToasts;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Lobby, live question, answer reveal and final results of one room.
 * MVP transport: the page polls the server every second; the server decides
 * everything (see GameEngine). Reloading the page reconnects.
 */
new #[Layout('layouts::app')] #[Title('Room')] class extends Component
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
     * Called by wire:poll: heartbeat + let the server advance the game.
     */
    public function refresh(RoomService $rooms, GameEngine $engine): void
    {
        $this->room->refresh();
        $rooms->touch($this->room, auth()->user());

        if ($game = $this->room->game) {
            $engine->tick($game);
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
        $game = $this->room->game;

        if ($game === null) {
            return;
        }

        try {
            $engine->submitAnswer($game, auth()->user(), $choiceId);
        } catch (\DomainException|AuthorizationException $e) {
            $this->toast($e->getMessage(), 'error');
        }
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

        return [
            'state' => $game ? app(GameEngine::class)->state($game, auth()->user()) : null,
            'players' => $this->room->players()->get(['users.id', 'users.name', 'users.avatar_path']),
            'isHost' => $this->room->host_id === auth()->id(),
            'ready' => $rooms->isReady($this->room),
            'minPlayers' => (int) app(\App\Services\PlatformSettings::class)->get('game.min_players'),
            'categoryNames' => Category::query()->whereIn('id', (array) $this->room->setting('category_ids', []))->get()->pluck('name')->all(),
        ];
    }
};
?>

@php(extract($this->viewData))
@php($finished = in_array($room->status, [RoomStatus::Completed, RoomStatus::Cancelled], true) && ($state === null || $state['phase'] === 'completed'))
<div class="ms-mw-720" @unless ($finished) wire:poll.{{ (int) config('fakkroun.gameplay.poll_seconds', 1) }}s="refresh" @endunless>
    <div class="ms-page-head">
        <div>
            <h2 class="ms-mb-0">{{ $room->name }}</h2>
            <p class="text-muted ms-note">{{ __('play.room.code') }}: <strong class="ms-code-chip">{{ $room->code }}</strong> · {{ __('play.room.players', ['count' => $players->count(), 'max' => $room->max_players]) }}</p>
        </div>
        @unless ($finished)
            <button type="button" wire:click="leave" wire:confirm="{{ __('play.room.leave_confirm') }}" class="btn">{{ __('play.room.leave') }}</button>
        @endunless
    </div>

    @if ($room->status === RoomStatus::Cancelled)
        <div class="card text-muted">{{ __('play.room.cancelled') }}</div>
    @elseif ($state === null)
        {{-- Lobby (spec §12.5) --}}
        <div class="card ms-stack-16">
            <div>
                <div class="ds-label">{{ __('play.room.invite') }}</div>
                <div class="ms-row-10 ms-wrap" x-data="{ copied: false, url: @js($room->invitationUrl()) }">
                    <x-text-input type="text" readonly :value="$room->invitationUrl()" aria-label="{{ __('play.room.invite') }}" />
                    <button type="button" class="btn" x-on:click="navigator.clipboard?.writeText(url); copied = true; setTimeout(() => copied = false, 2000)">
                        <span x-show="! copied">{{ __('play.room.copy') }}</span><span x-show="copied" x-cloak>{{ __('play.room.copied') }}</span>
                    </button>
                    <button type="button" class="btn" x-show="navigator.share" x-cloak x-on:click="navigator.share({ title: @js($room->name), text: @js(__('play.room.share_text', ['code' => $room->code])), url })">{{ __('play.room.share') }}</button>
                    <a class="btn" href="https://wa.me/?text={{ rawurlencode(__('play.room.share_text', ['code' => $room->code]).' '.$room->invitationUrl()) }}" target="_blank" rel="noopener noreferrer">WhatsApp</a>
                </div>
            </div>

            <dl class="ms-stack-16 ms-fs-13">
                <div><dt class="text-muted">{{ __('play.room.host') }}</dt><dd>{{ $room->host?->name }}</dd></div>
                <div><dt class="text-muted">{{ __('play.create.categories') }}</dt><dd>{{ implode(', ', $categoryNames) }}</dd></div>
                <div><dt class="text-muted">{{ __('play.create.question_count') }}</dt><dd>{{ $room->setting('question_count') }} · {{ __('play.room.seconds_each', ['seconds' => $room->setting('seconds_per_question')]) }}</dd></div>
                <div><dt class="text-muted">{{ __('play.create.language') }}</dt><dd>{{ config('app.available_locales.'.$room->setting('language').'.label') }}</dd></div>
                <div><dt class="text-muted">{{ __('play.rule.title') }}</dt><dd>{{ $room->setting('duplicate_rule') ? __('play.rule.on') : __('play.rule.off') }}</dd></div>
            </dl>

            @if ($room->setting('duplicate_rule'))
                <div class="tag tag-neutral ms-notice">{{ __('play.rule.explanation') }}</div>
            @endif

            <div>
                <div class="ds-label">{{ __('play.room.joined') }}</div>
                <ul class="ms-row-10 ms-wrap">
                    @foreach ($players as $player)
                        <li class="ms-row-10-center" wire:key="p-{{ $player->id }}"><x-user-avatar :user="$player" /> {{ $player->name }}@if ($player->id === $room->host_id) <span class="tag tag-accent">{{ __('play.room.host') }}</span>@endif</li>
                    @endforeach
                </ul>
            </div>

            @if ($isHost)
                <div>
                    <button type="button" wire:click="start" class="btn btn-primary" @disabled(! $ready)>{{ __('play.room.start') }}</button>
                    @unless ($ready)
                        <p class="text-muted ms-fs-12">{{ __('play.room.waiting_for_players', ['min' => $minPlayers]) }}</p>
                    @endunless
                </div>
            @else
                <p class="text-muted">{{ __('play.room.waiting_for_host') }}</p>
            @endif
        </div>
    @elseif ($state['phase'] === 'completed')
        {{-- Final results (spec §15.1) --}}
        <div class="card ms-stack-16">
            <h3 class="ms-fs-18">{{ __('play.results.title') }}</h3>
            @if ($winner = collect($state['results'])->firstWhere('rank', 1))
                <p class="ms-fw-500">🏆 {{ __('play.results.winner', ['name' => $winner['name'], 'score' => $winner['score']]) }}</p>
            @endif
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>#</th><th>{{ __('play.results.player') }}</th><th>{{ __('play.results.score') }}</th><th>{{ __('play.results.correct') }}</th><th>{{ __('play.results.incorrect') }}</th><th>{{ __('play.results.excluded') }}</th><th>{{ __('play.results.time') }}</th></tr></thead>
                    <tbody>
                        @foreach ($state['results'] as $row)
                            <tr @class(['ms-fw-500' => $row['user_id'] === auth()->id()])>
                                <td>{{ $row['rank'] }}</td><td>{{ $row['name'] }}</td><td>{{ $row['score'] }}</td><td>{{ $row['correct'] }}</td><td>{{ $row['incorrect'] }}</td><td>{{ $row['excluded'] }}</td><td>{{ number_format($row['total_response_ms'] / 1000, 1) }} s</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="ms-row-10">
                <a href="{{ route('rooms.create') }}" wire:navigate class="btn btn-primary">{{ __('play.results.play_again') }}</a>
                <a href="{{ route('dashboard') }}" wire:navigate class="btn">{{ __('app.dashboard') }}</a>
            </div>
        </div>
    @else
        {{-- Live question / reveal (spec §13.2) --}}
        @php($dir = config('app.available_locales.'.$room->setting('language').'.dir', 'ltr'))
        <div class="card ms-stack-16" wire:key="q-{{ $state['position'] }}-{{ $state['phase'] }}">
            <div class="ms-row-between">
                <span class="text-muted">{{ __('play.game.question_of', ['n' => $state['position'], 'total' => $state['total']]) }}</span>
                @if ($state['phase'] === 'question')
                    <span class="play-timer" role="timer" aria-live="off"
                          x-data="{ end: Date.now() + {{ $state['remaining_ms'] }}, left: {{ (int) ceil($state['remaining_ms'] / 1000) }} }"
                          x-init="setInterval(() => left = Math.max(0, Math.ceil((end - Date.now()) / 1000)), 200)"
                          x-text="left">{{ (int) ceil($state['remaining_ms'] / 1000) }}</span>
                @else
                    <span class="tag tag-neutral">{{ __('play.game.next_soon') }}</span>
                @endif
            </div>

            <div class="play-question" dir="{{ $dir }}">{{ $state['question']['body'] }}</div>

            <div class="play-answers" dir="{{ $dir }}">
                @foreach ($state['question']['choices'] as $choice)
                    @php($reveal = $state['reveal'])
                    @php($isCorrect = $reveal && $reveal['correct_choice_id'] === $choice['id'])
                    @php($isMine = $state['my_choice_id'] === $choice['id'])
                    <button type="button"
                            wire:click="answer({{ $choice['id'] }})"
                            @disabled($state['phase'] !== 'question' || $state['my_choice_id'] !== null)
                            @class(['play-answer', 'is-mine' => $isMine, 'is-correct' => $isCorrect, 'is-wrong' => $reveal && $isMine && ! $isCorrect])>
                        <b>{{ $choice['letter'] }}</b>
                        <span>{{ $choice['text'] }}</span>
                        @if ($reveal)
                            <small class="text-muted">{{ $reveal['distribution'][$choice['id']] ?? 0 }}@if (in_array($choice['id'], $reveal['excluded_choice_ids'], true)) · {{ __('play.game.excluded_short') }}@endif</small>
                        @endif
                    </button>
                @endforeach
            </div>

            @if ($state['phase'] === 'question')
                <p class="text-muted ms-fs-12">{{ __('play.game.answered', ['count' => $state['answered_count'], 'total' => $state['player_count']]) }}@if ($state['my_choice_id']) · {{ __('play.game.locked_in') }}@endif</p>
            @elseif ($state['reveal'])
                @php($mine = $state['reveal']['mine'])
                <div class="tag {{ $mine && $mine['correct'] && ! $mine['excluded'] ? 'tag-accent' : 'tag-outline' }} ms-notice">
                    @if (! $mine)
                        {{ __('play.game.no_answer') }}
                    @elseif ($mine['excluded'])
                        {{ __('play.game.excluded', ['points' => 0]) }}
                    @elseif ($mine['correct'])
                        {{ __('play.game.correct', ['points' => $mine['points'], 'seconds' => number_format($mine['response_ms'] / 1000, 1)]) }}
                    @else
                        {{ __('play.game.wrong') }}
                    @endif
                </div>
                @if ($state['reveal']['explanation'])
                    <p class="text-muted" dir="{{ $dir }}">{{ $state['reveal']['explanation'] }}</p>
                @endif
                @if ($state['reveal']['reference_url'])
                    <a href="{{ $state['reveal']['reference_url'] }}" target="_blank" rel="noopener noreferrer nofollow" class="ms-fs-12">{{ __('play.game.source') }}</a>
                @endif
            @endif
        </div>

        <div class="card ms-mt16-stack-16">
            <h3 class="ms-fs-18">{{ __('play.game.leaderboard') }}</h3>
            <ol class="ms-stack-16">
                @foreach ($state['leaderboard'] as $row)
                    <li class="ms-row-between ms-fs-13" @class(['ms-fw-500' => $row['user_id'] === auth()->id()])>
                        <span>{{ $loop->iteration }}. {{ $row['name'] }}@if ($row['left']) <span class="text-muted">({{ __('play.game.left') }})</span>@endif</span>
                        <span>{{ $row['score'] }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif
</div>
