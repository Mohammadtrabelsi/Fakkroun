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
};
?>

<div class="fk-container flex flex-col gap-8 pt-12 pb-20">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-col gap-2">
            <h1 class="fk-h2 m-0 text-[52px] leading-none">{{ __('play.leaderboard.title') }}</h1>
            <p class="m-0 text-lg text-ink-muted">{{ __('play.leaderboard.lead') }}</p>
        </div>
        <div role="group" aria-label="{{ __('play.leaderboard.period') }}" class="flex gap-1 rounded-[14px] bg-sand-200 p-1">
            @foreach (['week' => __('play.leaderboard.week'), 'all' => __('play.leaderboard.all')] as $value => $label)
                <button type="button" wire:click="$set('period', '{{ $value }}')" aria-pressed="{{ $period === $value ? 'true' : 'false' }}" class="min-h-11 rounded-[11px] px-4 font-semibold {{ $period === $value ? 'bg-paper text-ink' : 'text-ink-soft' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    <section class="rounded-[28px] border border-sand-300 bg-paper p-6">
        <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead><tr class="text-[13px] text-ink-soft"><th class="p-2 text-start">#</th><th class="p-2 text-start">{{ __('play.results.player') }}</th><th class="p-2 text-end">{{ __('play.leaderboard.games') }}</th><th class="p-2 text-end">{{ __('play.leaderboard.wins') }}</th><th class="p-2 text-end">{{ __('play.results.score') }}</th></tr></thead>
                <tbody>
                    @forelse ($this->rows as $index => $row)
                        <tr class="border-t border-sand-300 {{ $row->user_id === auth()->id() ? 'bg-tint-green' : '' }}">
                            <td class="p-2 font-semibold text-ink-soft">{{ $index + 1 }}</td>
                            <td class="p-2 font-medium">@auth<a href="{{ route('players.show', $row->user_id) }}" class="fk-link">{{ $row->name }}</a>@else{{ $row->name }}@endauth</td>
                            <td class="p-2 text-end tabular-nums">{{ $row->games }}</td>
                            <td class="p-2 text-end tabular-nums">{{ $row->wins }}</td>
                            <td class="p-2 text-end font-semibold tabular-nums">{{ number_format($row->score) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-4 text-ink-soft">{{ __('play.leaderboard.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
