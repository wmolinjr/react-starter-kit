<?php

declare(strict_types=1);

namespace App\Http\Middleware\Central;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirect Fortify auth routes to admin login when on central domain.
 *
 * This middleware intercepts Fortify routes (/login, /register) on central
 * domains and redirects them to the appropriate central admin routes.
 *
 * Why:
 * - Fortify is configured for tenant guard only
 * - Central domain has no tenant users in database
 * - Attempting /login on central would authenticate against empty table
 * - Better UX: redirect to proper admin login
 */
class RedirectFortifyOnCentral
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if tenancy is initialized (means we're in tenant context)
        // This handles test scenarios where tenancy()->initialize() is called
        // but HTTP requests still come from localhost
        if (tenancy()->initialized) {
            return $next($request);
        }

        // Only intercept on central domains
        if (! $this->isCentralDomain($request)) {
            return $next($request);
        }

        // Check if already authenticated as central admin
        if (auth('central')->check()) {
            return redirect()->route('central.admin.dashboard');
        }

        // Get the current route name
        $routeName = $request->route()?->getName();

        // Redirect Fortify login/register routes
        if (in_array($routeName, ['login', 'register'])) {
            return redirect()->route('central.admin.auth.login');
        }

        return $next($request);
    }

    /**
     * Check if request is on a central domain.
     */
    protected function isCentralDomain(Request $request): bool
    {
        $centralDomains = config('tenancy.identification.central_domains', []);

        return in_array($request->getHost(), $centralDomains);
    }
}
