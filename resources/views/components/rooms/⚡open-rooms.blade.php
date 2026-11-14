<?php

use App\Domain\Gameplay\RoomService;
use Livewire\Component;

/*
 * "Open rooms": the public rooms still waiting for players, refreshed while
 * on screen. Any player can enter one through its invitation page (visitors
 * pick a nickname there). Private rooms never show up here: only people the
 * host shares the QR code, link or room code with can join them.
 *
 * <livewire:rooms.open-rooms />
 */
new class extends Component
{
    protected RoomService $rooms;

    public function boot(RoomService $rooms): void
    {
        $this->rooms = $rooms;
    }

    /**
     * @return array<string, mixed>
     */
    public function with(): array
    {
        return [
            'openRooms' => $this->rooms->openRooms()->map(fn ($room): array => [
                'id' => $room->id,
                'name' => (string) $room->name,
                'meta' => __('play.lobby.room_meta', ['players' => $room->players_count, 'max' => $room->max_players, 'host' => $room->host?->name]),
                'url' => route('rooms.invitation', $room->code),
                'label' => __('play.lobby.join_room', ['name' => $room->name]),
            ])->all(),
        ];
    }
};
?>

<section aria-labelledby="open-h" class="flex flex-col gap-3.5 rounded-[28px] border border-sand-300 bg-paper p-5 text-ink sm:p-6" wire:poll.visible.10s>
    <div class="flex items-center justify-between gap-3">
        <h2 id="open-h" class="m-0 text-[22px] font-semibold">{{ __('play.lobby.open_rooms') }}</h2>
        <span class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-[#2F6B34]"><span aria-hidden="true" class="h-2 w-2 rounded-full bg-correct"></span>{{ __('play.lobby.live') }}</span>
    </div>
    <ul class="m-0 flex list-none flex-col gap-2.5 p-0">
        @forelse ($openRooms as $room)
            <li class="flex items-center gap-3 rounded-2xl bg-sand p-3" wire:key="open-{{ $room['id'] }}">
                <div class="flex min-w-0 flex-1 flex-col gap-0.5">
                    <b class="truncate text-base">{{ $room['name'] }}</b>
                    <span class="text-[13px] text-ink-soft">{{ $room['meta'] }}</span>
                </div>
                <a href="{{ $room['url'] }}" aria-label="{{ $room['label'] }}" class="inline-flex min-h-11 items-center rounded-xl border-2 border-sea px-4 text-sm font-semibold text-sea no-underline">{{ __('play.join.submit') }}</a>
            </li>
        @empty
            <li class="text-[15px] text-ink-soft">{{ __('play.lobby.no_open_rooms') }}</li>
        @endforelse
    </ul>
</section>
