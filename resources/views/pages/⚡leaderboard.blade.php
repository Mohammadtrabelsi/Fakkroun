<?php

use App\Domain\Gameplay\LeaderboardService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Global leaderboard, weekly or all-time (design brief Phase 8). */
new #[Layout('components.layouts.fakkroun')] #[Title('Leaderboard')] class extends Component
{
    /** week | all */
    #[Url]
    public string $period = 'week';

    #[Computed]
    public function rows()
    {
        return app(LeaderboardService::class)->top($this->period === 'all' ? 'all' : 'week');
    }

    /**
     * Rows ready to print: rank, formatted score, profile link for signed-in viewers.
     *
     * @return array{rows: array<int, array<string, mixed>>}
     */
    public function with(): array
    {
        $me = auth()->id();

        return [
            'rows' => $this->rows->values()->map(fn (object $row, int $index): array => [
                'rank' => $index + 1,
                'name' => $row->name,
                'games' => $row->games,
                'wins' => $row->wins,
                'score' => number_format($row->score),
                'isMe' => $row->user_id === $me,
                'url' => $me ? route('players.show', $row->user_id) : null,
            ])->all(),
        ];
    }
};
?>

<div class="fk-container flex flex-col gap-8 pt-12 pb-20">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-col gap-2">
            <h1 class="fk-h2 m-0 text-[clamp(36px,9vw,52px)] leading-none">{{ __('play.leaderboard.title') }}</h1>
            <p class="m-0 text-lg text-ink-muted">{{ __('play.leaderboard.lead') }}</p>
        </div>
        <div role="group" aria-label="{{ __('play.leaderboard.period') }}" class="flex flex-none gap-1 rounded-[14px] bg-sand-200 p-1">
            @foreach (['week' => __('play.leaderboard.week'), 'all' => __('play.leaderboard.all')] as $value => $label)
                <button type="button" wire:click="$set('period', '{{ $value }}')" aria-pressed="{{ $period === $value ? 'true' : 'false' }}" class="fk-seg px-4 whitespace-nowrap">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <section class="rounded-[28px] border border-sand-300 bg-paper p-4 sm:p-6">
        {{-- A list of players (not a table): on phones games and wins move under the name. --}}
        <div class="fk-standings-head fk-standings--wide" aria-hidden="true">
            <span>#</span><span>{{ __('play.results.player') }}</span><span>{{ __('play.leaderboard.games') }}</span><span>{{ __('play.leaderboard.wins') }}</span><span>{{ __('play.results.score') }}</span>
        </div>
        <ol class="m-0 list-none p-0">
            @forelse ($rows as $row)
                <li @class(['fk-standings-row fk-standings--wide', 'bg-tint-green' => $row['isMe']])>
                    <span class="font-semibold text-ink-soft"><span class="sr-only">{{ __('play.results.rank') }} </span>{{ $row['rank'] }}</span>
                    <span class="flex min-w-0 flex-col">
                        @if ($row['url'])<a href="{{ $row['url'] }}" class="fk-link truncate font-medium">{{ $row['name'] }}</a>@else<span class="truncate font-medium">{{ $row['name'] }}</span>@endif
                        <span class="fk-standings-sub">{{ __('play.leaderboard.games') }}: {{ $row['games'] }} · {{ __('play.leaderboard.wins') }}: {{ $row['wins'] }}</span>
                    </span>
                    <span class="fk-standings-extra text-end tabular-nums">{{ $row['games'] }}</span>
                    <span class="fk-standings-extra text-end tabular-nums">{{ $row['wins'] }}</span>
                    <span class="text-end font-semibold tabular-nums"><span class="sr-only">{{ __('play.results.score') }}: </span>{{ $row['score'] }}</span>
                </li>
            @empty
                <li class="p-4 text-ink-soft">{{ __('play.leaderboard.empty') }}</li>
            @endforelse
        </ol>
    </section>
</div>
