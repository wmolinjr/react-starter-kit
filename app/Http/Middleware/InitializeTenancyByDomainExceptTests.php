<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wrapper around InitializeTenancyByDomain with smart initialization logic.
 *
 * Behavior:
 * 1. Skips if tenant is already initialized (for tests where setUp() initializes manually)
 * 2. Skips for central domains (localhost, admin domain, etc.)
 * 3. Initializes tenancy for tenant domains BEFORE StartSession middleware
 *
 * The early initialization (via bootstrap/app.php append) is CRITICAL for impersonation
 * to work correctly with Redis session scoping. Without it, sessions are created with
 * tenant prefix but read without prefix, causing authentication to fail.
 *
 * @see bootstrap/app.php Line 89 - Early middleware initialization
 * @see https://v4.tenancyforlaravel.com/version-4 - Early Identification Middleware
 */
class InitializeTenancyByDomainExceptTests
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip domain-based initialization if tenant is already initialized
        // This happens in tests where we manually initialize in setUp()
        if (tenancy()->initialized) {
            return $next($request);
        }

        // Skip for central domains (localhost, admin domain, etc.)
        // Central domains should not trigger tenant initialization
        $centralDomains = config('tenancy.central_domains', []);
        if (in_array($request->getHost(), $centralDomains)) {
            return $next($request);
        }

        // For tenant domains, use standard domain-based initialization
        // This runs BEFORE StartSession middleware to ensure correct Redis session prefixing
        return app(InitializeTenancyByDomain::class)->handle($request, $next);
    }
}
