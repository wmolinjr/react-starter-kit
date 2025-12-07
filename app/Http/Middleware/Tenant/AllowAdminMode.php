<?php

namespace App\Http\Middleware\Tenant;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Features\UserImpersonation;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that allows access in "Admin Mode" without a logged-in user.
 *
 * Admin Mode is used when a central admin is inspecting a tenant without
 * impersonating a specific user. The admin has view-only access by default.
 *
 * TENANT-ONLY ARCHITECTURE (Option C):
 * - When admin enters tenant via adminMode(), no user is logged in
 * - Session has 'tenancy_admin_mode' = true
 * - This middleware allows access to protected routes
 *
 * Session flags:
 * - 'tenancy_impersonating': Set by both Admin Mode and User impersonation
 * - 'tenancy_admin_mode': Set ONLY by Admin Mode (no specific user)
 */
class AllowAdminMode
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // If in Admin Mode (impersonation without user), allow access
        if (session('tenancy_admin_mode')) {
            return $next($request);
        }

        // If impersonating as a specific user, let normal auth handle it
        if (UserImpersonation::isImpersonating()) {
            return $next($request);
        }

        // Normal authentication required
        if (! $request->user()) {
            return redirect()->route('tenant.auth.login');
        }

        return $next($request);
    }
}
