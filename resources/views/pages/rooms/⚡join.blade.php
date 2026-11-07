<?php

use App\Domain\Gameplay\RoomService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * Front-office "Join a room" page. Open to guests: a valid code sends them to
 * the player sign-in, then on to the room through its invitation link.
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

    public function join(RoomService $rooms): void
    {
        $this->validate(['code' => ['required', 'string', 'max:12']]);

        $room = $rooms->findByCode($this->code);

        if ($room === null) {
            $this->addError('code', __('play.errors.room_not_found'));

            return;
        }

        if (auth()->guest()) {
            // Come back through the invitation link once signed in.
            session()->put('url.intended', route('rooms.invitation', $room->code));
            $this->redirect(route('login'));

            return;
        }

        $this->authorize('play games');

        try {
            $rooms->join($room, auth()->user());
        } catch (\DomainException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        $this->redirect(route('rooms.show', $room));
    }
};
?>

<div class="fk-container flex justify-center pt-12 pb-20">
    <div class="flex w-full max-w-[520px] flex-col gap-6">
        <div class="flex flex-col gap-2">
            <h1 class="fk-h2 m-0 text-[52px] leading-none">{{ __('play.join.title') }}</h1>
            <p class="m-0 text-lg text-ink-muted">{{ __('play.join.description') }}</p>
        </div>

        @if (session('error'))
            <p role="alert" class="m-0 rounded-xl bg-tint-sun p-3 font-semibold text-[#5A3618]">{{ session('error') }}</p>
        @endif

        <form wire:submit="join" aria-labelledby="join-h" class="flex flex-col gap-4 rounded-[28px] bg-ink p-7 text-sand">
            <h2 id="join-h" class="m-0 text-2xl font-semibold">{{ __('play.lobby.join_title') }}</h2>
            <label for="code" class="text-[15px] text-[#C9D3D0]">{{ __('play.lobby.join_hint') }}</label>
            <div class="flex gap-2">
                <input wire:model="code" id="code" type="text" autocomplete="off" autocapitalize="characters" maxlength="12" placeholder="7KQ2AB"
                       @error('code') aria-invalid="true" aria-describedby="code-error" @enderror
                       class="min-h-14 min-w-0 flex-1 rounded-[14px] border-2 border-[#3C5A62] bg-[#183740] px-4 text-center text-2xl font-bold tracking-[0.3em] text-sand uppercase">
                <button type="submit" class="fk-btn min-h-14 rounded-[14px] bg-sun px-[22px] text-ink">{{ __('play.join.submit') }}</button>
            </div>
            @error('code') <span id="code-error" role="alert" class="text-sm font-semibold text-sun">{{ $message }}</span> @enderror
            @guest
                <p class="m-0 text-sm text-[#C9D3D0]">{{ __('play.join.guest_hint') }}</p>
            @endguest
        </form>

        <p class="m-0 text-ink-muted">
            {{ __('play.join.or_create') }}
            <a href="{{ auth()->check() ? route('rooms.create') : route('login') }}" class="font-semibold text-sea">{{ __('play.create.title') }}</a>
        </p>
    </div>
</div>
