<?php

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

    public function playAgain(RoomService $rooms): void
    {
        try {
            $room = $rooms->replay($this->room, auth()->user());
        } catch (\DomainException|\Illuminate\Validation\ValidationException $e) {
            $this->redirect(route('rooms.create'), navigate: true);

            return;
        }

        $this->redirect(route('rooms.show', $room), navigate: true);
    }
};
?>

@php($results = $this->results)
@php($recap = $this->recap)
@php($podium = collect($results)->take(3)->values())
@php($right = collect($recap)->where('correct', true)->where('excluded', false)->count())
<div class="fk-container flex flex-col gap-8 pt-10 pb-20">
    <h1 class="fk-h2 m-0 text-center text-[52px] leading-none">{{ __('play.results.title') }}</h1>

    @if ($podium->isNotEmpty())
        <section aria-label="{{ __('play.results2.podium') }}" class="mx-auto flex w-full max-w-[720px] items-end justify-center gap-3">
            @foreach ([1, 0, 2] as $slot)
                @if ($row = $podium->get($slot))
                    @php([$height, $bg, $medal] = [[0 => 'h-[180px]', 1 => 'h-[140px]', 2 => 'h-[110px]'][$slot], [0 => 'bg-sun', 1 => 'bg-tint-sea', 2 => 'bg-tint-sun'][$slot], [0 => '🥇', 1 => '🥈', 2 => '🥉'][$slot]])
                    <div class="flex flex-1 flex-col items-center gap-2">
                        <span class="text-3xl" aria-hidden="true">{{ $medal }}</span>
                        <b class="text-center text-lg">{{ $row['name'] }}</b>
                        <span class="font-display text-2xl font-extrabold text-sea">{{ number_format($row['score']) }}</span>
                        <div class="flex w-full items-start justify-center rounded-t-3xl pt-3 font-display text-4xl font-extrabold text-ink {{ $height }} {{ $bg }}">{{ $row['rank'] }}</div>
                    </div>
                @endif
            @endforeach
        </section>
    @endif

    <div class="flex flex-wrap justify-center gap-3" x-data="{ copied: false }">
        <button type="button" wire:click="playAgain" class="fk-btn fk-btn-primary min-h-14 rounded-2xl px-7 text-[17px]">{{ __('play.results2.play_again') }}</button>
        <a href="{{ route('rooms.create') }}" wire:navigate class="fk-btn fk-btn-outline min-h-14 rounded-2xl">{{ __('play.results2.new_room') }}</a>
        <button type="button" class="fk-btn fk-btn-outline min-h-14 rounded-2xl"
                x-on:click="navigator.share ? navigator.share({ title: @js($room->name), text: @js(__('play.results2.share_text')), url: location.href }) : (navigator.clipboard?.writeText(location.href), copied = true)">
            <span x-show="! copied">{{ __('play.results2.share') }}</span><span x-show="copied" x-cloak>{{ __('play.results2.copied') }}</span>
        </button>
    </div>

    <div class="flex flex-wrap items-start gap-6">
        <section aria-labelledby="lb-h" class="flex min-w-0 flex-[1_1_360px] flex-col gap-3.5 rounded-[28px] border border-sand-300 bg-paper p-6">
            <h2 id="lb-h" class="m-0 text-[22px] font-semibold">{{ __('play.results2.title') }}</h2>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-base">
                    <thead>
                        <tr class="text-start text-[13px] text-ink-soft">
                            <th scope="col" class="px-1.5 py-2 text-start font-semibold">#</th>
                            <th scope="col" class="px-1.5 py-2 text-start font-semibold">{{ __('play.results.player') }}</th>
                            <th scope="col" class="px-1.5 py-2 text-end font-semibold">{{ __('play.results.correct') }}</th>
                            <th scope="col" class="px-1.5 py-2 text-end font-semibold">{{ __('play.results.score') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($results as $row)
                            <tr class="{{ $row['user_id'] === auth()->id() ? 'bg-tint-green' : '' }} border-t border-sand-300">
                                <td class="px-1.5 py-3 font-semibold text-ink-soft">{{ $row['rank'] }}</td>
                                <td class="px-1.5 py-3 font-medium">{{ $row['name'] }}</td>
                                <td class="px-1.5 py-3 text-end tabular-nums">{{ $row['correct'] }}</td>
                                <td class="px-1.5 py-3 text-end font-semibold tabular-nums">{{ number_format($row['score']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section aria-labelledby="recap-h" class="flex min-w-0 flex-[999_1_560px] flex-col gap-3.5">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 id="recap-h" class="fk-h2 m-0 text-[34px]">{{ __('play.results2.recap') }}</h2>
                <span class="text-ink-soft">{{ __('play.results2.got_right', ['right' => $right, 'total' => count($recap)]) }}</span>
            </div>
            @php($dir = config('app.available_locales.'.$room->setting('language').'.dir', 'ltr'))
            @foreach ($recap as $item)
                @php($ok = $item['correct'] && ! $item['excluded'])
                @php($label = ! $item['answered'] ? __('play.results2.not_answered') : ($item['excluded'] ? __('play.results2.excluded') : ($ok ? __('play.results2.you_got_it') : __('play.results2.you_missed'))))
                <article class="flex items-start gap-4 rounded-[22px] border border-sand-300 bg-paper px-[22px] py-5">
                    <span class="flex h-10 w-10 flex-none items-center justify-center rounded-full text-lg font-bold text-white {{ $ok ? 'bg-correct' : 'bg-wrong' }}" role="img" aria-label="{{ $label }}">{{ $ok ? '✓' : '✗' }}</span>
                    <div class="flex min-w-0 flex-1 flex-col gap-1.5" dir="{{ $dir }}">
                        <span class="text-[13px] font-semibold text-[#0B5A55]">{{ __('play.results2.question_n', ['n' => $item['position']]) }} · {{ $label }}</span>
                        <b class="text-[17px] leading-snug">{{ $item['body'] }}</b>
                        <span class="leading-normal text-ink-muted">{{ __('play.results2.answer', ['answer' => '']) }}<b class="text-ink">{{ $item['correct_text'] }}</b>@if ($item['explanation']) — {{ $item['explanation'] }}@endif</span>
                        @if ($item['reference_url'])<a href="{{ $item['reference_url'] }}" target="_blank" rel="noopener noreferrer nofollow" class="fk-link text-[13px]">{{ __('play.game.source') }}</a>@endif
                    </div>
                </article>
            @endforeach
        </section>
    </div>
</div>
