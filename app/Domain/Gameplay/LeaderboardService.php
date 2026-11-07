<?php

namespace App\Domain\Gameplay;

use App\Domain\Gameplay\Models\Game;
use App\Domain\Questions\Enums\QuestionSource;
use App\Domain\Questions\Enums\QuestionStatus;
use App\Domain\Questions\QuestionService;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Global leaderboards (weekly / all-time) and per-player statistics, from
 * finished games only. Aggregates are cached briefly; nothing loads rows
 * per player (no N+1).
 */
class LeaderboardService
{
    public function __construct(
        private readonly GameResultService $gameResults,
        private readonly QuestionService $questions,
        private readonly GameService $games,
    ) {}

    /**
     * @return Collection<int, object{user_id: int, name: string, score: int, games: int, wins: int}>
     */
    public function top(string $period = 'week', int $limit = 20): Collection
    {
        $period = in_array($period, ['week', 'all'], true) ? $period : 'week';

        return Cache::remember("leaderboard:{$period}:{$limit}", 60, fn () => $this->gameResults->query()
            ->join('games', 'games.id', '=', 'game_results.game_id')
            ->join('users', 'users.id', '=', 'game_results.user_id')
            ->where('games.status', Game::COMPLETED)
            ->whereNull('users.deleted_at')
            ->when($period === 'week', fn ($query) => $query->where('games.ended_at', '>=', now()->startOfWeek()))
            ->groupBy('game_results.user_id', 'users.name')
            ->selectRaw('game_results.user_id, users.name, sum(game_results.score) as score, count(*) as games, sum(case when game_results.rank = 1 then 1 else 0 end) as wins')
            ->orderByDesc('score')
            ->orderBy('game_results.user_id')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (object) [
                'user_id' => (int) $row->user_id,
                'name' => (string) $row->name,
                'score' => (int) $row->score,
                'games' => (int) $row->games,
                'wins' => (int) $row->wins,
            ]));
    }

    /**
     * @return array<string, int|float>
     */
    public function stats(User $user): array
    {
        $row = $this->gameResults->query()
            ->join('games', 'games.id', '=', 'game_results.game_id')
            ->where('games.status', Game::COMPLETED)
            ->where('game_results.user_id', $user->id)
            ->selectRaw('count(*) as games, coalesce(sum(score), 0) as score, coalesce(sum(case when rank = 1 then 1 else 0 end), 0) as wins, coalesce(sum(correct_count), 0) as correct, coalesce(sum(incorrect_count), 0) as incorrect, coalesce(max(score), 0) as best')
            ->toBase()
            ->first();

        $answered = (int) $row->correct + (int) $row->incorrect;

        return [
            'games' => (int) $row->games,
            'wins' => (int) $row->wins,
            'score' => (int) $row->score,
            'best' => (int) $row->best,
            'correct_rate' => $answered > 0 ? round(100 * (int) $row->correct / $answered, 1) : 0.0,
            'questions_live' => $this->questions->query()->where('creator_id', $user->id)->where('source', QuestionSource::Community->value)->where('status', QuestionStatus::Published->value)->count(),
        ];
    }

    /**
     * Games still running whose clock says something should happen now.
     *
     * @return Collection<int, Game>
     */
    public function gamesDue(int $limit = 500): Collection
    {
        $reveal = (int) config('fakkroun.gameplay.reveal_seconds', 6);

        return $this->games->query()
            ->where('status', Game::IN_PROGRESS)
            ->whereExists(fn ($query) => $query->select(DB::raw(1))
                ->from('game_questions')
                ->whereColumn('game_questions.game_id', 'games.id')
                ->whereColumn('game_questions.position', 'games.current_position')
                ->where(fn ($q) => $q->where(fn ($open) => $open->whereNull('closed_at')->where('deadline_at', '<', now()))
                    ->orWhere('closed_at', '<=', now()->subSeconds($reveal))))
            ->limit($limit)
            ->get();
    }
}
