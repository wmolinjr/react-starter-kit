<?php

namespace App\Services\Central;

use App\Models\Central\Tenant;
use App\Models\Central\User as CentralUser;
use App\Models\Tenant\User as TenantUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\ImpersonationToken;
use Stancl\Tenancy\Features\UserImpersonation;

/**
 * ImpersonationService
 *
 * Handles all business logic for admin impersonation of tenant users.
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
class ImpersonationService
{
    /**
     * Get all users from a tenant's database for impersonation selection.
     *
     * @return Collection<int, array{id: string, name: string, email: string, created_at: string|null, roles: array<string>}>
     */
    public function getTenantUsers(Tenant $tenant): Collection
    {
        return tenancy()->run($tenant, function () {
            return TenantUser::select('id', 'name', 'email', 'created_at')
                ->with('roles:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (TenantUser $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at?->toISOString(),
                    'roles' => $user->roles->pluck('name')->toArray(),
                ]);
        });
    }

    /**
     * Create an Admin Mode impersonation token.
     *
     * Admin Mode allows access to tenant without logging in as a specific user.
     * Token is created with user_id = null.
     *
     * @param  string  $redirectUrl  Default: '/admin/dashboard'
     */
    public function createAdminModeToken(Tenant $tenant, string $redirectUrl = '/admin/dashboard'): ImpersonationToken
    {
        // NOTE: We create the token manually because tenancy()->impersonate() requires a user_id
        return ImpersonationToken::create([
            'token' => Str::random(128),
            'tenant_id' => $tenant->getTenantKey(),
            'user_id' => null, // Admin Mode - no specific user
            'redirect_url' => $redirectUrl,
            'auth_guard' => null,
        ]);
    }

    /**
     * Create a User impersonation token.
     *
     * Creates a token to impersonate a specific user in the tenant.
     *
     * @throws \InvalidArgumentException If user doesn't exist in tenant
     */
    public function createUserImpersonationToken(
        Tenant $tenant,
        string $userId,
        string $redirectUrl = '/admin/dashboard'
    ): ImpersonationToken {
        // Verify user exists in tenant database
        $userExists = tenancy()->run($tenant, function () use ($userId) {
            return TenantUser::where('id', $userId)->exists();
        });

        if (! $userExists) {
            throw new \InvalidArgumentException(__('User not found in this tenant.'));
        }

        // Use native tenancy impersonate method
        return tenancy()->impersonate($tenant, $userId, $redirectUrl);
    }

    /**
     * Get the authenticated admin user.
     *
     * Supports both new 'central' guard and legacy 'tenant' guard with Super Admin role.
     */
    public function getAuthenticatedAdmin(): CentralUser|TenantUser|null
    {
        // Try central guard first (new architecture)
        $admin = auth('central')->user();
        if ($admin) {
            return $admin;
        }

        // Fallback to web guard (legacy)
        $user = auth()->user();
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('Super Admin')) {
            return $user;
        }

        return null;
    }

    /**
     * Check if admin can access the tenant.
     */
    public function canAccessTenant($admin, Tenant $tenant): bool
    {
        // New Admin model has canAccessTenant() method
        if (method_exists($admin, 'canAccessTenant')) {
            return $admin->canAccessTenant($tenant);
        }

        // Legacy: User with Super Admin role
        if (method_exists($admin, 'hasRole')) {
            return $admin->hasRole('Super Admin');
        }

        return false;
    }

    /**
     * Build the impersonation redirect URL for a tenant.
     *
     * @throws \InvalidArgumentException If tenant has no primary domain
     */
    public function buildImpersonationUrl(Tenant $tenant, ImpersonationToken $token): string
    {
        $domain = $tenant->primaryDomain()?->domain;

        if (! $domain) {
            throw new \InvalidArgumentException(__('Tenant does not have a primary domain configured.'));
        }

        $protocol = request()->secure() ? 'https' : 'http';

        return "{$protocol}://{$domain}/impersonate/{$token->token}";
    }

    /**
     * Check if currently impersonating.
     */
    public function isImpersonating(): bool
    {
        return UserImpersonation::isImpersonating();
    }

    /**
     * Stop impersonation.
     */
    public function stopImpersonation(): void
    {
        UserImpersonation::stopImpersonating();
    }

    /**
     * Format tenant data for impersonation page.
     *
     * @return array{id: string, name: string, slug: string, domain: string|null}
     */
    public function formatTenantForDisplay(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'domain' => $tenant->primaryDomain()?->domain,
        ];
    }
}
