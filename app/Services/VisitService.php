<?php

namespace App\Services;

use App\Models\Visit;
use App\Services\Concerns\ManagesModel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VisitService
{
    /** @use ManagesModel<Visit> */
    use ManagesModel;

    /** Paths that are never counted as page visits. */
    protected array $ignoredPaths = [
        'admin', 'admin/*', 'livewire/*', 'api/*', 'up', 'language/*', 'logout',
    ];

    public function shouldTrack(Request $request, Response $response): bool
    {
        return $request->isMethod('GET')
            && $response->isSuccessful()
            && ! $request->expectsJson()
            && ! $request->is(...$this->ignoredPaths)
            && ! $this->isBot($request->userAgent());
    }

    public function record(Request $request): void
    {
        try {
            Visit::create([
                'path' => Str::limit('/'.ltrim($request->path(), '/'), 2048, ''),
                'visitor_hash' => $this->visitorHash($request),
                'user_id' => $request->user()?->id,
                'referrer' => Str::limit((string) $request->headers->get('referer'), 2048, '') ?: null,
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array{today: int, week: int, month: int, total: int, uniqueToday: int, uniqueWeek: int}
     */
    public function stats(): array
    {
        $counts = Visit::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when created_at >= ? then 1 else 0 end) as today', [now()->startOfDay()])
            ->selectRaw('sum(case when created_at >= ? then 1 else 0 end) as week', [now()->subWeek()])
            ->selectRaw('sum(case when created_at >= ? then 1 else 0 end) as month', [now()->subMonth()])
            ->toBase()
            ->first();

        return [
            'today' => (int) ($counts->today ?? 0),
            'week' => (int) ($counts->week ?? 0),
            'month' => (int) ($counts->month ?? 0),
            'total' => (int) ($counts->total ?? 0),
            'uniqueToday' => Visit::where('created_at', '>=', now()->startOfDay())->distinct()->count('visitor_hash'),
            'uniqueWeek' => Visit::where('created_at', '>=', now()->subWeek())->distinct()->count('visitor_hash'),
        ];
    }

    /**
     * Visits per day for the last $days days (oldest first), zero-filled.
     *
     * @return array<int, array{date: Carbon, count: int}>
     */
    public function daily(int $days = 14): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $counts = Visit::query()
            ->where('created_at', '>=', $start)
            ->selectRaw('date(created_at) as day, count(*) as total')
            ->groupBy(DB::raw('date(created_at)'))
            ->pluck('total', 'day');

        return collect(range(0, $days - 1))
            ->map(function (int $offset) use ($start, $counts) {
                $date = $start->copy()->addDays($offset);

                return ['date' => $date, 'count' => (int) ($counts[$date->toDateString()] ?? 0)];
            })
            ->all();
    }

    /**
     * @return array<int, array{path: string, count: int}>
     */
    public function topPages(int $limit = 5, int $days = 30): array
    {
        return Visit::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('path, count(*) as total')
            ->groupBy('path')
            ->orderByDesc('total')
            ->limit($limit)
            ->toBase()
            ->get()
            ->map(fn ($row) => ['path' => $row->path, 'count' => (int) $row->total])
            ->all();
    }

    protected function visitorHash(Request $request): string
    {
        return hash('sha256', implode('|', [
            $request->ip(),
            $request->userAgent(),
            now()->toDateString(),
            config('app.key'),
        ]));
    }

    protected function isBot(?string $userAgent): bool
    {
        return $userAgent === null
            || (bool) preg_match('/bot|crawl|spider|slurp|curl|wget|python|headless|lighthouse/i', $userAgent);
    }

    protected function model(): string
    {
        return Visit::class;
    }
}
