<?php

namespace App\Domain\Analytics;

use App\Domain\Ai\Models\AiGenerationBatch;
use App\Domain\Gameplay\Models\Game;
use App\Domain\Gameplay\Models\GameResult;
use App\Domain\Gameplay\Models\PlayerAnswer;
use App\Domain\Gameplay\Models\Room;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\Models\QuestionReport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Platform and question analytics (spec §24). Every figure is computed from
 * stored, server-recorded events; nothing here is estimated or invented,
 * and an empty platform shows zeros.
 */
class PlatformAnalytics
{
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
        $games = Game::query()->whereBetween('started_at', [$from, $to]);
        $completed = (clone $games)->whereNotNull('ended_at')->where('status', 'completed');
        $answers = $this->answers($from, $to);
        $total = (clone $answers)->count();
        $resultGameIds = (clone $completed)->select('id');

        return [
            'rooms_created' => Room::query()->whereBetween('created_at', [$from, $to])->count(),
            'games_started' => (clone $games)->count(),
            'games_completed' => (clone $completed)->count(),
            'active_players' => GameResult::query()->whereIn('game_id', (clone $games)->select('id'))->distinct()->count('user_id'),
            'avg_players_per_game' => round((float) GameResult::query()->whereIn('game_id', $resultGameIds)->selectRaw('count(*) as players')->groupBy('game_id')->get()->avg('players'), 1),
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
        $counts = Game::query()->whereBetween('started_at', [$from, $to])
            ->selectRaw('date(started_at) as day, count(*) as total')
            ->groupBy(DB::raw('date(started_at)'))
            ->pluck('total', 'day');

        $days = [];

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $days[$day->toDateString()] = (int) ($counts[$day->toDateString()] ?? 0);
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

        $names = \App\Models\Category::query()->whereIn('id', $categories->pluck('id'))->get()->keyBy('id');

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

        $reported = QuestionReport::query()->whereBetween('created_at', [$from, $to])
            ->selectRaw('question_id as id, count(*) as reports')->groupBy('question_id')->orderByDesc('reports')->limit($limit)->get()
            ->map(fn ($row) => ['id' => (int) $row->id, 'reports' => (int) $row->reports]);

        $lists = [
            'hardest' => $ranked->sortBy('correct_rate')->take($limit)->values()->all(),
            'easiest' => $ranked->sortByDesc('correct_rate')->take($limit)->values()->all(),
            'most_played' => $stats->sortByDesc('answers')->take($limit)->values()->all(),
            'most_reported' => $reported->all(),
        ];

        $ids = collect($lists)->flatten(1)->pluck('id')->unique();
        $questions = Question::withTrashed()->with('translations:id,question_id,language,body')->whereIn('id', $ids)->get()->keyBy('id');

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
            'by_status' => Question::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all(),
            'by_source' => Question::query()->selectRaw('source, count(*) as total')->groupBy('source')->pluck('total', 'source')->all(),
            'open_reports' => QuestionReport::query()->where('status', 'open')->count(),
        ];
    }

    /**
     * AI generation usage and acceptance in the period.
     *
     * @return array<string, int|float|null>
     */
    public function ai(Carbon $from, Carbon $to): array
    {
        $batches = AiGenerationBatch::query()->whereBetween('created_at', [$from, $to]);
        $generated = (int) (clone $batches)->sum('generated_count');

        return [
            'batches' => (clone $batches)->where('kind', 'batch')->count(),
            'drafts' => (clone $batches)->where('kind', 'draft')->count(),
            'generated' => $generated,
            'accepted_rate' => $generated > 0 ? round(100 * (int) (clone $batches)->sum('accepted_count') / $generated, 1) : null,
            'published' => Question::query()->where('source', 'ai')->where('status', 'published')->whereBetween('created_at', [$from, $to])->count(),
            'estimated_cost' => round((float) (clone $batches)->sum('estimated_cost'), 2),
        ];
    }

    private function answers(Carbon $from, Carbon $to)
    {
        return PlayerAnswer::query()->whereBetween('player_answers.received_at', [$from, $to]);
    }
}
