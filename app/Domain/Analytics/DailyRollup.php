<?php

namespace App\Domain\Analytics;

use App\Domain\Gameplay\GameResultService;
use App\Domain\Gameplay\GameService;
use App\Domain\Gameplay\Models\Game;
use App\Domain\Gameplay\PlayerAnswerService;
use App\Domain\Gameplay\RoomService;
use App\Services\UserService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One row of platform_daily_stats per day (next step 10), so dashboards read
 * a small table instead of scanning answers for past days. Recomputing a day
 * replaces its row (idempotent); today is never stored because it is not over.
 */
class DailyRollup
{
    public function __construct(
        private readonly PlayerAnswerService $playerAnswers,
        private readonly GameService $games,
        private readonly UserService $users,
        private readonly RoomService $rooms,
        private readonly GameResultService $gameResults,
    ) {}

    public function compute(Carbon $day): array
    {
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();
        $answers = $this->playerAnswers->query()->whereBetween('received_at', [$start, $end]);
        $games = $this->games->query()->whereBetween('started_at', [$start, $end]);
        $completed = $this->games->query()->where('status', Game::COMPLETED)->whereBetween('ended_at', [$start, $end]);

        return [
            'day' => $start->toDateString(),
            'registrations' => $this->users->query()->whereBetween('created_at', [$start, $end])->count(),
            'rooms_created' => $this->rooms->query()->whereBetween('created_at', [$start, $end])->count(),
            'games_started' => (clone $games)->count(),
            'games_completed' => (clone $completed)->count(),
            'active_players' => (clone $answers)->distinct()->count('user_id'),
            'answers' => (clone $answers)->count(),
            'correct_answers' => (clone $answers)->where('is_correct', true)->count(),
            'duplicate_exclusions' => (clone $answers)->where('is_duplicate_excluded', true)->count(),
            'fast_answers' => (clone $answers)->where('flagged_fast', true)->count(),
            'total_response_ms' => (int) (clone $answers)->sum('response_ms'),
            'player_slots' => $this->gameResults->query()->whereIn('game_id', (clone $completed)->select('id'))->count(),
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
