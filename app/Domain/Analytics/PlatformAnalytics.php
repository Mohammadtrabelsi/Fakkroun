<?php

namespace App\Domain\Analytics;

use App\Domain\Ai\AiGenerationBatchService;
use App\Domain\Gameplay\GameResultService;
use App\Domain\Gameplay\GameService;
use App\Domain\Gameplay\PlayerAnswerService;
use App\Domain\Gameplay\RoomService;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\QuestionModerationLogService;
use App\Domain\Questions\QuestionService;
use App\Domain\Questions\ReportService;
use App\Services\CategoryService;
use App\Services\PlatformSettings;
use App\Services\UserService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Platform and question analytics (spec §24). Every figure is computed from
 * stored, server-recorded events; nothing here is estimated or invented,
 * and an empty platform shows zeros.
 */
class PlatformAnalytics
{
    public function __construct(
        private readonly GameService $games,
        private readonly RoomService $rooms,
        private readonly GameResultService $gameResults,
        private readonly ReportService $reports,
        private readonly QuestionService $questions,
        private readonly AiGenerationBatchService $aiBatches,
        private readonly PlayerAnswerService $playerAnswers,
        private readonly CategoryService $categories,
        private readonly UserService $users,
        private readonly QuestionModerationLogService $moderationLogs,
    ) {}

    /** Answers a question needs before its correct rate is ranked. */
    public const MIN_ANSWERS = 5;

    /**
     * @return array{from: Carbon, to: Carbon}
     */
    public function period(?string $from, ?string $to): array
    {
        $end = filled($to) ? Carbon::parse($to)->endOfDay() : now()->endOfDay();
        $start = filled($from) ? Carbon::parse($from)->startOfDay() : $end->copy()->subDays(29)->startOfDay();

        return ['from' => $start, 'to' => $end];
    }

    /**
     * @return array<string, int|float|null>
     */
    public function overview(Carbon $from, Carbon $to): array
    {
        $games = $this->games->query()->whereBetween('started_at', [$from, $to]);
        $completed = (clone $games)->whereNotNull('ended_at')->where('status', 'completed');
        $answers = $this->answers($from, $to);
        $total = (clone $answers)->count();
        $resultGameIds = (clone $completed)->select('id');

        return [
            'rooms_created' => $this->rooms->query()->whereBetween('created_at', [$from, $to])->count(),
            'games_started' => (clone $games)->count(),
            'games_completed' => (clone $completed)->count(),
            'active_players' => $this->gameResults->query()->whereIn('game_id', (clone $games)->select('id'))->distinct()->count('user_id'),
            'avg_players_per_game' => round((float) $this->gameResults->query()->whereIn('game_id', $resultGameIds)->selectRaw('count(*) as players')->groupBy('game_id')->get()->avg('players'), 1),
            'answers' => $total,
            'correct_rate' => $total > 0 ? round(100 * (clone $answers)->where('is_correct', true)->count() / $total, 1) : null,
            'avg_response_seconds' => $total > 0 ? round(((float) (clone $answers)->avg('response_ms')) / 1000, 1) : null,
            'duplicate_exclusions' => (clone $answers)->where('is_duplicate_excluded', true)->count(),
        ];
    }

    /**
     * Games started per day, every day of the period present (zero-filled).
     *
     * @return array<string, int>
     */
    public function dailyGames(Carbon $from, Carbon $to): array
    {
        // Past days come from the daily rollup when stored; today and missing days are counted live.
        $stored = app(DailyRollup::class)->rows($from, $to);
        $counts = $this->games->query()->whereBetween('started_at', [$from, $to])
            ->selectRaw('date(started_at) as day, count(*) as total')
            ->groupBy(DB::raw('date(started_at)'))
            ->pluck('total', 'day');

        $days = [];

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $key = $day->toDateString();
            $days[$key] = isset($stored[$key]) ? (int) $stored[$key]->games_started : (int) ($counts[$key] ?? 0);
        }

