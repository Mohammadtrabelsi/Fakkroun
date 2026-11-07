<?php

use App\Domain\Advertising\AdvertisingStats;
use App\Domain\Analytics\PlatformAnalytics;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Platform and question analytics (spec §24). */
new #[Layout('layouts::app')] #[Title('Analytics')] class extends Component
{
    #[Url] public string $from = '';

    #[Url] public string $to = '';

    public function mount(): void
    {
        $this->authorize('view admin dashboard');
    }

    #[Computed]
    public function data(): array
    {
        $analytics = app(PlatformAnalytics::class);
        ['from' => $from, 'to' => $to] = $analytics->period($this->from ?: null, $this->to ?: null);

        return [
            'from' => $from,
            'to' => $to,
            'overview' => $analytics->overview($from, $to),
            'daily' => $analytics->dailyGames($from, $to),
            'breakdown' => $analytics->breakdown($from, $to),
            'questions' => $analytics->questions($from, $to),
            'bank' => $analytics->bank(),
            'ai' => $analytics->ai($from, $to),
            'fairPlay' => $analytics->fairPlay($from, $to),
            'calibration' => $analytics->calibration(),
            'moderation' => $analytics->moderation(),
            'ads' => auth()->user()->can('manage advertising')
                ? app(AdvertisingStats::class)->platform(['from' => $from->toDateString(), 'to' => $to->toDateString()])
                : null,
        ];
    }
};
?>

@php
    $d = $this->data;
    $o = $d['overview'];
    $fmt = fn ($v, $suffix = '') => $v === null ? '—' : number_format($v, is_float($v) ? 1 : 0).$suffix;
    $peak = max(1, ...array_values($d['daily']));
    $currency = app(\App\Services\PlatformSettings::class)->get('advertising.currency');
