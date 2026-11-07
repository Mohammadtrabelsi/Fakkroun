<?php

use App\Domain\Gameplay\RoomService;
use Livewire\Component;

/**
 * Target of shared invitation links and (later) QR codes: /join/{code}.
 * The auth middleware sends guests to sign in and back here afterwards
 * (spec §12.2 steps 1–7).
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
