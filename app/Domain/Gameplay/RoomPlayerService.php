<?php

namespace App\Domain\Gameplay;

use App\Domain\Gameplay\Models\RoomPlayer;
use App\Services\Concerns\ManagesModel;

/**
 * Single entry point for reading and writing RoomPlayer records.
 */
class RoomPlayerService
{
    /** @use ManagesModel<RoomPlayer> */
    use ManagesModel;

    protected function model(): string
    {
        return RoomPlayer::class;
    }
}
