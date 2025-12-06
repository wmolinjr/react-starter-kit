<?php

namespace App\Http\Controllers\Central\Admin;

use App\Enums\CentralPermission;
use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use App\Services\Central\ImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles admin impersonation of tenant users.
 *
 * STANCL/TENANCY V4:
 * - Uses native tenancy()->impersonate() to create tokens
 * - Uses native UserImpersonation::stopImpersonating() to end session
 *
 * TENANT-ONLY ARCHITECTURE (Option C):
 * - Supports two impersonation scenarios:
 *   1. Admin Mode: Admin enters tenant without logging in as a user (user_id = null)
 *   2. As User: Admin impersonates a specific tenant user
 * - Users exist ONLY in tenant databases, queried via tenancy()->run()
 */
class ImpersonationController extends Controller implements HasMiddleware
{
    public function __construct(
        protected ImpersonationService $impersonationService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:'.CentralPermission::TENANTS_IMPERSONATE->value, only: ['index', 'adminMode', 'asUser', 'start']),
        ];
    }

    /**
     * List users from tenant database for impersonation selection.
     */
    public function index(Tenant $tenant): InertiaResponse|RedirectResponse
    {
        $admin = $this->impersonationService->getAuthenticatedAdmin();

        if (! $admin) {
            abort(401, __('You must be logged in as an administrator.'));
        }

        if (! $this->impersonationService->canAccessTenant($admin, $tenant)) {
            abort(403, __('You do not have permission to access this tenant.'));
        }

        $users = $this->impersonationService->getTenantUsers($tenant);

        return Inertia::render('central/admin/tenants/impersonate', [
            'tenant' => $this->impersonationService->formatTenantForDisplay($tenant),
            'users' => $users,
        ]);
    }

    /**
     * Impersonate tenant in Admin Mode (without specific user).
     */
    public function adminMode(Request $request, Tenant $tenant): Response
    {
        $admin = $this->impersonationService->getAuthenticatedAdmin();

        if (! $admin) {
            abort(401, __('You must be logged in as an administrator.'));
        }

        if (! $this->impersonationService->canAccessTenant($admin, $tenant)) {
            abort(403, __('You do not have permission to access this tenant.'));
        }

        $token = $this->impersonationService->createAdminModeToken($tenant);
        $url = $this->impersonationService->buildImpersonationUrl($tenant, $token);

        return Inertia::location($url);
    }

    /**
     * Impersonate a specific user from the tenant.
     */
    public function asUser(Request $request, Tenant $tenant, string $userId): Response
    {
        $admin = $this->impersonationService->getAuthenticatedAdmin();

        if (! $admin) {
            abort(401, __('You must be logged in as an administrator.'));
        }

        if (! $this->impersonationService->canAccessTenant($admin, $tenant)) {
            abort(403, __('You do not have permission to access this tenant.'));
        }

        try {
            $token = $this->impersonationService->createUserImpersonationToken($tenant, $userId);
            $url = $this->impersonationService->buildImpersonationUrl($tenant, $token);

            return Inertia::location($url);
        } catch (\InvalidArgumentException $e) {
            abort(404, $e->getMessage());
        }
    }

    /**
     * Legacy method: Start impersonation of tenant user via token.
     *
     * @deprecated Use asUser() or adminMode() instead
     */
    public function start(Tenant $tenant, ?User $user = null): RedirectResponse|Response
    {
        // If no user specified, redirect to user selection page
        if (! $user) {
            return redirect()->route('central.admin.tenants.impersonate.index', $tenant);
        }

        // Legacy: Validate user belongs to tenant via pivot table
        // This path is kept for backward compatibility during migration
        if (method_exists($user, 'tenants') && ! $user->tenants()->where('tenant_id', $tenant->id)->exists()) {
            abort(403, 'User does not belong to this tenant.');
        }

        try {
            $token = $this->impersonationService->createUserImpersonationToken(
                $tenant,
                (string) $user->id,
                '/admin'
            );
            $url = $this->impersonationService->buildImpersonationUrl($tenant, $token);

            return Inertia::location($url);
        } catch (\InvalidArgumentException $e) {
            abort(404, $e->getMessage());
        }
    }

    /**
     * Stop impersonation and return to admin dashboard.
     */
    public function stop(): RedirectResponse
    {
        if (! $this->impersonationService->isImpersonating()) {
            return redirect()->route('central.admin.dashboard');
        }

        $this->impersonationService->stopImpersonation();

        return redirect()->route('central.admin.dashboard')
            ->with('success', __('flash.impersonation.stopped'));
    }
}
