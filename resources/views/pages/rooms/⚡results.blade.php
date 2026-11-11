<?php

use App\Domain\Advertising\AdService;
use App\Domain\Gameplay\GameEngine;
use App\Domain\Gameplay\Models\Room;
use App\Domain\Gameplay\RoomService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Results (fakkroun-design/design/Results.dc.html): podium, standings and
 * "What we learned". Visible to the room's players once the game is over.
 */
new #[Layout('components.layouts.fakkroun')] #[Title('Results')] class extends Component
{
    public Room $room;

    public function mount(Room $room): void
    {
        $this->authorize('play games');
        $this->room = $room;

        $member = $room->memberships()->where('user_id', auth()->id())->exists();

        if (! $member) {
            throw new AuthorizationException;
        }

        if (! $room->game?->isCompleted()) {
            $this->redirect(route('rooms.show', $room));
        }
    }

    #[Computed]
    public function results(): array
    {
        return $this->room->game ? (app(GameEngine::class)->state($this->room->game, auth()->user())['results'] ?? []) : [];
    }

    #[Computed]
    public function recap(): array
    {
        return $this->room->game ? app(GameEngine::class)->recap($this->room->game, auth()->user()) : [];
    }

    /**
     * Results-page ad (story 16 S3): once per player and room, below
     * "What we learned". Nothing is billed until confirmImpression().
     */
    #[Computed]
    public function ad()
    {
        return $this->room->game?->isCompleted()
            ? app(AdService::class)->offerPostGame($this->room, auth()->user())?->load('creative')
            : null;
    }

    public function confirmImpression(string $token, AdService $ads): void
    {
        $ads->confirm($token, auth()->user());
    }

    public function playAgain(RoomService $rooms): void
    {
        if (auth()->user()->isGuest()) {
            // Hosting needs an account.
            $this->redirect(route('register'));

            return;
        }

        try {
            $room = $rooms->replay($this->room, auth()->user());
        } catch (\DomainException|\Illuminate\Validation\ValidationException $e) {
            $this->redirect(route('rooms.create'), navigate: true);

            return;
        }

        $this->redirect(route('rooms.show', $room), navigate: true);
    }

    /**
     * View-ready data: podium in 2-1-3 order, standings and the recap, with
     * every label and number already formatted.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $me = (int) auth()->id();
        $results = collect($this->results)->values();
        $recap = collect($this->recap);
        $medals = [1 => '🥇', 2 => '🥈', 3 => '🥉'];

        return [
            'podium' => collect([1, 0, 2])
                ->filter(fn (int $slot): bool => $results->has($slot))
                ->map(fn (int $slot): array => [
                    'place' => $slot + 1,
                    'medal' => $medals[$slot + 1],
                    'name' => $results[$slot]['name'],
                    'score' => number_format($results[$slot]['score']),
                    'rank' => $results[$slot]['rank'],
                ])->values()->all(),
            'standings' => $results->map(fn (array $row): array => [
                'rank' => $row['rank'],
                'name' => $row['name'],
                'correct' => $row['correct'],
                'score' => number_format($row['score']),
                'isMe' => $row['user_id'] === $me,
            ])->all(),
            'recap' => $recap->map(function (array $item): array {
                $ok = $item['correct'] && ! $item['excluded'];

                return $item + [
                    'ok' => $ok,
                    'mark' => $ok ? '✓' : '✗',
                    'label' => match (true) {
                        ! $item['answered'] => __('play.results2.not_answered'),
                        $item['excluded'] => __('play.results2.excluded'),
                        $ok => __('play.results2.you_got_it'),
                        default => __('play.results2.you_missed'),
                    },
                ];
            })->all(),
            'right' => $recap->where('correct', true)->where('excluded', false)->count(),
            'dir' => (string) config('app.available_locales.'.$this->room->setting('language').'.dir', 'ltr'),
            'isGuest' => auth()->user()->isGuest(),
            'ad' => $this->ad,
        ];
    }
};
?>

<div class="fk-container flex flex-col gap-8 pt-10 pb-20">
    <h1 class="fk-h2 m-0 text-center text-[clamp(36px,9vw,52px)] leading-none">{{ __('play.results.title') }}</h1>

    @if ($podium !== [])
        <section aria-label="{{ __('play.results2.podium') }}" class="mx-auto flex w-full max-w-[720px] items-end justify-center gap-3">
            @foreach ($podium as $step)
                <div class="flex min-w-0 flex-1 flex-col items-center gap-2">
                    <span class="text-3xl" aria-hidden="true">{{ $step['medal'] }}</span>
                    <b class="max-w-full truncate text-center text-lg">{{ $step['name'] }}</b>
                    <span class="font-display text-2xl font-extrabold text-sea">{{ $step['score'] }}</span>
                    <div class="fk-podium-step" data-place="{{ $step['place'] }}">{{ $step['rank'] }}</div>
                </div>
            @endforeach
        </section>
    @endif

    <div class="flex flex-wrap justify-center gap-3" x-data="{ copied: false }">
        @if ($isGuest)
            <p class="m-0 basis-full text-center text-ink-muted">{{ __('play.join.guest_cta') }}</p>
            <a href="{{ route('register') }}" class="fk-btn fk-btn-primary min-h-14 rounded-2xl px-7 text-[17px]">{{ __('play.join.guest_cta_button') }}</a>
        @else
            <button type="button" wire:click="playAgain" class="fk-btn fk-btn-primary min-h-14 rounded-2xl px-7 text-[17px]">{{ __('play.results2.play_again') }}</button>
            <a href="{{ route('rooms.create') }}" wire:navigate class="fk-btn fk-btn-outline min-h-14 rounded-2xl">{{ __('play.results2.new_room') }}</a>
        @endif
        <button type="button" class="fk-btn fk-btn-outline min-h-14 rounded-2xl"
                x-on:click="navigator.share ? navigator.share({ title: @js($room->name), text: @js(__('play.results2.share_text')), url: location.href }) : (navigator.clipboard?.writeText(location.href), copied = true)">
            <span x-show="! copied">{{ __('play.results2.share') }}</span><span x-show="copied" x-cloak>{{ __('play.results2.copied') }}</span>
        </button>
    </div>

    <div class="flex flex-wrap items-start gap-6">
        <section aria-labelledby="lb-h" class="flex min-w-0 flex-[1_1_360px] flex-col gap-3.5 rounded-[28px] border border-sand-300 bg-paper p-6">
            <h2 id="lb-h" class="m-0 text-[22px] font-semibold">{{ __('play.results2.title') }}</h2>
            {{-- A list, not a table: each row is one player and fits a phone without scrolling. --}}
            <div class="fk-standings-head" aria-hidden="true">
                <span>#</span><span>{{ __('play.results.player') }}</span><span>{{ __('play.results.correct') }}</span><span>{{ __('play.results.score') }}</span>
            </div>
            <ol class="m-0 list-none p-0">
                @foreach ($standings as $row)
                    <li @class(['fk-standings-row', 'bg-tint-green' => $row['isMe']])>
                        <span class="font-semibold text-ink-soft"><span class="sr-only">{{ __('play.results.rank') }} </span>{{ $row['rank'] }}</span>
                        <span class="min-w-0 truncate font-medium">{{ $row['name'] }}</span>
                        <span class="text-end tabular-nums"><span class="sr-only">{{ __('play.results.correct') }}: </span>{{ $row['correct'] }}</span>
                        <span class="text-end font-semibold tabular-nums"><span class="sr-only">{{ __('play.results.score') }}: </span>{{ $row['score'] }}</span>
                    </li>
                @endforeach
            </ol>
        </section>

        <section aria-labelledby="recap-h" class="flex min-w-0 flex-[999_1_560px] flex-col gap-3.5">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 id="recap-h" class="fk-h2 m-0 text-[34px]">{{ __('play.results2.recap') }}</h2>
                <span class="text-ink-soft">{{ __('play.results2.got_right', ['right' => $right, 'total' => count($recap)]) }}</span>
            </div>
            @foreach ($recap as $item)
                <article class="flex items-start gap-3 rounded-[22px] border border-sand-300 bg-paper px-4 py-4 sm:gap-4 sm:px-[22px] sm:py-5">
                    <span @class(['flex h-10 w-10 flex-none items-center justify-center rounded-full text-lg font-bold text-white', 'bg-correct' => $item['ok'], 'bg-wrong' => ! $item['ok']]) role="img" aria-label="{{ $item['label'] }}">{{ $item['mark'] }}</span>
                    <div class="flex min-w-0 flex-1 flex-col gap-1.5" dir="{{ $dir }}">
                        <span class="text-[13px] font-semibold text-[#0B5A55]">{{ __('play.results2.question_n', ['n' => $item['position']]) }} · {{ $item['label'] }}</span>
                        <b class="text-[17px] leading-snug">{{ $item['body'] }}</b>
                        <span class="leading-normal text-ink-muted">{{ __('play.results2.answer', ['answer' => '']) }}<b class="text-ink">{{ $item['correct_text'] }}</b>@if ($item['explanation']) — {{ $item['explanation'] }}@endif</span>
                        @if ($item['reference_url'])<a href="{{ $item['reference_url'] }}" target="_blank" rel="noopener noreferrer nofollow" class="fk-link text-[13px]">{{ __('play.game.source') }}</a>@endif
                    </div>
                </article>
            @endforeach

            @if ($ad && $ad->creative)
                @include('partials.ads.card', ['ad' => $ad, 'dark' => false])
            @endif
        </section>
    </div>
</div>
