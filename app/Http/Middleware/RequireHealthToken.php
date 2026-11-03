<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the machine-readable /health.json endpoint. It answers 404 until
 * HEALTH_SECRET_TOKEN is configured, then requires that token as a bearer
 * token (or X-Health-Token header), compared in constant time. Detailed
 * results are otherwise only visible to administrators at /admin/health.
 */
class RequireHealthToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('health.secret_token');

        abort_if($expected === '', 404);

        $given = (string) ($request->bearerToken() ?? $request->header('X-Health-Token', ''));

        abort_unless($given !== '' && hash_equals($expected, $given), 401);

        return $next($request);
    }
}
