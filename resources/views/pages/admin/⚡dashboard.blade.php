<?php

use App\Services\SystemHealthService;
use App\Services\UserService;
use App\Services\VisitService;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

new #[Layout('layouts::app')] #[Title('Admin Dashboard')] class extends Component
{
    #[Computed]
    public function stats(): array
    {
        return app(UserService::class)->stats();
    }

    #[Computed]
    public function visits(): array
    {
        return app(VisitService::class)->stats();
    }

    #[Computed]
    public function signups(): array
    {
        return app(UserService::class)->dailySignups(14);
    }

    #[Computed]
    public function newThisMonth(): int
    {
        return app(UserService::class)->newThisMonth();
    }

    #[Computed]
    public function roleSlices(): array
    {
        $rows = app(UserService::class)->roleDistribution();
        $total = max(1, array_sum(array_column($rows, 'count')));
        $palette = ['var(--color-navy)', 'var(--color-accent)', 'var(--color-teal-light)', 'var(--color-orange)', 'rgba(var(--ink), .35)'];
        $offset = 0.0;

        return collect($rows)->values()->map(function (array $row, int $i) use ($total, $palette, &$offset) {
            $share = $row['count'] / $total * 100;
            $slice = [
                'label' => $row['role'] === null ? __('dashboard.no_role') : Str::headline($row['role']),
                'count' => $row['count'],
                'percent' => (int) round($share),
                'length' => round($share, 3),
                'offset' => round(-$offset, 3),
                'color' => $palette[$i % count($palette)],
            ];
            $offset += $share;

            return $slice;
        })->all();
    }

    #[Computed]
    public function recentActivity()
    {
        if (! auth()->user()->can('view activity log')) {
            return collect();
        }

        return app(\App\Services\ActivityLogService::class)->query()->with('causer')->latest()->latest('id')->limit(4)->get();
    }

    #[Computed]
    public function health(): array
    {
        return app(SystemHealthService::class)->checks();
    }

    public function render()
    {
        return $this->view();
    }
};
?>