        return $days;
    }

    /**
     * Answers and correct rate per category and per language.
     *
     * @return array{categories: array<int, array<string, mixed>>, languages: array<int, array<string, mixed>>}
     */
    public function breakdown(Carbon $from, Carbon $to): array
    {
        $base = $this->answers($from, $to)
            ->join('game_questions', 'game_questions.id', '=', 'player_answers.game_question_id')
            ->join('questions', 'questions.id', '=', 'game_questions.question_id');

        $categories = (clone $base)
            ->selectRaw('questions.category_id as id, count(*) as answers, sum(case when player_answers.is_correct then 1 else 0 end) as correct')
            ->groupBy('questions.category_id')
            ->orderByDesc('answers')
            ->get();

        $names = $this->categories->query()->whereIn('id', $categories->pluck('id'))->get()->keyBy('id');

        return [
            'categories' => $categories->map(fn ($row) => [
                'name' => $names[$row->id]?->name ?? '—',
                'answers' => (int) $row->answers,
                'correct_rate' => round(100 * $row->correct / max(1, $row->answers), 1),
            ])->all(),
            'languages' => (clone $base)
                ->selectRaw('game_questions.language as language, count(*) as answers, sum(case when player_answers.is_correct then 1 else 0 end) as correct')
                ->groupBy('game_questions.language')
                ->orderByDesc('answers')
                ->get()
                ->map(fn ($row) => ['language' => $row->language, 'answers' => (int) $row->answers, 'correct_rate' => round(100 * $row->correct / max(1, $row->answers), 1)])
                ->all(),
        ];
    }

    /**
     * Per-question figures: hardest, easiest, most played, most reported.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function questions(Carbon $from, Carbon $to, int $limit = 8): array
    {
        $stats = $this->answers($from, $to)
            ->join('game_questions', 'game_questions.id', '=', 'player_answers.game_question_id')
            ->selectRaw('game_questions.question_id as id, count(*) as answers, sum(case when player_answers.is_correct then 1 else 0 end) as correct, avg(player_answers.response_ms) as response_ms')
            ->groupBy('game_questions.question_id')
            ->get()
            ->map(fn ($row) => ['id' => (int) $row->id, 'answers' => (int) $row->answers, 'correct_rate' => round(100 * $row->correct / max(1, $row->answers), 1), 'avg_seconds' => round($row->response_ms / 1000, 1)]);

        $ranked = $stats->where('answers', '>=', self::MIN_ANSWERS);

        $reported = $this->reports->query()->whereBetween('created_at', [$from, $to])
            ->selectRaw('question_id as id, count(*) as reports')->groupBy('question_id')->orderByDesc('reports')->limit($limit)->get()
            ->map(fn ($row) => ['id' => (int) $row->id, 'reports' => (int) $row->reports]);

        $lists = [
            'hardest' => $ranked->sortBy('correct_rate')->take($limit)->values()->all(),
            'easiest' => $ranked->sortByDesc('correct_rate')->take($limit)->values()->all(),
            'most_played' => $stats->sortByDesc('answers')->take($limit)->values()->all(),
            'most_reported' => $reported->all(),
        ];

        $ids = collect($lists)->flatten(1)->pluck('id')->unique();
        $questions = $this->questions->withTrashed()->with('translations:id,question_id,language,body')->whereIn('id', $ids)->get()->keyBy('id');

        return array_map(fn (array $rows) => array_map(fn (array $row) => $row + [
            'body' => $questions[$row['id']]?->translations->firstWhere('language', $questions[$row['id']]->origin_language)?->body ?? '—',
            'uuid' => $questions[$row['id']]?->uuid,
        ], $rows), $lists);
    }

    /**
     * Question bank health: counts by status and source, open reports.
     *
     * @return array<string, mixed>
     */
    public function bank(): array
    {
        return [
            'by_status' => $this->questions->query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all(),
            'by_source' => $this->questions->query()->selectRaw('source, count(*) as total')->groupBy('source')->pluck('total', 'source')->all(),
            'open_reports' => $this->reports->query()->where('status', 'open')->count(),
        ];
    }

    /**
     * AI generation usage and acceptance in the period.
     *
     * @return array<string, int|float|null>
     */
    public function ai(Carbon $from, Carbon $to): array
    {
        $batches = $this->aiBatches->query()->whereBetween('created_at', [$from, $to]);
        $generated = (int) (clone $batches)->sum('generated_count');

        return [
            'batches' => (clone $batches)->where('kind', 'batch')->count(),
            'drafts' => (clone $batches)->where('kind', 'draft')->count(),
            'generated' => $generated,
            'accepted_rate' => $generated > 0 ? round(100 * (int) (clone $batches)->sum('accepted_count') / $generated, 1) : null,
            'published' => $this->questions->query()->where('source', 'ai')->where('status', 'published')->whereBetween('created_at', [$from, $to])->count(),
            'estimated_cost' => round((float) (clone $batches)->sum('estimated_cost'), 2),
        ];
    }

    /**
     * Fair play (next step 10): answers flagged as faster than a person can
     * read, and the players with the most of them. For review only.
     *
     * @return array{flagged: int, players: array<int, array{id: int, name: string, flagged: int, answers: int}>}
     */
    public function fairPlay(Carbon $from, Carbon $to): array
    {
        $threshold = (int) app(PlatformSettings::class)->get('quality.fast_answer_review_threshold');
        $rows = $this->answers($from, $to)
            ->selectRaw('user_id, count(*) as answers, sum(case when flagged_fast then 1 else 0 end) as flagged')
            ->groupBy('user_id')
            ->havingRaw('sum(case when flagged_fast then 1 else 0 end) >= ?', [$threshold])
            ->orderByDesc('flagged')
            ->limit(20)
            ->get();
        $names = $this->users->query()->whereIn('id', $rows->pluck('user_id'))->pluck('name', 'id');

        return [
            'flagged' => $this->answers($from, $to)->where('flagged_fast', true)->count(),
            'players' => $rows->map(fn ($r) => ['id' => (int) $r->user_id, 'name' => (string) ($names[$r->user_id] ?? '—'), 'flagged' => (int) $r->flagged, 'answers' => (int) $r->answers])->all(),
        ];
    }

    /**
     * Difficulty calibration over all plays: "easy" questions most players
     * miss, "hard" ones most get right. Thresholds are quality settings.
     *
     * @return array<int, array{uuid: string, body: string, difficulty: string, correct_rate: float, played: int}>
     */
    public function calibration(int $limit = 15): array
    {
        $q = app(PlatformSettings::class)->all()['quality'];
        $rate = 'times_correct * 100.0 / times_played';

        return $this->questions->query()
            ->with('translations:id,question_id,language,body')
            ->where('times_played', '>=', max(1, (int) $q['calibration_min_answers']))
            ->where(fn ($w) => $w
                ->where(fn ($e) => $e->where('difficulty', 'easy')->whereRaw("$rate < ?", [(int) $q['easy_min_correct_rate']]))
                ->orWhere(fn ($h) => $h->where('difficulty', 'hard')->whereRaw("$rate > ?", [(int) $q['hard_max_correct_rate']])))
            ->orderByDesc('times_played')
            ->limit($limit)
            ->get()
            ->map(fn (Question $question) => [
                'uuid' => $question->uuid,
                'body' => $question->translations->firstWhere('language', $question->origin_language)?->body ?? '—',
                'difficulty' => $question->difficulty->value,
                'correct_rate' => round(100 * $question->times_correct / max(1, $question->times_played), 1),
                'played' => (int) $question->times_played,
            ])->all();
    }

    /**
     * Moderation health: queue size, oldest waiting item, decisions per week.
     *
     * @return array{waiting: int, oldest_hours: ?int, weekly: array<string, int>}
     */
    public function moderation(): array
    {
        $oldest = $this->questions->query()->awaitingReview()->min(DB::raw('coalesce(submitted_at, created_at)'));
        $decisions = $this->moderationLogs->query()
            ->whereIn('action', ['approved', 'rejected', 'changes_requested'])
            ->where('created_at', '>=', now()->subWeeks(4)->startOfWeek())
            ->get(['created_at'])
            ->groupBy(fn ($log) => $log->created_at->startOfWeek()->toDateString())
            ->map->count()
            ->sortKeys()
            ->all();

        return [
            'waiting' => $this->questions->query()->awaitingReview()->count(),
            'oldest_hours' => $oldest ? (int) Carbon::parse($oldest)->diffInHours(now(), true) : null,
            'weekly' => $decisions,
        ];
    }

    private function answers(Carbon $from, Carbon $to)
    {
        return $this->playerAnswers->query()->whereBetween('player_answers.received_at', [$from, $to]);
    }
}
