<?php

use App\Domain\Gameplay\Models\Room;
use App\Domain\Gameplay\RoomService;
use App\Support\Livewire\ThrottlesSubmissions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Target of shared invitation links and of the waiting room's QR code:
 * /join/{code} (spec §12.2). Visitors pick any nickname and play as a guest;
 * signed-in players see the name they will play under. Both enter the room
 * with the "join" button, when they are ready.
 */
new #[Layout('components.layouts.fakkroun')] #[Title('Join a room')] class extends Component
{
    use ThrottlesSubmissions;

    #[Locked]
    public string $code = '';

    /** Display name for visitors joining without an account. */
    public string $nickname = '';

    protected RoomService $rooms;

    public function boot(RoomService $rooms): void
    {
        $this->rooms = $rooms;
    }

    public function mount(string $code): void
    {
        $room = $this->rooms->findByCode($code);

        if ($room === null) {
            session()->flash('error', __('play.errors.room_not_found'));
            $this->redirectRoute('rooms.join');

            return;
        }

        $this->code = $room->code;

        if (auth()->check()) {
            $this->authorize('play games');

            // Already in (second scan, refresh): straight back to the room.
            if ($this->rooms->isMember($room, auth()->user())) {
                $this->redirectRoute('rooms.show', $room);
            }
        }
    }

    public function join(): void
    {
        $this->throttleSubmission('join-room', 'nickname');

        $room = $this->room();

        if ($room === null) {
            $this->addError('nickname', __('play.errors.room_not_found'));

            return;
        }

        try {
            if (auth()->guest()) {
                $this->validate(['nickname' => ['required', 'string', 'min:2', 'max:24']], [], ['nickname' => __('play.join.nickname')]);

                Auth::login($this->rooms->joinAsGuest($room, $this->nickname));
                session()->regenerate();
            } else {
                $this->authorize('play games');
                $this->rooms->join($room, auth()->user());
            }
        } catch (\DomainException $e) {
            $this->addError('nickname', $e->getMessage());

            return;
        }

        $this->redirectRoute('rooms.show', $room);
    }

    /** "Use my account": sign in, then come back to this invitation. */
    public function signIn(): void
    {
        session()->put('url.intended', route('rooms.invitation', $this->code));
        $this->redirectRoute('login');
    }

    /**
     * @return array<string, mixed>
     *
     * @throws AuthorizationException
     */
    public function with(): array
    {
        $room = $this->room();
        $user = auth()->user();

        return [
            'roomName' => (string) $room?->name,
            'hostName' => (string) $room?->host?->name,
            'playersLabel' => $room ? __('play.room.players', ['count' => $this->rooms->playerCount($room), 'max' => $room->max_players]) : '',
            'player' => $user ? [
                'name' => $user->name,
                'initial' => mb_substr((string) $user->name, 0, 1),
                'isGuest' => $user->isGuest(),
            ] : null,
        ];
    }

    private function room(): ?Room
    {
        return $this->code === '' ? null : $this->rooms->findByCode($this->code);
    }
};
?>

<div class="fk-container flex justify-center pt-8 pb-14 max-sm:px-4 sm:pt-12 sm:pb-20">
    <div class="flex w-full max-w-[520px] flex-col gap-6">
        <div class="flex flex-col gap-2">
            <p class="m-0 text-sm font-semibold tracking-[.08em] text-sun-text uppercase">{{ __('play.invitation.eyebrow') }}</p>
            <h1 class="fk-h2 m-0 text-[clamp(32px,9vw,48px)] leading-none">{{ $roomName }}</h1>
            <p class="m-0 text-lg text-ink-muted">
                {{ __('play.invitation.hosted_by', ['name' => $hostName]) }} · {{ $playersLabel }} ·
                {{ __('play.game2.code_label') }} <b class="tracking-[0.2em]">{{ $code }}</b>
            </p>
        </div>

        <form wire:submit="join" aria-labelledby="invitation-h" class="flex flex-col gap-4 rounded-[28px] bg-ink p-5 text-sand sm:p-7">
            @if ($player)
                <h2 id="invitation-h" class="m-0 text-2xl font-semibold">{{ __('play.invitation.ready_title') }}</h2>
                <div class="flex items-center gap-3 rounded-[14px] border-2 border-[#3C5A62] bg-[#183740] p-3">
                    <span class="fk-avatar" aria-hidden="true">{{ $player['initial'] }}</span>
                    <span class="flex min-w-0 flex-col">
                        <span class="text-sm text-[#C9D3D0]">{{ $player['isGuest'] ? __('play.invitation.playing_as_guest') : __('play.invitation.playing_as') }}</span>
                        <b class="truncate text-lg">{{ $player['name'] }}</b>
                    </span>
                </div>
            @else
                <h2 id="invitation-h" class="m-0 text-2xl font-semibold">{{ __('play.invitation.name_title') }}</h2>
                <label for="nickname" class="text-[15px] text-[#C9D3D0]">{{ __('play.invitation.name_hint') }}</label>
                <input wire:model="nickname" id="nickname" type="text" autocomplete="nickname" required minlength="2" maxlength="24" placeholder="{{ __('play.join.nickname_placeholder') }}"
                       @error('nickname') aria-invalid="true" aria-describedby="nickname-error" @enderror
                       class="min-h-14 rounded-[14px] border-2 border-[#3C5A62] bg-[#183740] px-4 text-lg text-sand">
            @endif

            @error('nickname') <span id="nickname-error" role="alert" class="text-sm font-semibold text-sun">{{ $message }}</span> @enderror

            <button type="submit" wire:loading.attr="disabled" class="fk-btn min-h-14 rounded-[14px] bg-sun px-[22px] text-lg text-ink disabled:opacity-70">{{ __('play.invitation.submit') }}</button>
            <p class="m-0 text-sm text-[#C9D3D0]">{{ __('play.invitation.ready_hint') }}</p>

            @unless ($player)
                <p class="m-0 text-sm text-[#C9D3D0]">
                    {{ __('play.invitation.have_account') }}
                    <button type="button" wire:click="signIn" class="cursor-pointer border-0 bg-transparent p-0 font-semibold text-sun underline">{{ __('play.invitation.use_account') }}</button>
                </p>
            @endunless
        </form>
    </div>
</div>