<div>
    <div class="ms-page-head">
        <div>
            <h2 class="ms-mb-0">{{ __('dashboard.overview_title') }}</h2>
            <p class="text-muted ms-overview-sub">{{ __('dashboard.overview_subtitle') }}</p>
        </div>
        <div class="ms-row-10 ms-wrap">
            @can('manage users')
                <a href="{{ route('admin.users.index') }}" wire:navigate class="btn">{{ __('app.users') }}</a>
                <a href="{{ route('admin.users.create') }}" wire:navigate class="btn btn-primary">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="9" cy="8" r="3.5"></circle><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6M19 8v6M16 11h6"></path></svg>
                    New user
                    @if ($this->stats['newToday'] > 0)
                        <span class="btn-badge" title="{{ $this->stats['newToday'] }} new today">{{ $this->stats['newToday'] }}</span>
                    @endif
                </a>
            @endcan
            @can('view activity log')
                <a href="{{ route('admin.activity-log') }}" wire:navigate class="btn">{{ __('dashboard.view_activity_logs') }}</a>
            @endcan
        </div>
    </div>

    <div class="ov-grid">
        <section class="card ov-users">
            <h3 class="ov-title">{{ __('dashboard.users_overview') }}</h3>
            <div class="stat-value ov-big">{{ number_format($this->newThisMonth) }}</div>
            <div class="text-muted ms-fs-13">{{ __('dashboard.new_users_month') }}</div>
            @php($peak = max(1, max(array_column($this->signups, 'count'))))
            <div class="ov-bars" role="img" aria-label="{{ __('dashboard.signups_chart', ['days' => count($this->signups)]) }}">
                @foreach ($this->signups as $i => $day)
                    <span
                        class="ov-bar {{ $i % 3 === 1 ? 'is-accent' : '' }}"
                        style="--h: {{ max(4, round($day['count'] / $peak * 100)) }}%"
                        title="{{ $day['date']->format('M j') }}: {{ $day['count'] }}"
                    ></span>
                @endforeach
            </div>
        </section>

        <section class="card ov-activity">
            <h3 class="ov-title">{{ __('dashboard.activity_snippet') }}</h3>
            <ul class="ov-list">
                @forelse ($this->recentActivity as $activity)
                    <li class="ov-item" wire:key="act-{{ $activity->id }}">
                        <span class="ov-icon {{ $activity->causer ? 'is-accent' : '' }}" aria-hidden="true">
                            @if ($activity->causer)
                                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"></circle><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"></path></svg>
                            @else
                                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 12a9 9 0 1 0 3-6.7"></path><path d="M3 4v5h5"></path><path d="M12 8v4l3 2"></path></svg>
                            @endif
                        </span>
                        <span class="ms-minw-0 ms-flex-1">
                            <span class="ms-block ov-item-text">
                                <strong class="ms-fw-500">{{ $activity->causer?->name ?? 'System' }}</strong>
                                {{ $activity->description }}
                                @if ($activity->subject_type)
                                    <span class="text-muted">· {{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}</span>
                                @endif
                            </span>
                            <span class="ms-block text-muted ms-fs-12">{{ $activity->created_at?->diffForHumans() }}</span>
                        </span>
                    </li>
                @empty
                    <li class="text-muted ms-fs-13">{{ __('dashboard.no_activity') }}</li>
                @endforelse
            </ul>
        </section>

        <section class="card ov-roles">
            <h3 class="ov-title">{{ __('dashboard.roles_distribution') }}</h3>
            <div class="ov-donut-row">
                <svg class="ov-donut" viewBox="0 0 120 120" role="img" aria-label="{{ collect($this->roleSlices)->map(fn ($s) => $s['label'].' '.$s['percent'].'%')->implode(', ') }}">
                    <g transform="rotate(-90 60 60)" fill="none" stroke-width="30">
                        <circle cx="60" cy="60" r="40" stroke="rgba(var(--ink), .08)"></circle>
                        @foreach ($this->roleSlices as $slice)
                            <circle cx="60" cy="60" r="40" pathLength="100" stroke="{{ $slice['color'] }}" stroke-dasharray="{{ $slice['length'] }} {{ 100 - $slice['length'] }}" stroke-dashoffset="{{ $slice['offset'] }}"></circle>
                        @endforeach
                    </g>
                </svg>
                <ul class="ov-legend">
                    @foreach ($this->roleSlices as $slice)
                        <li>
                            <span class="ov-swatch" style="background: {{ $slice['color'] }}"></span>
                            <span class="ms-flex-1">{{ $slice['label'] }}</span>
                            <span class="text-muted">{{ $slice['percent'] }}%</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        <section class="card ov-health">
            <h3 class="ov-title">{{ __('dashboard.system_health') }}</h3>
            <ul class="ov-list">
                @foreach ($this->health as $check)
                    <li class="ov-health-row">
                        @if ($check['ok'])
                            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="var(--color-accent)"></circle><path d="M7 12.5l3.2 3L17 9" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                        @else
                            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><circle cx="12" cy="12" r="11" fill="var(--color-orange)"></circle><path d="M12 7v6M12 16.5v.5" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round"></path></svg>
                        @endif
                        <span class="ms-flex-1">
                            {{ __('dashboard.health_'.$check['key']) }}
                            @if ($check['detail'])
                                <span class="text-muted ms-fs-12">· {{ $check['detail'] }}</span>
                            @endif
                        </span>
                        <span class="{{ $check['ok'] ? 'ov-ok' : 'ov-bad' }}">{{ $check['ok'] ? __('dashboard.operational') : __('dashboard.degraded') }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>

    <div class="stat-cards">
        @foreach ([
            ['Total users', $this->stats['total']],
            ['Administrators', $this->stats['admins']],
            ['New this week', $this->stats['newThisWeek']],
            ['New today', $this->stats['newToday']],
            ['Trashed users', $this->stats['trashed']],
        ] as [$label, $value])
            <div class="card">
                <div class="card-kicker">{{ $label }}</div>
                <div class="stat-value">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="stat-cards">
        @foreach ([
            ['Visits today', $this->visits['today'], $this->visits['uniqueToday'].' unique'],
            ['Visits this week', $this->visits['week'], $this->visits['uniqueWeek'].' unique'],
            ['Visits (30 days)', $this->visits['month'], 'Last 30 days'],
            ['Visits all time', $this->visits['total'], 'All time'],
        ] as [$label, $value, $hint])
            <div class="card">
                <div class="card-kicker">{{ $label }}</div>
                <div class="stat-value">{{ number_format($value) }}</div>
                <div class="text-muted ms-fs-12">{{ $hint }}</div>
            </div>
        @endforeach
    </div>
</div>
