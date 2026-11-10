<?php

use App\Domain\Gameplay\RoomService;
use Livewire\Component;

/*
 * Reusable "Join a room" form: takes a room code and sends the player into
 * the room. Guests are routed through the player sign-in, then on to the room
 * through its invitation link.
 *
 * <livewire:rooms.join-form />
 * <livewire:rooms.join-form :code="$code" />   (pre-filled, e.g. from ?code=…)
 */
new class extends Component
{
    public string $code = '';

    public function mount(string $code = ''): void
    {
        $this->code = $code;
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

<form wire:submit="join" aria-labelledby="join-h" class="flex flex-col gap-4 rounded-[28px] bg-ink p-5 text-sand sm:p-7">
    <h2 id="join-h" class="m-0 text-2xl font-semibold">{{ __('play.lobby.join_title') }}</h2>
    <label for="code" class="text-[15px] text-[#C9D3D0]">{{ __('play.lobby.join_hint') }}</label>
    <div class="flex flex-col gap-2 min-[400px]:flex-row">
        <input wire:model="code" id="code" type="text" autocomplete="off" autocapitalize="characters" maxlength="12" placeholder="7KQ2AB"
               @error('code') aria-invalid="true" aria-describedby="code-error" @enderror
               class="min-h-14 min-w-0 flex-1 rounded-[14px] border-2 border-[#3C5A62] bg-[#183740] px-4 text-center text-2xl font-bold tracking-[0.3em] text-sand uppercase">
        <button type="submit" wire:loading.attr="disabled" class="fk-btn min-h-14 rounded-[14px] bg-sun px-[22px] text-ink disabled:opacity-70">{{ __('play.join.submit') }}</button>
    </div>
    @error('code') <span id="code-error" role="alert" class="text-sm font-semibold text-sun">{{ $message }}</span> @enderror
    @guest
        <p class="m-0 text-sm text-[#C9D3D0]">{{ __('play.join.guest_hint') }}</p>
    @endguest
</form>
