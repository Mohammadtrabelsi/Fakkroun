<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Where every "Play" button leads: the player picks between joining a room
 * (code or QR code, no account needed) and hosting one (create the room, then
 * share its QR code from the waiting room).
 *
 * Hosting needs a real account: the create page sends visitors and guest
 * players through the sign-in and brings them back.
 */
new #[Layout('components.layouts.fakkroun')] #[Title('Play')] class extends Component
{
    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'needsAccount' => auth()->user()?->isGuest() !== false,
            'hostSteps' => [__('play.start.host_step_1'), __('play.start.host_step_2'), __('play.start.host_step_3')],
        ];
    }
};
?>

<div class="fk-container flex justify-center pt-8 pb-14 max-sm:px-4 sm:pt-12 sm:pb-20">
    <div class="flex w-full max-w-[960px] flex-col gap-6">
        <div class="flex flex-col gap-2">
            <h1 class="fk-h2 m-0 text-[clamp(36px,10vw,52px)] leading-none">{{ __('play.start.title') }}</h1>
            <p class="m-0 text-lg text-ink-muted">{{ __('play.start.lead') }}</p>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <section aria-labelledby="start-join-h" class="flex flex-col gap-4 rounded-[28px] bg-ink p-5 text-sand sm:p-7">
                <h2 id="start-join-h" class="m-0 text-2xl font-semibold">{{ __('play.start.join_title') }}</h2>
                <p class="m-0 text-[#C9D3D0]">{{ __('play.start.join_body') }}</p>
                <form method="GET" action="{{ route('rooms.join') }}" class="mt-auto flex flex-col gap-2">
                    <label for="start-code" class="text-[15px] text-[#C9D3D0]">{{ __('play.lobby.join_hint') }}</label>
                    <div class="flex flex-col gap-2 min-[400px]:flex-row">
                        <input id="start-code" name="code" type="text" autocomplete="off" autocapitalize="characters" maxlength="12" placeholder="7KQ2AB" dir="ltr"
                               class="min-h-14 min-w-0 flex-1 rounded-[14px] border-2 border-[#3C5A62] bg-[#183740] px-4 text-center text-2xl font-bold tracking-[0.3em] text-sand uppercase">
                        <button type="submit" class="fk-btn min-h-14 rounded-[14px] bg-sun px-[22px] text-ink">{{ __('play.start.join_button') }}</button>
                    </div>
                </form>
            </section>

            <section aria-labelledby="start-host-h" class="flex flex-col gap-4 rounded-[28px] border border-sand-300 bg-paper p-5 text-ink sm:p-7">
                <h2 id="start-host-h" class="m-0 text-2xl font-semibold">{{ __('play.start.host_title') }}</h2>
                <p class="m-0 text-ink-muted">{{ __('play.start.host_body') }}</p>
                <ol class="m-0 flex list-none flex-col gap-2.5 p-0">
                    @foreach ($hostSteps as $step)
                        <li class="flex items-center gap-3">
                            <span aria-hidden="true" class="flex size-8 shrink-0 items-center justify-center rounded-full bg-tint-green font-display font-extrabold text-[#0B5A55]">{{ $loop->iteration }}</span>
                            <span>{{ $step }}</span>
                        </li>
                    @endforeach
                </ol>
                <div class="mt-auto flex flex-col gap-2">
                    <a href="{{ route('rooms.create') }}" class="fk-btn fk-btn-primary min-h-14 rounded-[14px] text-lg">{{ __('play.start.host_button') }}</a>
                    @if ($needsAccount)
                        <p class="m-0 text-sm text-ink-muted">{{ __('play.start.host_needs_account') }}</p>
                    @endif
                </div>
            </section>
        </div>
    </div>
</div>
