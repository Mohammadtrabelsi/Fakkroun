<?php

use App\Domain\Gameplay\RoomService;
use Livewire\Component;

/**
 * Target of shared invitation links and (later) QR codes: /join/{code}.
 * Visitors without a session go to the join page with the code filled in,
 * where they pick a nickname and play as a guest (spec §12.2).
 */
new class extends Component
{
    public function mount(string $code, RoomService $rooms): void
    {
        $room = $rooms->findByCode($code);

        if ($room === null) {
            session()->flash('error', __('play.errors.room_not_found'));
            $this->redirectRoute('rooms.join');

            return;
        }

        if (auth()->guest()) {
            $this->redirectRoute('rooms.join', ['code' => $room->code]);

            return;
        }

        try {
            $rooms->join($room, auth()->user());
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
            $this->redirectRoute('rooms.join');

            return;
        }

        $this->redirectRoute('rooms.show', $room);
    }
};
?>

<div></div>
