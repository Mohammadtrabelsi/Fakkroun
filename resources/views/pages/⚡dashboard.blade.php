<?php

use App\Models\Post;
use App\Services\CategoryService;
use App\Services\PostService;
use App\Services\UserService;
use App\Services\VisitService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

new #[Layout('layouts::app')] #[Title('My space')] class extends Component
{
    #[Computed]
    public function isAdmin(): bool
    {
        return auth()->user()->isAdmin();
    }

    /**
     * Personal stats, shown to every user.
     */
    #[Computed]
    public function myStats(): array
    {
        $user = auth()->user();

        $posts = Post::query()
            ->where('user_id', $user->id)
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as published', [Post::STATUS_PUBLISHED])
            ->toBase()
            ->first();

        return [
            'posts' => (int) ($posts->total ?? 0),
            'published' => (int) ($posts->published ?? 0),
            'drafts' => (int) ($posts->total ?? 0) - (int) ($posts->published ?? 0),
            'actionsThisWeek' => Activity::query()
                ->where('causer_type', $user->getMorphClass())
                ->where('causer_id', $user->id)
                ->where('created_at', '>=', now()->subWeek())
                ->count(),
            'daysAsMember' => (int) $user->created_at->diffInDays(now()),
        ];
    }

    /**
     * Site-wide stats, admins only.
     */
    #[Computed]
    public function siteStats(): array
    {
        return [
            'users' => app(UserService::class)->stats(),
            'posts' => app(PostService::class)->stats(),
            'categories' => app(CategoryService::class)->stats(),
            'visits' => app(VisitService::class)->stats(),
            'activity' => [
                'today' => Activity::where('created_at', '>=', now()->startOfDay())->count(),
                'week' => Activity::where('created_at', '>=', now()->subWeek())->count(),
            ],
        ];
    }

    #[Computed]
    public function dailyVisits(): array
    {
        return app(VisitService::class)->daily(14);
    }

    #[Computed]
    public function topPages(): array
    {
        return app(VisitService::class)->topPages(5);
    }

    #[Computed]
    public function recentActivity()
    {
        return Activity::query()->with('causer')->latest()->limit(6)->get();
    }
};
?>

