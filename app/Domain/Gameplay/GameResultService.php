<?php

namespace App\Domain\Gameplay;

use App\Domain\Gameplay\Models\GameResult;
use App\Models\User;
use App\Services\Concerns\ManagesModel;
use Illuminate\Database\Eloquent\Collection;

/**
 * Single entry point for reading and writing GameResult records.
 */
class GameResultService
{
    /** @use ManagesModel<GameResult> */
    use ManagesModel;

    /**
     * The player's latest results, with the room of each game.
     *
     * @return Collection<int, GameResult>
     */
    public function recentFor(User $user, int $limit = 5): Collection
    {
        return $this->query()->where('user_id', $user->id)->with('game.room:id,ulid,name')->latest('id')->limit($limit)->get();
    }

    protected function model(): string
    {
        return GameResult::class;
    }
}
