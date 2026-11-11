<?php

namespace App\Http\Middleware;

use App\Services\UserService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of an account that was suspended while signed in, and
 * records when signed-in users were last active (at most once a minute).
 *
 * Bearer-token API clients are covered by UserService::suspend(), which
 * revokes every token.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isSuspended()) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson()) {
                abort(403, __('account.suspended'));
            }

            return redirect()->route('login')->with('status', __('account.suspended'));
        }

        if ($user !== null) {
            app(UserService::class)->touchLastSeen($user);
        }

        return $next($request);
    }
}
