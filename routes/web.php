<?php

use App\Http\Middleware\RequireHealthToken;
use App\Services\SitemapService;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Route;
use Spatie\Health\Http\Controllers\HealthCheckJsonResultsController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Served dynamically so the Sitemap line always uses the configured APP_URL
// instead of a domain hardcoded into a static file.
Route::get('/robots.txt', function (SitemapService $sitemap) {
    $lines = ['User-agent: *', 'Disallow:'];

    if ($url = $sitemap->publicUrl()) {
        $lines[] = '';
        $lines[] = "Sitemap: {$url}";
    }

    return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('robots');

Route::view('/about', 'about')->name('about');
Route::view('/terms', 'terms')->name('terms');
Route::view('/docs', 'docs')->name('docs');
Route::livewire('/blog', 'pages::blog.index')->name('blog');
Route::livewire('/blog/{slug}', 'pages::blog.show')->name('blog.show');

// RSS feed(s) from config/feed.php ("feeds.blog" → /feed).
if (config('feed.enabled')) {
    Route::feeds();
}

// Machine-readable health results for external monitors; 404 unless
// HEALTH_SECRET_TOKEN is set (see RequireHealthToken).
Route::get('/health.json', HealthCheckJsonResultsController::class)
    ->middleware([RequireHealthToken::class, 'throttle:30,1'])
    ->name('health.json');

Route::livewire('/language/{locale}', 'pages::locale')->name('locale.switch');

Route::livewire('/leaderboard', 'pages::leaderboard')->name('leaderboard');

// Playing needs no account: anyone can join with a code and a nickname (the
// join page signs visitors in as guest players, see GuestPlayers). Only
// creating a room, and every account page below, needs a real account.
Route::livewire('/play/join', 'pages::rooms.join')->name('rooms.join');
// Shareable invitation link (spec §12.2); visitors without a session pick a
// nickname on the join page first.
Route::livewire('/join/{code}', 'pages::rooms.invitation')
    ->middleware('throttle:30,1')
    ->where('code', '[A-Za-z0-9-]{4,12}')
    ->name('rooms.invitation');

Route::middleware(['auth', 'verified', 'can:play games'])->group(function () {
    Route::livewire('/rooms/{room}', 'pages::rooms.show')->name('rooms.show');
    Route::livewire('/rooms/{room}/results', 'pages::rooms.results')->name('rooms.results');
});

// "Learn more" on an ad: counts one click per delivery, then leaves the site.
Route::livewire('/ads/click/{token}', 'pages::ads.click')
    ->middleware(['auth', 'verified', 'throttle:60,1'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->name('ads.click');

Route::middleware('guest')->group(function () {
    // Player sign-in (front office).
    Route::livewire('/login', 'pages::auth.login')->name('login');
    // Staff sign-in (back office): admins, question writers and reviewers.
    Route::livewire('/admin/login', 'pages::auth.admin-login')->name('admin.login');
    Route::livewire('/register', 'pages::auth.register')->name('register');
    Route::livewire('/forgot-password', 'pages::auth.forgot-password')->name('password.request');
    Route::livewire('/reset-password/{token}', 'pages::auth.reset-password')->name('password.reset');
    Route::livewire('/two-factor-challenge', 'pages::auth.two-factor-challenge')->name('two-factor.challenge');
});

// Account pages: guest players are signed out and sent to the player sign-in.
Route::middleware(['auth', 'registered'])->group(function () {
    // Email verification
    Route::livewire('/verify-email', 'pages::auth.verify-email')->name('verification.notice');

    Route::get('/verify-email/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill();

        return redirect()->intended(route('dashboard').'?verified=1');
    })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

    // Account / profile management
    Route::livewire('/settings/profile', 'pages::settings.profile')->name('profile.edit');
    Route::livewire('/settings/two-factor', 'pages::settings.two-factor')->name('two-factor.settings');

    Route::middleware('verified')->group(function () {
        Route::livewire('/dashboard', 'pages::dashboard')->name('dashboard');

        Route::livewire('/notifications', 'pages::notifications')->name('notifications');
        Route::livewire('/players/{user}', 'pages::players.show')->name('players.show');

        Route::middleware('can:play games')->group(function () {
            Route::livewire('/play', 'pages::rooms.create')->name('rooms.create');
            Route::livewire('/play/history', 'pages::rooms.history')->name('rooms.history');
        });

        // Advertiser dashboard (spec §20); ownership is checked in the page.
        Route::livewire('/advertiser', 'pages::advertiser.dashboard')
            ->middleware('can:manage own campaigns')
            ->name('advertiser.dashboard');

        Route::middleware('can:submit questions')->group(function () {
            Route::livewire('/community/submit', 'pages::questions.submit')->name('questions.submit');
            Route::livewire('/questions/mine', 'pages::questions.mine')->name('questions.mine');
        });

        // Admins and moderators; each screen is further gated by its own permission.
        Route::middleware('backoffice')->group(function () {
            Route::livewire('/admin/dashboard', 'pages::admin.dashboard')
                ->middleware('can:view admin dashboard')
                ->name('admin.dashboard');

            Route::livewire('/admin/users', 'pages::admin.users.index')
                ->middleware('can:manage users')
                ->name('admin.users.index');
            Route::livewire('/admin/users/create', 'pages::admin.users.create')
                ->middleware('can:manage users')
                ->name('admin.users.create');
            Route::livewire('/admin/users/{user}/edit', 'pages::admin.users.edit')
                ->middleware('can:manage users')
                ->name('admin.users.edit');

            Route::livewire('/admin/categories/import', 'pages::admin.categories.import')
                ->middleware('can:manage categories')
                ->name('admin.categories.import');
            Route::livewire('/admin/categories', 'pages::admin.categories.index')
                ->middleware('can:manage categories')
                ->name('admin.categories.index');
            Route::livewire('/admin/categories/create', 'pages::admin.categories.create')
                ->middleware('can:manage categories')
                ->name('admin.categories.create');
            Route::livewire('/admin/categories/{category}/edit', 'pages::admin.categories.edit')
                ->middleware('can:manage categories')
                ->name('admin.categories.edit');

            Route::livewire('/admin/posts', 'pages::admin.posts.index')
                ->middleware('can:manage posts')
                ->name('admin.posts.index');
            Route::livewire('/admin/posts/create', 'pages::admin.posts.create')
                ->middleware('can:manage posts')
                ->name('admin.posts.create');
            Route::livewire('/admin/posts/{post}/edit', 'pages::admin.posts.edit')
                ->middleware('can:manage posts')
                ->name('admin.posts.edit');

            Route::livewire('/admin/tags', 'pages::admin.tags.index')
                ->middleware('can:manage tags')
                ->name('admin.tags.index');

            Route::livewire('/admin/health', 'pages::admin.health')
                ->middleware('can:view system health')
                ->name('admin.health');
            Route::livewire('/admin/backups', 'pages::admin.backups')
                ->middleware('can:manage backups')
                ->name('admin.backups');

            Route::livewire('/admin/notifications', 'pages::admin.notifications.index')
                ->name('admin.notifications.index');

            Route::livewire('/admin/activity-log', 'pages::admin.activity-log')
                ->middleware('can:view activity log')
                ->name('admin.activity-log');
            Route::livewire('/admin/settings', 'pages::admin.settings')
                ->middleware('can:manage settings')
                ->name('admin.settings');

            Route::livewire('/admin/questions/import', 'pages::admin.questions.import')
                ->middleware('can:manage questions')
                ->name('admin.questions.import');
            Route::livewire('/admin/questions', 'pages::admin.questions.index')
                ->middleware('can:manage questions')
                ->name('admin.questions.index');
            Route::livewire('/admin/questions/create', 'pages::admin.questions.editor')
                ->middleware('can:manage questions')
                ->name('admin.questions.create');
            Route::livewire('/admin/questions/{question}/edit', 'pages::admin.questions.editor')
                ->middleware('can:manage questions')
                ->name('admin.questions.edit');
            Route::livewire('/admin/ai', 'pages::admin.ai')
                ->middleware('can:manage questions')
                ->name('admin.ai');
            Route::livewire('/admin/moderation', 'pages::admin.moderation')
                ->middleware('can:moderate questions')
                ->name('admin.moderation');

            Route::livewire('/admin/game-settings', 'pages::admin.game-settings')
                ->middleware('can:manage platform settings')
                ->name('admin.game-settings');
            Route::livewire('/admin/partners', 'pages::admin.partners')
                ->middleware('can:manage advertising')
                ->name('admin.partners');
            Route::livewire('/admin/plans', 'pages::admin.plans')
                ->middleware('can:manage platform settings')
                ->name('admin.plans');
            Route::livewire('/admin/advertising', 'pages::admin.advertising')
                ->middleware('can:manage advertising')
                ->name('admin.advertising');
            Route::livewire('/admin/analytics', 'pages::admin.analytics')
                ->middleware('can:view admin dashboard')
                ->name('admin.analytics');
            Route::livewire('/admin/moderators', 'pages::admin.moderators')
                ->middleware('can:manage moderators')
                ->name('admin.moderators');
        });
    });
});
