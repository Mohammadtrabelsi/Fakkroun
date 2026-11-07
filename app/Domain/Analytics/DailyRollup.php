<?php

namespace App\Domain\Analytics;

use App\Domain\Gameplay\Models\Game;
use App\Domain\Gameplay\Models\GameResult;
use App\Domain\Gameplay\Models\PlayerAnswer;
use App\Domain\Gameplay\Models\Room;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One row of platform_daily_stats per day (next step 10), so dashboards read
 * a small table instead of scanning answers for past days. Recomputing a day
 * replaces its row (idempotent); today is never stored because it is not over.
 */
class DailyRollup
{
    public function compute(Carbon $day): array
    {
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();
        $answers = PlayerAnswer::query()->whereBetween('received_at', [$start, $end]);
        $games = Game::query()->whereBetween('started_at', [$start, $end]);
        $completed = Game::query()->where('status', Game::COMPLETED)->whereBetween('ended_at', [$start, $end]);

        return [
            'day' => $start->toDateString(),
            'registrations' => User::query()->whereBetween('created_at', [$start, $end])->count(),
            'rooms_created' => Room::query()->whereBetween('created_at', [$start, $end])->count(),
            'games_started' => (clone $games)->count(),
            'games_completed' => (clone $completed)->count(),
            'active_players' => (clone $answers)->distinct()->count('user_id'),
            'answers' => (clone $answers)->count(),
            'correct_answers' => (clone $answers)->where('is_correct', true)->count(),
            'duplicate_exclusions' => (clone $answers)->where('is_duplicate_excluded', true)->count(),
            'fast_answers' => (clone $answers)->where('flagged_fast', true)->count(),
            'total_response_ms' => (int) (clone $answers)->sum('response_ms'),
            'player_slots' => GameResult::query()->whereIn('game_id', (clone $completed)->select('id'))->count(),
            'computed_at' => now(),
        ];
    }

    /** Stores the given day (never today or later). Returns false when skipped. */
    public function store(Carbon $day): bool
    {
        if ($day->copy()->startOfDay()->gte(now()->startOfDay())) {
            return false;
        }

        $row = $this->compute($day);
        DB::table('platform_daily_stats')->upsert([$row], ['day'], array_keys(array_diff_key($row, ['day' => true])));

        return true;
    }

    /**
     * Stored rows for a period, keyed by Y-m-d.
     *
     * @return array<string, object>
     */
    public function rows(Carbon $from, Carbon $to): array
    {
        return DB::table('platform_daily_stats')
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->day)->toDateString())
            ->all();
    }
}
