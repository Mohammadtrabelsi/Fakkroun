<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the /admin area. Admins and moderators both hold
 * `access backoffice`; each screen inside is further gated by its own
 * `can:` permission, so a moderator only reaches the screens granted to them.
 */
class EnsureBackofficeAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->canAccessBackoffice()) {
            abort(403);
        }

        return $next($request);
    }
}
