<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdminKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.stampo.admin_key');
        $supplied = $request->bearerToken() ?: (string) $request->header('X-Admin-Key');
        abort_unless($expected !== '' && hash_equals($expected, $supplied), 401, 'Invalid admin API key.');

        return $next($request);
    }
}
