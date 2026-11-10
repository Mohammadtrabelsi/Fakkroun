<?php

use App\Http\Middleware\EnforceMaintenanceMode;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureBackofficeAccess;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsRegistered;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackVisit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            EnsureAccountIsActive::class,
            SetLocale::class,
            TrackVisit::class,
            EnforceMaintenanceMode::class,
        ]);

        $middleware->api(append: [
            EnforceMaintenanceMode::class,
        ]);

        // Guests hitting the back office go to the staff portal; everyone
        // else to the player sign-in in the front office.
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('admin', 'admin/*') ? route('admin.login') : route('login'),
        );

        // Signed-in users opening /login or /register go to their dashboard.
        // Guest players (nickname only) are signed out instead, so they can
        // sign in or create an account on the page they asked for.
        $middleware->redirectUsersTo(function (Request $request) {
            if (! $request->user()?->isGuest()) {
                return route('dashboard');
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return $request->fullUrl();
        });

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'backoffice' => EnsureBackofficeAccess::class,
            'registered' => EnsureUserIsRegistered::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
