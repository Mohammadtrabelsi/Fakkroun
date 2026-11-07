<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * Front-office "Join a room" page. Open to guests: a valid code sends them to
 * the player sign-in, then on to the room through its invitation link. The
 * form itself lives in the reusable <livewire:rooms.join-form> component.
 */
new #[Layout('components.layouts.fakkroun')] #[Title('Join a room')] class extends Component
{
    /** Pre-filled from the home page "Have a code?" form (?code=…). */
    #[Url]
    public string $code = '';

    public function mount(): void
    {
        if (auth()->check()) {
            $this->authorize('play games');
        }
    }
};
?>

<div class="fk-container flex justify-center pt-8 pb-14 max-sm:px-4 sm:pt-12 sm:pb-20">
    <div class="flex w-full max-w-[520px] flex-col gap-6">
        <div class="flex flex-col gap-2">
            <h1 class="fk-h2 m-0 text-[clamp(36px,10vw,52px)] leading-none">{{ __('play.join.title') }}</h1>
            <p class="m-0 text-lg text-ink-muted">{{ __('play.join.description') }}</p>
        </div>

        @if (session('error'))
            <p role="alert" class="m-0 rounded-xl bg-tint-sun p-3 font-semibold text-[#5A3618]">{{ session('error') }}</p>
        @endif

        <livewire:rooms.join-form :code="$code" />

        <p class="m-0 text-ink-muted">
            {{ __('play.join.or_create') }}
            <a href="{{ auth()->check() ? route('rooms.create') : route('login') }}" class="font-semibold text-sea">{{ __('play.create.title') }}</a>
        </p>
    </div>
</div>
