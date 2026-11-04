<?php

namespace App\Domain\Gameplay\Enums;

/**
 * Room lifecycle (spec §12.3). "Ready" is not stored: a waiting room is
 * ready once it holds enough players (RoomService::isReady).
 */
enum RoomStatus: string
{
    case Waiting = 'waiting';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
