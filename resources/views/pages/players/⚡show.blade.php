<?php

use App\Domain\Gameplay\LeaderboardService;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** A player's profile and statistics (design brief Phase 8). */
new #[Layout('components.layouts.fakkroun')] #[Title('Player')] class extends Component
{
    public User $user;

    public function mount(User $user): void
    {
        abort_if($user->isSuspended(), 404);
        $this->user = $user;
    }

    #[Computed]
    public function stats(): array
    {
        return app(LeaderboardService::class)->stats($this->user);
    }

    #[Computed]
    public function achievements()
    {
        return app(\App\Domain\Gameplay\AchievementService::class)->for($this->user);
    }
};
?>

<div class="fk-container flex flex-col gap-8 pt-12 pb-20">
    <div class="flex items-center gap-4">
        <x-user-avatar :user="$user" size="72" />
        <div class="flex flex-col">
            <h1 class="fk-h2 m-0 text-[44px] leading-none">{{ $user->name }}</h1>
            <span class="text-ink-soft">{{ __('play.profile.since', ['date' => $user->created_at->isoFormat('LL')]) }}</span>
        </div>
    </div>
    <div class="grid gap-4 [grid-template-columns:repeat(auto-fit,minmax(180px,1fr))]">
        @foreach (['games', 'wins', 'score', 'best', 'correct_rate', 'questions_live'] as $key)
            <div class="flex flex-col gap-1 rounded-3xl border border-sand-300 bg-paper p-5">
                <span class="text-sm font-semibold text-ink-soft">{{ __('play.profile.'.$key) }}</span>
                <span class="font-display text-4xl font-extrabold text-sea">{{ $key === 'correct_rate' ? $this->stats[$key].'%' : number_format($this->stats[$key]) }}</span>
            </div>
        @endforeach
    </div>
    <section class="flex flex-col gap-4" aria-labelledby="achievements-title">
        <h2 id="achievements-title" class="fk-h2 m-0 text-[28px]">{{ __('progress.achievements_title') }}</h2>
        @if ($this->achievements->isEmpty())
            <p class="m-0 text-ink-soft">{{ __('progress.no_achievements_public') }}</p>
        @else
            <ul class="m-0 grid list-none gap-3 p-0 [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))]">
                @foreach ($this->achievements as $achievement)
                    <li class="flex flex-col gap-1 rounded-3xl border border-sand-300 bg-paper p-5">
                        <span class="font-display text-xl font-bold text-sea">{{ __('progress.achievements.'.$achievement->key.'.name') }}</span>
                        <span class="text-sm text-ink-soft">{{ __('progress.achievements.'.$achievement->key.'.description') }}</span>
                        <span class="text-xs text-ink-soft">{{ $achievement->achieved_at->isoFormat('ll') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
