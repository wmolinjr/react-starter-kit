<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wrapper around InitializeTenancyByDomain that skips if already initialized.
 *
 * In testing, TenantTestCase manually initializes tenants in setUp().
 * This middleware skips initialization if tenant is already active,
 * while still working normally in production.
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

        // In production or when not initialized, use standard domain-based initialization
        return app(InitializeTenancyByDomain::class)->handle($request, $next);
    }
}
