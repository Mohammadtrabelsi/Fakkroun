<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps nickname-only guest players (see GuestPlayers) out of pages that need
 * a real account, such as creating a room. The guest session is ended and the
 * visitor is sent to the player sign-in, then back to the page they wanted.
 */
class EnsureUserIsRegistered
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isGuest()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(403);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('url.intended', $request->fullUrl());

        return redirect()->route('login');
    }
}