@endphp
<div>
    <div class="ms-page-head">
        <div>
            <h2 class="ms-mb-0">{{ __('analytics.title') }}</h2>
            <p class="text-muted ms-note">{{ __('analytics.description', ['from' => $d['from']->isoFormat('ll'), 'to' => $d['to']->isoFormat('ll')]) }}</p>
        </div>
        <div class="ms-row-10 ms-wrap">
            <input type="date" wire:model.live="from" class="input" aria-label="{{ __('advertising.from') }}">
            <input type="date" wire:model.live="to" class="input" aria-label="{{ __('advertising.to') }}">
        </div>
    </div>

    <div class="stat-cards ms-mb-24">
        @foreach ([
            ['games_completed', $fmt($o['games_completed']), __('analytics.of_started', ['count' => number_format($o['games_started'])])],
            ['active_players', $fmt($o['active_players']), __('analytics.avg_players', ['count' => $fmt($o['avg_players_per_game'])])],
            ['answers', $fmt($o['answers']), __('analytics.duplicates', ['count' => number_format($o['duplicate_exclusions'])])],
            ['correct_rate', $fmt($o['correct_rate'], ' %'), __('analytics.avg_response', ['seconds' => $fmt($o['avg_response_seconds'])])],
            ['rooms_created', $fmt($o['rooms_created']), ''],
        ] as [$key, $value, $hint])
            <div class="card"><div class="card-kicker">{{ __('analytics.metrics.'.$key) }}</div><div class="stat-value">{{ $value }}</div><div class="text-muted ms-fs-12">{{ $hint }}</div></div>
        @endforeach
    </div>

    <div class="card ms-mb-24">
        <div class="card-kicker">{{ __('analytics.games_per_day') }}</div>
        <div role="img" aria-label="{{ __('analytics.games_per_day') }}" style="display: flex; align-items: flex-end; gap: 2px; height: 120px; margin-top: 12px;">
            @foreach ($d['daily'] as $day => $count)
                <div title="{{ \Illuminate\Support\Carbon::parse($day)->isoFormat('ll') }}: {{ $count }}" style="flex: 1; min-width: 2px; height: {{ max(2, round(100 * $count / $peak)) }}%; background: {{ $count ? 'var(--color-accent, currentColor)' : 'var(--color-border, #ddd)' }}; border-radius: 2px 2px 0 0;"></div>
            @endforeach
        </div>
        <div class="ms-row-between text-muted ms-fs-12"><span>{{ $d['from']->isoFormat('ll') }}</span><span>{{ __('analytics.peak', ['count' => max($d['daily'] ?: [0])]) }}</span><span>{{ $d['to']->isoFormat('ll') }}</span></div>
    </div>

    <div class="ms-row-10 ms-wrap ms-mb-24">
        @foreach (['categories' => 'name', 'languages' => 'language'] as $key => $labelKey)
            <div class="card ms-p-0 ms-flex-1" style="min-width: 280px;">
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>{{ __('analytics.by.'.$key) }}</th><th>{{ __('analytics.metrics.answers') }}</th><th>{{ __('analytics.metrics.correct_rate') }}</th></tr></thead>
                        <tbody>
                            @forelse ($d['breakdown'][$key] as $row)
                                <tr><td>{{ $key === 'languages' ? (config('app.available_locales.'.$row['language'].'.label') ?? strtoupper($row['language'])) : $row['name'] }}</td><td class="tabular-nums">{{ number_format($row['answers']) }}</td><td class="tabular-nums">{{ $row['correct_rate'] }} %</td></tr>
                            @empty
                                <tr><td colspan="3" class="text-muted ms-empty">{{ __('analytics.no_data') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>

    <h3 class="ms-fs-18 ms-mb-8">{{ __('analytics.question_title') }}</h3>
    <p class="text-muted ms-fs-13 ms-mb-16">{{ __('analytics.question_hint', ['min' => \App\Domain\Analytics\PlatformAnalytics::MIN_ANSWERS]) }}</p>
    <div class="ms-row-10 ms-wrap ms-mb-24">
        @foreach (['hardest', 'easiest', 'most_played', 'most_reported'] as $list)
            <div class="card ms-flex-1" style="min-width: 280px;">
                <div class="card-kicker">{{ __('analytics.lists.'.$list) }}</div>
                <ol class="ms-fs-13 ms-stack-8">
                    @forelse ($d['questions'][$list] as $row)
                        <li class="ms-row-between">
                            <span>@if ($row['uuid'])<a href="{{ route('admin.questions.edit', $row['uuid']) }}" wire:navigate>{{ \Illuminate\Support\Str::limit($row['body'], 70) }}</a>@else{{ \Illuminate\Support\Str::limit($row['body'], 70) }}@endif</span>
                            <b class="tabular-nums ms-nowrap">{{ $list === 'most_reported' ? trans_choice('analytics.reports', $row['reports'], ['count' => $row['reports']]) : ($list === 'most_played' ? number_format($row['answers']) : $row['correct_rate'].' %') }}</b>
                        </li>
                    @empty
                        <li class="text-muted">{{ __('analytics.no_data') }}</li>
                    @endforelse
                </ol>
            </div>
        @endforeach
    </div>

    <div class="ms-row-10 ms-wrap">
        <div class="card ms-flex-1" style="min-width: 260px;">
            <div class="card-kicker">{{ __('analytics.bank') }}</div>
            <ul class="ms-fs-13">
                @foreach ($d['bank']['by_status'] as $status => $total)
                    <li class="ms-row-between"><span>{{ __('questions.status.'.$status) }}</span><b>{{ number_format($total) }}</b></li>
                @endforeach
                <li class="ms-row-between"><span>{{ __('analytics.open_reports') }}</span><b>{{ number_format($d['bank']['open_reports']) }}</b></li>
            </ul>
            <div class="text-muted ms-fs-12 ms-mt-8">
                @foreach ($d['bank']['by_source'] as $source => $total){{ __('questions.source.'.$source) }}: {{ number_format($total) }}@if (! $loop->last) · @endif @endforeach
            </div>
        </div>

        <div class="card ms-flex-1" style="min-width: 260px;">
            <div class="card-kicker">{{ __('analytics.ai') }}</div>
            <ul class="ms-fs-13">
                <li class="ms-row-between"><span>{{ __('analytics.ai_batches') }}</span><b>{{ number_format($d['ai']['batches']) }} / {{ number_format($d['ai']['drafts']) }}</b></li>
                <li class="ms-row-between"><span>{{ __('analytics.ai_generated') }}</span><b>{{ number_format($d['ai']['generated']) }}</b></li>
                <li class="ms-row-between"><span>{{ __('analytics.ai_accepted') }}</span><b>{{ $fmt($d['ai']['accepted_rate'], ' %') }}</b></li>
                <li class="ms-row-between"><span>{{ __('analytics.ai_published') }}</span><b>{{ number_format($d['ai']['published']) }}</b></li>
                <li class="ms-row-between"><span>{{ __('analytics.ai_cost') }}</span><b>{{ number_format($d['ai']['estimated_cost'], 2) }} USD</b></li>
            </ul>
        </div>

        @if ($d['ads'])
            <div class="card ms-flex-1" style="min-width: 260px;">
                <div class="card-kicker">{{ __('advertising.admin_title') }}</div>
                <ul class="ms-fs-13">
                    <li class="ms-row-between"><span>{{ __('advertising.impressions') }}</span><b>{{ number_format($d['ads']['impressions']) }}</b></li>
                    <li class="ms-row-between"><span>{{ __('advertising.sponsored_deliveries') }}</span><b>{{ number_format($d['ads']['sponsored_deliveries']) }}</b></li>
                    <li class="ms-row-between"><span>{{ __('advertising.revenue_earned') }}</span><b>{{ number_format($d['ads']['spend_minor'] / 100, 2) }} {{ $currency }}</b></li>
                </ul>
                <a href="{{ route('admin.advertising') }}" wire:navigate class="ms-fs-13">{{ __('analytics.open_advertising') }} &rarr;</a>
            </div>
        @endif
    </div>

    <div class="ms-row-10 ms-wrap" style="margin-top: 16px;">
        <div class="card ms-flex-1" style="min-width: 260px;">
            <div class="card-kicker">{{ __('analytics.moderation.title') }}</div>
            <ul class="ms-fs-13">
                <li class="ms-row-between"><span>{{ __('analytics.moderation.waiting') }}</span><b>{{ number_format($d['moderation']['waiting']) }}</b></li>
                <li class="ms-row-between"><span>{{ __('analytics.moderation.oldest') }}</span><b>{{ $d['moderation']['oldest_hours'] === null ? '—' : __('analytics.moderation.hours', ['count' => $d['moderation']['oldest_hours']]) }}</b></li>
                @foreach ($d['moderation']['weekly'] as $week => $count)
                    <li class="ms-row-between"><span>{{ __('analytics.moderation.week_of', ['date' => \Illuminate\Support\Carbon::parse($week)->isoFormat('ll')]) }}</span><b>{{ number_format($count) }}</b></li>
                @endforeach
            </ul>
        </div>

        <div class="card ms-flex-1" style="min-width: 260px;">
            <div class="card-kicker">{{ __('analytics.fair_play.title') }}</div>
            <p class="ms-fs-13">{{ __('analytics.fair_play.flagged', ['count' => number_format($d['fairPlay']['flagged'])]) }}</p>
            <ul class="ms-fs-13">
                @forelse ($d['fairPlay']['players'] as $row)
                    <li class="ms-row-between"><a href="{{ route('players.show', $row['id']) }}" wire:navigate>{{ $row['name'] }}</a><b>{{ $row['flagged'] }} / {{ $row['answers'] }}</b></li>
                @empty
                    <li class="text-muted">{{ __('analytics.fair_play.none') }}</li>
                @endforelse
            </ul>
            <p class="text-muted ms-fs-12">{{ __('analytics.fair_play.note') }}</p>
        </div>

        <div class="card ms-flex-1" style="min-width: 280px;">
            <div class="card-kicker">{{ __('analytics.calibration.title') }}</div>
            <ol class="ms-fs-13 ms-stack-8">
                @forelse ($d['calibration'] as $row)
                    <li class="ms-row-between">
                        <a href="{{ route('admin.questions.edit', $row['uuid']) }}" wire:navigate>{{ \Illuminate\Support\Str::limit($row['body'], 60) }}</a>
                        <b class="ms-nowrap">{{ __('questions.difficulty.'.$row['difficulty']) }} · {{ $row['correct_rate'] }} %</b>
                    </li>
                @empty
                    <li class="text-muted">{{ __('analytics.calibration.none') }}</li>
                @endforelse
            </ol>
            <p class="text-muted ms-fs-12">{{ __('analytics.calibration.note') }}</p>
        </div>
    </div>
</div>
