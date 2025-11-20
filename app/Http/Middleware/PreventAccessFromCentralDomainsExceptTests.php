<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wrapper around PreventAccessFromCentralDomains that skips in testing.
 *
 * In testing, we manually initialize tenants and don't need domain-based blocking.
 * This allows tests to work without complex HTTP_HOST manipulation.
 */
class PreventAccessFromCentralDomainsExceptTests
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip central domain check in testing environment
        if (app()->environment('testing')) {
            return $next($request);
        }

        // In production, delegate to Stancl's middleware
        return app(PreventAccessFromCentralDomains::class)->handle($request, $next);
    }
}
