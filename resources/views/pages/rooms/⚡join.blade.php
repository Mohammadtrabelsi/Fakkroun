<?php

use App\Domain\Gameplay\GuestPlayers;
use App\Domain\Gameplay\RoomService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/*
 * Front-office "Join a room" page. Playing needs no account: visitors without
 * a session pick a nickname and are signed in as a guest player (GuestPlayers).
 * Only creating a room needs a real account.
 */
new #[Layout('components.layouts.fakkroun')] #[Title('Join a room')] class extends Component
{
    /** Pre-filled from the home page "Have a code?" form and invitation links (?code=…). */
    #[Url]
    public string $code = '';

    /** Display name for visitors joining without an account. */
    public string $nickname = '';

    public function mount(): void
    {
        if (auth()->check()) {
            $this->authorize('play games');
        }
    }

    public function join(RoomService $rooms, GuestPlayers $guests): void
    {
        $rules = ['code' => ['required', 'string', 'max:12']];

        if (auth()->guest()) {
            $rules['nickname'] = ['required', 'string', 'min:2', 'max:24'];
        }

        $this->validate($rules, [], ['nickname' => __('play.join.nickname')]);

        $room = $rooms->findByCode($this->code);

        if ($room === null) {
            $this->addError('code', __('play.errors.room_not_found'));

            return;
        }

        $guest = null;

        if (auth()->guest()) {
            $guest = $guests->create($this->nickname);
            Auth::login($guest);
            session()->regenerate();
        }

        $this->authorize('play games');

        try {
            $rooms->join($room, auth()->user());
        } catch (\DomainException $e) {
            if ($guest !== null) {
                // Nothing to keep: the guest never got into a room.
                Auth::guard('web')->logout();
                $guest->forceDelete();
            }

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
            @guest
                <label for="nickname" class="text-[15px] text-[#C9D3D0]">{{ __('play.join.nickname') }}</label>
                <input wire:model="nickname" id="nickname" type="text" autocomplete="nickname" minlength="2" maxlength="24" placeholder="{{ __('play.join.nickname_placeholder') }}"
                       @error('nickname') aria-invalid="true" aria-describedby="nickname-error" @enderror
                       class="min-h-14 rounded-[14px] border-2 border-[#3C5A62] bg-[#183740] px-4 text-lg text-sand">
                @error('nickname') <span id="nickname-error" role="alert" class="text-sm font-semibold text-sun">{{ $message }}</span> @enderror
            @endguest
            <label for="code" class="text-[15px] text-[#C9D3D0]">{{ __('play.lobby.join_hint') }}</label>
            <div class="flex gap-2">
                <input wire:model="code" id="code" type="text" autocomplete="off" autocapitalize="characters" maxlength="12" placeholder="7KQ2AB"
                       @error('code') aria-invalid="true" aria-describedby="code-error" @enderror
                       class="min-h-14 min-w-0 flex-1 rounded-[14px] border-2 border-[#3C5A62] bg-[#183740] px-4 text-center text-2xl font-bold tracking-[0.3em] text-sand uppercase">
                <button type="submit" class="fk-btn min-h-14 rounded-[14px] bg-sun px-[22px] text-ink">{{ __('play.join.submit') }}</button>
            </div>
            @error('code') <span id="code-error" role="alert" class="text-sm font-semibold text-sun">{{ $message }}</span> @enderror
            @guest
                <p class="m-0 text-sm text-[#C9D3D0]">
                    {{ __('play.join.guest_hint') }}
                    <a href="{{ route('login') }}" class="font-semibold text-sun">{{ __('play.join.sign_in') }}</a>
                </p>
            @endguest
        </form>

        <p class="m-0 text-ink-muted">
            {{ __('play.join.or_create') }}
            <a href="{{ auth()->check() && ! auth()->user()->isGuest() ? route('rooms.create') : route('login') }}" class="font-semibold text-sea">{{ __('play.create.title') }}</a>
            <span class="text-sm">{{ __('play.join.create_needs_account') }}</span>
        </p>
    </div>
</div>
