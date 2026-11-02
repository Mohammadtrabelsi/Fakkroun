<?php

use App\Http\Controllers\ActivityLogExportController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\RobotsController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/robots.txt', RobotsController::class)->name('robots');

Route::view('/about', 'about')->name('about');
Route::view('/docs', 'docs')->name('docs');
Route::get('/blog', [BlogController::class, 'index'])->name('blog');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

Route::get('/language/{locale}', LocaleController::class)->name('locale.switch');

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

        Route::middleware('admin')->group(function () {
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
        });
    });

    Route::post('/logout', LogoutController::class)->name('logout');
});
