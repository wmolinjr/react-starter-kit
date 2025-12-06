<?php

namespace App\Http\Middleware\Tenant;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Features\UserImpersonation;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unified tenant access middleware that handles both:
 * 1. User authorization (role check for non-impersonating users)
 * 2. Impersonation restrictions (blocked routes for impersonating users)
 *
 * NOTE: This middleware is REQUIRED - Stancl/Tenancy handles data isolation,
 * not user authorization or impersonation restrictions.
 *
 * Super Admins must use impersonation to access tenants (security best practice).
 */
class VerifyTenantAccess
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Ensure tenancy is initialized
        if (! tenancy()->initialized) {
            abort(403, __('Tenant not initialized.'));
        }

        // OPTION C: Admin Mode - no user, but session flag is set
        if (session('tenancy_admin_mode')) {
            return $this->handleAdminMode($request, $next);
        }

        // Let 'admin.mode' middleware handle unauthenticated users
        if (! $request->user()) {
            return $next($request);
        }

        // Branch: Impersonating vs Regular user
        if (UserImpersonation::isImpersonating()) {
            return $this->handleImpersonation($request, $next);
        }

        return $this->handleRegularUser($request, $next);
    }

    /**
     * Handle Admin Mode - central admin viewing tenant without user identity.
     *
     * OPTION C ARCHITECTURE:
     * Admin Mode allows viewing but blocks destructive actions.
     */
    protected function handleAdminMode(Request $request, Closure $next): Response
    {
        $blockedRoutes = config('tenancy.impersonation.blocked_routes', []);

        if (! empty($blockedRoutes) && $request->routeIs(...$blockedRoutes)) {
            abort(403, __('This action is not allowed in Admin Mode.'));
        }

        return $next($request);
    }

    /**
     * Handle impersonating users - check for blocked routes.
     */
    protected function handleImpersonation(Request $request, Closure $next): Response
    {
        $blockedRoutes = config('tenancy.impersonation.blocked_routes', []);

        if (! empty($blockedRoutes) && $request->routeIs(...$blockedRoutes)) {
            abort(403, __('This action is not allowed during impersonation.'));
        }

        return $next($request);
    }

    /**
     * Handle regular users - verify they have at least one role in this tenant.
     */
    protected function handleRegularUser(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Check user has at least one role in this tenant
        // (SpatiePermissionsBootstrapper handles database context)
        if ($user->getRoleNames()->isEmpty()) {
            abort(403, __('You do not have access to this tenant.'));
        }

        return $next($request);
    }
}
