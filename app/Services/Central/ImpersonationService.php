<?php

namespace App\Services\Central;

use App\Models\Central\Tenant;
use App\Models\Central\User as CentralUser;
use App\Models\Tenant\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\ImpersonationToken;
use Stancl\Tenancy\Features\UserImpersonation;

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
            return User::select('id', 'name', 'email', 'created_at')
                ->with('roles:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
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
            return User::where('id', $userId)->exists();
        });

        if (! $userExists) {
            throw new \InvalidArgumentException(__('User not found in this tenant.'));
        }

        // Use native tenancy impersonate method
        return tenancy()->impersonate($tenant, $userId, $redirectUrl);
    }

    /**
     * Get the authenticated central admin user.
     */
    public function getAuthenticatedAdmin(): ?CentralUser
    {
        return auth('central')->user();
    }

    /**
     * Check if admin can access the tenant.
     */
    public function canAccessTenant(CentralUser $admin, Tenant $tenant): bool
    {
        return $admin->canAccessTenant($tenant);
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
