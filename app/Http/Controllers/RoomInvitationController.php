<?php

namespace App\Http\Controllers;

use App\Domain\Gameplay\RoomService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Target of shared invitation links and (later) QR codes: /join/{code}.
 * The auth middleware sends guests to sign in and back here afterwards
 * (spec §12.2 steps 1–7).
 */
class RoomInvitationController extends Controller
{
    public function __invoke(Request $request, string $code, RoomService $rooms): RedirectResponse
    {
        $room = $rooms->findByCode($code);

        if ($room === null) {
            return redirect()->route('rooms.join')->with('error', __('play.errors.room_not_found'));
        }

        try {
            $rooms->join($room, $request->user());
        } catch (\DomainException $e) {
            return redirect()->route('rooms.join')->with('error', $e->getMessage());
        }

        return redirect()->route('rooms.show', $room);
    }
}
