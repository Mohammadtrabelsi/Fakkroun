<?php

use App\Http\Controllers\ActivityLogExportController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PostPdfController;
use App\Http\Controllers\QuestionExportController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\RoomInvitationController;
use App\Http\Middleware\RequireHealthToken;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Route;
use Spatie\Health\Http\Controllers\HealthCheckJsonResultsController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/robots.txt', RobotsController::class)->name('robots');

Route::view('/about', 'about')->name('about');
Route::view('/docs', 'docs')->name('docs');
Route::get('/blog', [BlogController::class, 'index'])->name('blog');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

// RSS feed(s) from config/feed.php ("feeds.blog" → /feed).
if (config('feed.enabled')) {
    Route::feeds();
}

// Machine-readable health results for external monitors; 404 unless
// HEALTH_SECRET_TOKEN is set (see RequireHealthToken).
Route::get('/health.json', HealthCheckJsonResultsController::class)
    ->middleware([RequireHealthToken::class, 'throttle:30,1'])
    ->name('health.json');

Route::get('/language/{locale}', LocaleController::class)->name('locale.switch');

Route::livewire('/leaderboard', 'pages::leaderboard')->name('leaderboard');

Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'pages::auth.login')->name('login');
    Route::livewire('/register', 'pages::auth.register')->name('register');
    Route::livewire('/forgot-password', 'pages::auth.forgot-password')->name('password.request');
    Route::livewire('/reset-password/{token}', 'pages::auth.reset-password')->name('password.reset');
    Route::livewire('/two-factor-challenge', 'pages::auth.two-factor-challenge')->name('two-factor.challenge');
});

Route::middleware('auth')->group(function () {
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
            Route::livewire('/play/join', 'pages::rooms.join')->name('rooms.join');
            Route::livewire('/rooms/{room}', 'pages::rooms.show')->name('rooms.show');
            Route::livewire('/rooms/{room}/results', 'pages::rooms.results')->name('rooms.results');
            // Shareable invitation link (spec §12.2); guests are sent to log in first.
            Route::get('/join/{code}', RoomInvitationController::class)
                ->middleware('throttle:30,1')
                ->where('code', '[A-Za-z0-9-]{4,12}')
                ->name('rooms.invitation');
        });

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

            Route::get('/admin/categories/export/{format}', [ExportController::class, 'categories'])
                ->middleware('can:manage categories')
                ->where('format', 'csv|xlsx')
                ->name('admin.categories.export');
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

            Route::get('/admin/posts/export/{format}', [ExportController::class, 'posts'])
                ->middleware('can:manage posts')
                ->where('format', 'csv|xlsx')
                ->name('admin.posts.export');
            Route::get('/admin/posts/{post}/pdf', PostPdfController::class)
                ->middleware('can:manage posts')
                ->name('admin.posts.pdf');
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
            Route::get('/admin/activity-log/export/{format}', ActivityLogExportController::class)
                ->middleware('can:view activity log')
                ->where('format', 'csv|json')
                ->name('admin.activity-log.export');
            Route::livewire('/admin/settings', 'pages::admin.settings')
                ->middleware('can:manage settings')
                ->name('admin.settings');

            Route::get('/admin/questions/export/{format}', QuestionExportController::class)
                ->middleware('can:manage questions')
                ->where('format', 'csv|xlsx|json')
                ->name('admin.questions.export');
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
            Route::livewire('/admin/moderators', 'pages::admin.moderators')
                ->middleware('can:manage moderators')
                ->name('admin.moderators');
        });
    });

    Route::post('/logout', LogoutController::class)->name('logout');
});
