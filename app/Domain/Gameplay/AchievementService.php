<?php

namespace App\Domain\Gameplay;

use App\Domain\Gameplay\Models\Game;
use App\Domain\Gameplay\Models\GameResult;
use App\Domain\Gameplay\Models\UserAchievement;
use App\Domain\Gameplay\Notifications\AchievementUnlocked;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Achievements (spec §16, next step 07 §6), awarded from finished games only.
 * Each one is earned once per player (unique user_id + key), so running the
 * award twice for the same game changes nothing.
 */
class AchievementService
{
    /** Display order. */
    public const KEYS = ['first_game', 'first_win', 'ten_games', 'perfect_game', 'hat_trick', 'quick_thinker'];

    /** A perfect game needs at least this many questions. */
    public const PERFECT_MIN_QUESTIONS = 5;

    /** Average answer time, in ms, for "quick thinker" in a game of ≥ 5 all-correct answers. */
    public const QUICK_AVERAGE_MS = 4000;

    public function awardFor(Game $game): void
    {
        $results = GameResult::query()->where('game_id', $game->id)->get();

        foreach ($results as $result) {
            foreach ($this->earned($result, $game) as $key) {
                $this->grant($result->user_id, $key, $game->id);
            }
        }
    }

    /**
     * @return Collection<int, UserAchievement>
     */
    public function for(User $user): Collection
    {
        return UserAchievement::query()->where('user_id', $user->id)->get()
            ->sortBy(fn (UserAchievement $a) => array_search($a->key, self::KEYS, true))
            ->values();
    }

    /**
     * @return array<int, string>
     */
    private function earned(GameResult $result, Game $game): array
    {
        $history = GameResult::query()
            ->join('games', 'games.id', '=', 'game_results.game_id')
            ->where('games.status', Game::COMPLETED)
            ->where('game_results.user_id', $result->user_id)
            ->orderByDesc('games.ended_at')
            ->orderByDesc('game_results.id')
            ->limit(500)
            ->get(['game_results.rank']);

        $games = $history->count();
        $wins = $history->where('rank', 1)->count();
        $perfect = $game->total_questions >= self::PERFECT_MIN_QUESTIONS && $result->correct_count === $game->total_questions;

        return array_keys(array_filter([
            'first_game' => $games >= 1,
            'first_win' => $wins >= 1,
            'ten_games' => $games >= 10,
            'perfect_game' => $perfect,
            'hat_trick' => $history->take(3)->count() === 3 && $history->take(3)->every(fn ($row) => (int) $row->rank === 1),
            'quick_thinker' => $perfect && $result->total_response_ms / max(1, $game->total_questions) <= self::QUICK_AVERAGE_MS,
        ]));
    }

    private function grant(int $userId, string $key, int $gameId): void
    {
        $inserted = UserAchievement::query()->insertOrIgnore([
            'user_id' => $userId,
            'key' => $key,
            'game_id' => $gameId,
            'achieved_at' => now(),
        ]);

        if ($inserted > 0) {
            User::query()->find($userId)?->notify(new AchievementUnlocked($key));
        }
    }
}
