<?php

use App\Domain\Gameplay\Models\Game;
use App\Domain\Gameplay\Models\GameResult;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/** The signed-in player's finished games (next step 07 §6). */
new #[Layout('components.layouts.fakkroun')] #[Title('My games')] class extends Component
{
    use WithPagination;

    #[Computed]
    public function results()
    {
        return app(\App\Domain\Gameplay\GameResultService::class)->query()
            ->where('user_id', auth()->id())
            ->whereHas('game', fn ($q) => $q->where('status', Game::COMPLETED))
            ->with(['game:id,room_id,total_questions,ended_at', 'game.room:id,ulid,name,settings'])
            ->addSelect(['players_count' => app(\App\Domain\Gameplay\GameResultService::class)->query()->from('game_results as gr')->selectRaw('count(*)')->whereColumn('gr.game_id', 'game_results.game_id')])
            ->orderByDesc('id')
            ->paginate(15);
    }
};
?>

<div class="fk-container flex flex-col gap-8 pt-12 pb-20">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-col gap-2">
            <span class="fk-eyebrow">{{ __('progress.history.eyebrow') }}</span>
            <h1 class="fk-h2 m-0">{{ __('progress.history.title') }}</h1>
        </div>
        <a href="{{ route('rooms.create') }}" wire:navigate class="fk-btn fk-btn-primary">{{ __('play.nav.new_room') }}</a>
    </div>

    @if ($this->results->isEmpty())
        <div class="flex flex-col items-start gap-4 rounded-3xl border border-sand-300 bg-paper p-8">
            <x-fakkroun.mascot :size="96" />
            <p class="m-0 text-lg">{{ __('progress.history.empty') }}</p>
        </div>
    @else
        <ul class="m-0 flex list-none flex-col gap-3 p-0">
            @foreach ($this->results as $result)
                <li wire:key="h-{{ $result->id }}" class="flex flex-wrap items-center gap-4 rounded-3xl border border-sand-300 bg-paper px-6 py-4">
                    <span @class(['flex h-12 w-12 flex-none items-center justify-center rounded-2xl font-display text-2xl font-extrabold', 'bg-sun text-ink' => $result->rank === 1, 'bg-sand-200 text-ink' => $result->rank !== 1])>#{{ $result->rank }}</span>
                    <div class="flex min-w-0 flex-1 flex-col">
                        <span class="truncate text-lg font-semibold">{{ $result->game?->room?->name ?? '—' }}</span>
                        <span class="text-sm text-ink-soft">
                            {{ $result->game?->ended_at?->isoFormat('lll') }}
                            · {{ __('progress.history.players', ['count' => $result->players_count]) }}
                            · {{ __('progress.history.correct', ['correct' => $result->correct_count, 'total' => $result->game?->total_questions]) }}
                        </span>
                    </div>
                    <span class="font-display text-2xl font-extrabold text-sea">{{ __('play.dashboard.points', ['points' => number_format($result->score)]) }}</span>
                    @if ($result->game?->room)
                        <a href="{{ route('rooms.results', $result->game->room) }}" wire:navigate class="fk-btn">{{ __('progress.history.review') }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
        <div>{{ $this->results->links() }}</div>
    @endif
</div>
