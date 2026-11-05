<?php

namespace App\Console\Commands;

use App\Domain\Gameplay\Enums\RoomStatus;
use App\Domain\Gameplay\Models\Room;
use Illuminate\Console\Command;

/**
 * Cancels lobbies nobody started before game.room_expiry_minutes, so they stop
 * counting against the concurrent-room limit and leave the open-rooms list.
 */
class ExpireRooms extends Command
{
    protected $signature = 'rooms:expire';

    protected $description = 'Cancel waiting rooms that passed their expiry time';

    public function handle(): int
    {
        $count = Room::query()
            ->where('status', RoomStatus::Waiting->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => RoomStatus::Cancelled->value, 'updated_at' => now()]);

        $this->info("Cancelled {$count} expired room(s).");

        return self::SUCCESS;
    }
}
