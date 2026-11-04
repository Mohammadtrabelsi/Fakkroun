<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interface language, in order of precedence:
 *   1. the signed-in user's saved preference (users.locale),
 *   2. the locale picked in this session (guests, or users without a preference),
 *   3. config('app.locale').
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->preferredLocale()
            ?? $request->session()->get('locale', config('app.locale'));

        if (array_key_exists($locale, config('app.available_locales', []))) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
