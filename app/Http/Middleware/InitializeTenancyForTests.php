<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Symfony\Component\HttpFoundation\Response;

/**
 * Test-friendly tenancy initialization middleware.
 *
 * In testing environment: Skips domain-based initialization if tenant is already initialized manually
 * In production: Delegates to InitializeTenancyByDomain for standard domain-based identification
 */
class InitializeTenancyForTests
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // In testing environment, skip both tenant initialization and central domain check
        // if tenant is already manually initialized in the test setup
        if (app()->environment('testing') && tenancy()->initialized) {
            return $next($request);
        }

        // In production: check central domains first (prevent access from localhost, etc.)
        if (! app()->environment('testing') && in_array($request->getHost(), config('tenancy.central_domains'))) {
            abort(404);
        }

        // In production or if tenant not initialized, use standard domain-based initialization
        return app(InitializeTenancyByDomain::class)->handle($request, $next);
    }
}