<div>
    <div class="ms-mb-24">
        <div class="card-kicker">{{ __('app.my_space') }}</div>
        <h2>{{ __('dashboard.welcome', ['name' => Str::of(auth()->user()->name)->before(' ')]) }}</h2>
        <p class="text-muted ms-fs-14-m0">
            {{ __('dashboard.logged_in_as', ['email' => auth()->user()->email]) }}
            @if ($this->isAdmin)
                {{ __('dashboard.admin_note') }}
            @endif
        </p>

        @if ($this->isAdmin)
            <a href="{{ route('admin.dashboard') }}" wire:navigate class="btn btn-primary ms-mt-18">
                {{ __('dashboard.admin_panel') }}
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"></path></svg>
            </a>
        @endif
    </div>

    @can('play games')
        <div class="card ms-stack-16 ms-mb-24">
            <div>
                <div class="card-kicker">{{ __('fakkroun.tagline') }}</div>
                <h3 class="ms-fs-18">{{ __('play.dashboard.title') }}</h3>
                <p class="text-muted ms-note">{{ __('play.dashboard.description') }}</p>
            </div>
            <div class="ms-row-10 ms-wrap">
                <a href="{{ route('rooms.create') }}" wire:navigate class="btn btn-primary">{{ __('play.nav.new_room') }}</a>
                <a href="{{ route('rooms.join') }}" wire:navigate class="btn">{{ __('play.nav.join_room') }}</a>
                @can('submit questions')
                    <a href="{{ route('questions.submit') }}" wire:navigate class="btn">{{ __('questions.submit.title') }}</a>
                @endcan
            </div>
            @php
                $playerStats = app(\App\Domain\Gameplay\LeaderboardService::class)->stats(auth()->user());
                $achievements = app(\App\Domain\Gameplay\AchievementService::class)->for(auth()->user());
                $recent = \App\Domain\Gameplay\Models\GameResult::query()->where('user_id', auth()->id())->with('game.room:id,ulid,name')->latest('id')->limit(5)->get();
            @endphp
            <div class="ms-row-10 ms-wrap">
                @foreach (['games', 'wins', 'best', 'correct_rate'] as $key)
                    <div class="card ms-flex-1" style="min-width: 140px;">
                        <div class="card-kicker">{{ __('play.profile.'.$key) }}</div>
                        <div class="stat-value">{{ $key === 'correct_rate' ? $playerStats[$key].' %' : number_format($playerStats[$key]) }}</div>
                    </div>
                @endforeach
            </div>
            <div>
                <div class="ds-label">{{ __('progress.achievements_title') }}</div>
                @if ($achievements->isEmpty())
                    <p class="text-muted ms-fs-13">{{ __('progress.no_achievements') }}</p>
                @else
                    <div class="ms-row-10 ms-wrap">
                        @foreach ($achievements as $achievement)
                            <span class="tag tag-accent" title="{{ __('progress.achievements.'.$achievement->key.'.description') }}">{{ __('progress.achievements.'.$achievement->key.'.name') }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
            @if ($recent->isNotEmpty())
                <div>
                    <div class="ds-label">{{ __('play.dashboard.recent') }}</div>
                    <ul class="ms-stack-16 ms-fs-13">
                        @foreach ($recent as $result)
                            <li>@if ($result->game?->room && $result->game->status === \App\Domain\Gameplay\Models\Game::COMPLETED)<a href="{{ route('rooms.results', $result->game->room) }}" wire:navigate>{{ $result->game->room->name }}</a>@else{{ $result->game?->room?->name }}@endif · #{{ $result->rank }} · {{ __('play.dashboard.points', ['points' => $result->score]) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div class="ms-row-10 ms-wrap ms-fs-13">
                <a href="{{ route('rooms.history') }}" wire:navigate>{{ __('progress.history.title') }} &rarr;</a>
                <a href="{{ route('players.show', auth()->user()) }}" wire:navigate>{{ __('progress.my_profile') }} &rarr;</a>
                <a href="{{ route('leaderboard') }}" wire:navigate>{{ __('play.leaderboard.title') }} &rarr;</a>
            </div>
        </div>
    @endcan

    {{-- Personal stats --}}
    <h3 class="dash-section">{{ __('dashboard.your_activity') }}</h3>
    <div class="stat-cards">
        @foreach ([
            [__('dashboard.my_posts'), $this->myStats['posts'], __('dashboard.published_drafts', ['published' => $this->myStats['published'], 'drafts' => $this->myStats['drafts']])],
            [__('dashboard.actions_this_week'), $this->myStats['actionsThisWeek'], __('dashboard.actions_hint')],
            [__('dashboard.days_as_member'), $this->myStats['daysAsMember'], __('dashboard.since', ['date' => auth()->user()->created_at->format('M j, Y')])],
        ] as [$label, $value, $hint])
            <div class="card">
                <div class="card-kicker">{{ $label }}</div>
                <div class="stat-value">{{ number_format($value) }}</div>
                <div class="text-muted ms-fs-12">{{ $hint }}</div>
            </div>
        @endforeach
    </div>

    @if ($this->isAdmin)
        @php($s = $this->siteStats)

        {{-- Visits --}}
        <h3 class="dash-section">{{ __('dashboard.visits') }}</h3>
        <div class="stat-cards">
            @foreach ([
                [__('dashboard.visits_today'), $s['visits']['today'], __('dashboard.unique_visitors', ['count' => number_format($s['visits']['uniqueToday'])])],
                [__('dashboard.visits_week'), $s['visits']['week'], __('dashboard.unique_visitors', ['count' => number_format($s['visits']['uniqueWeek'])])],
                [__('dashboard.visits_month'), $s['visits']['month'], __('dashboard.last_30_days')],
                [__('dashboard.visits_total'), $s['visits']['total'], __('dashboard.all_time')],
            ] as [$label, $value, $hint])
                <div class="card">
                    <div class="card-kicker">{{ $label }}</div>
                    <div class="stat-value">{{ number_format($value) }}</div>
                    <div class="text-muted ms-fs-12">{{ $hint }}</div>
                </div>
            @endforeach
        </div>

        <div class="dash-cards ms-mt-16">
            <div class="card">
                <div class="card-kicker">{{ __('dashboard.visits_per_day') }}</div>
                @php($max = max(1, max(array_column($this->dailyVisits, 'count'))))
                <div class="dash-chart" role="img" aria-label="{{ __('dashboard.visits_per_day') }}">
                    @foreach ($this->dailyVisits as $day)
                        <div class="dash-bar-col" tabindex="0">
                            <div class="dash-bar" style="height: {{ $day['count'] ? max(2, round($day['count'] / $max * 100)) : 0 }}%"></div>
                            <span class="dash-tip">{{ $day['date']->format('M j') }} · {{ number_format($day['count']) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="dash-axis text-muted ms-fs-12">
                    <span>{{ $this->dailyVisits[0]['date']->format('M j') }}</span>
                    <span>{{ __('dashboard.peak', ['count' => number_format($max)]) }}</span>
                    <span>{{ __('dashboard.today') }}</span>
                </div>
            </div>

            <div class="card">
                <div class="card-kicker">{{ __('dashboard.top_pages') }}</div>
                @forelse ($this->topPages as $page)
                    <div class="dash-row">
                        <span class="ms-minw-0 dash-ellipsis">{{ $page['path'] }}</span>
                        <span class="ms-fw-500">{{ number_format($page['count']) }}</span>
                    </div>
                @empty
                    <p class="text-muted ms-note">{{ __('dashboard.no_visits') }}</p>
                @endforelse
            </div>
        </div>

        {{-- Content & users --}}
        <h3 class="dash-section">{{ __('dashboard.overview') }}</h3>
        <div class="stat-cards">
            @foreach ([
                [__('dashboard.users'), $s['users']['total'], __('dashboard.users_hint', ['admins' => $s['users']['admins'], 'new' => $s['users']['newThisWeek']]), route('admin.dashboard')],
                [__('dashboard.posts'), $s['posts']['total'], __('dashboard.published_drafts', ['published' => $s['posts']['published'], 'drafts' => $s['posts']['drafts']]), route('admin.posts.index')],
                [__('dashboard.categories'), $s['categories']['total'], __('dashboard.active_inactive', ['active' => $s['categories']['active'], 'inactive' => $s['categories']['inactive']]), route('admin.categories.index')],
                [__('dashboard.activity'), $s['activity']['week'], __('dashboard.activity_hint', ['today' => $s['activity']['today']]), route('admin.activity-log')],
            ] as [$label, $value, $hint, $href])
                <a href="{{ $href }}" wire:navigate class="card dash-link">
                    <div class="card-kicker">{{ $label }}</div>
                    <div class="stat-value">{{ number_format($value) }}</div>
                    <div class="text-muted ms-fs-12">{{ $hint }}</div>
                </a>
            @endforeach
        </div>

        <div class="card ms-mt-16">
            <div class="card-kicker">{{ __('dashboard.recent_activity') }}</div>
            @forelse ($this->recentActivity as $activity)
                <div class="dash-row">
                    <span class="ms-minw-0 dash-ellipsis">
                        <span class="ms-fw-500">{{ $activity->causer?->name ?? 'System' }}</span>
                        <span class="text-muted">{{ $activity->description }}</span>
                        @if ($activity->subject_type)
                            <span class="text-muted">— {{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}</span>
                        @endif
                    </span>
                    <span class="text-muted ms-fs-12 ms-nowrap">{{ $activity->created_at->diffForHumans() }}</span>
                </div>
            @empty
                <p class="text-muted ms-note">{{ __('dashboard.no_activity') }}</p>
            @endforelse
        </div>
    @endif

    {{-- Account --}}
    <h3 class="dash-section">{{ __('dashboard.account') }}</h3>
    <div class="dash-cards">
        <div class="card">
            <div class="card-kicker">{{ __('dashboard.account') }}</div>
            <dl class="ms-detail-list">
                <div class="ms-row-between-16">
                    <dt class="text-muted">{{ __('dashboard.name') }}</dt>
                    <dd class="ms-m0-strong">{{ auth()->user()->name }}</dd>
                </div>
                <div class="ms-row-between-16">
                    <dt class="text-muted">{{ __('dashboard.email') }}</dt>
                    <dd class="ms-m0-strong-ellipsis">{{ auth()->user()->email }}</dd>
                </div>
                <div class="ms-row-between-16">
                    <dt class="text-muted">{{ __('dashboard.member_since') }}</dt>
                    <dd class="ms-m0-strong">{{ auth()->user()->created_at->format('M j, Y') }}</dd>
                </div>
            </dl>
        </div>

        <div class="card">
            <div class="card-kicker">{{ __('dashboard.role') }}</div>
            <div class="ms-my-12">
                <span class="tag {{ $this->isAdmin ? 'tag-accent' : 'tag-neutral' }}">
                    {{ $this->isAdmin ? __('dashboard.administrator') : __('dashboard.member') }}
                </span>
            </div>
            <p class="text-muted ms-note">
                @if ($this->isAdmin)
                    {{ __('dashboard.admin_permissions_note') }}
                @else
                    {{ __('dashboard.member_note') }}
                @endif
            </p>
        </div>
    </div>

    <style>
        .dash-cards { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        .dash-section { font-size: 13px; letter-spacing: 0.08em; text-transform: uppercase; color: rgba(var(--ink), 0.6); margin: 32px 0 12px; }
        .dash-link { display: block; color: inherit; text-decoration: none; }
        .dash-link:hover { background: rgba(var(--ink), 0.04); }
        .dash-row { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; padding: 8px 0; border-bottom: 1px solid rgba(var(--ink), 0.08); font-size: 14px; }
        .dash-row:last-child { border-bottom: 0; }
        .dash-ellipsis { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dash-chart { display: flex; align-items: flex-end; gap: 2px; height: 140px; margin-top: 12px; border-bottom: 1px solid var(--color-divider); }
        .dash-bar-col { position: relative; flex: 1; height: 100%; display: flex; align-items: flex-end; outline: none; }
        .dash-bar { width: 100%; background: var(--color-accent); border-radius: 4px 4px 0 0; }
        .dash-bar-col:hover .dash-bar, .dash-bar-col:focus .dash-bar { background: var(--color-accent-700); }
        .dash-tip { display: none; position: absolute; bottom: calc(100% + 4px); left: 50%; transform: translateX(-50%); white-space: nowrap; font-size: 12px; padding: 4px 8px; background: var(--color-text); color: var(--color-bg); z-index: 2; pointer-events: none; }
        .dash-bar-col:hover .dash-tip, .dash-bar-col:focus .dash-tip { display: block; }
        .dash-bar-col:first-child .dash-tip { left: 0; transform: none; }
        .dash-bar-col:last-child .dash-tip { left: auto; right: 0; transform: none; }
        .dash-axis { display: flex; justify-content: space-between; margin-top: 6px; }
        .ms-mt-16 { margin-top: 16px; }
        @media (max-width: 640px) { .dash-cards { grid-template-columns: 1fr; } }
    </style>
</div>
