<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Laravel\Pennant\Feature;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => $this->getAuthData($user),
            'tenant' => $this->getTenantData($request),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'impersonation' => [
                'isImpersonating' => $request->session()->has('impersonating_tenant'),
                'impersonatingTenant' => $request->session()->get('impersonating_tenant'),
                'impersonatingUser' => $request->session()->get('impersonating_user'),
            ],
        ];
    }

    /**
     * Get authentication data for the current user.
     *
     * PERFORMANCE: Usa cache do Spatie Permission (isolado por tenant via SpatiePermissionsBootstrapper)
     * - getAllPermissions() cached automaticamente
     * - hasRole() cached automaticamente
     * - Cache invalidado automaticamente quando roles/permissions mudam
     */
    protected function getAuthData($user): array
    {
        if (! $user) {
            return [
                'user' => null,
                'tenants' => [],
                'permissions' => [],
                'role' => null,
            ];
        }

        // Eager load tenants para evitar N+1
        $user->loadMissing('tenants');

        // Cache do team_id atual para restaurar depois
        $currentTeamId = getPermissionsTeamId();

        return [
            'user' => $user->toArray(),

            // Tenants do usuário com eager loading
            'tenants' => $user->tenants->map(function ($tenant) use ($user) {
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'role' => $user->roleOn($tenant), // Usa cache do Spatie
                    'is_current' => tenancy()->initialized && tenant('id') === $tenant->id,
                ];
            }),

            // Permissions: apenas as que o usuário TEM (não todas com booleans)
            // Mais performático (1 query vs 19+) e escalável (100+ permissions no futuro)
            // CACHE: getAllPermissions() usa cache do Spatie automaticamente
            'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),

            // Role info: para UI apenas (badges, display, etc) - NÃO usar para autorização
            'role' => $this->getRoleData($user, $currentTeamId),
        ];
    }

    /**
     * Get role data for UI display.
     *
     * CACHE: Todas as verificações usam cache do Spatie automaticamente
     */
    protected function getRoleData($user, $currentTeamId): array
    {
        // Check Super Admin globally (without tenant_id)
        setPermissionsTeamId(null);
        $user->unsetRelation('roles'); // Force reload para contexto global
        $isSuperAdmin = $user->hasRole('Super Admin'); // CACHED by Spatie

        // Restaurar team_id original
        setPermissionsTeamId($currentTeamId);
        $user->unsetRelation('roles'); // Force reload para contexto tenant

        return [
            'name' => $user->currentTenantRole(), // Usa cache do Spatie
            'isOwner' => $user->isOwner(), // hasRole('owner') - CACHED
            'isAdmin' => $user->hasRole('admin'), // CACHED
            'isAdminOrOwner' => $user->isAdminOrOwner(), // CACHED
            'isSuperAdmin' => $isSuperAdmin,
        ];
    }

    /**
     * Get tenant data for the current request.
     */
    protected function getTenantData(Request $request): ?array
    {
        if (! tenancy()->initialized) {
            return null;
        }

        return [
            'id' => tenant('id'),
            'name' => tenant('name'),
            'slug' => current_tenant()->slug,
            'domain' => $request->getHost(),
            'settings' => current_tenant()->settings,
            'subscription' => $this->getTenantSubscription(current_tenant()),
            'plan' => $this->getPlanData(current_tenant()),
        ];
    }

    /**
     * Get tenant subscription information.
     */
    protected function getTenantSubscription($tenant): ?array
    {
        if (! $tenant) {
            return null;
        }

        $subscription = $tenant->subscription('default');

        if (! $subscription) {
            return null;
        }

        return [
            'name' => $subscription->stripe_price,
            'active' => $subscription->active(),
            'on_trial' => $subscription->onTrial(),
            'ends_at' => $subscription->ends_at?->toISOString(),
            'trial_ends_at' => $subscription->trial_ends_at?->toISOString(),
        ];
    }

    /**
     * Get plan data for the current tenant.
     */
    protected function getPlanData($tenant): ?array
    {
        if (! $tenant || ! $tenant->plan) {
            return null;
        }

        $plan = $tenant->plan;

        // Get features using Pennant
        $features = [
            'customRoles' => Feature::for($tenant)->active('customRoles'),
            'apiAccess' => Feature::for($tenant)->active('apiAccess'),
            'advancedReports' => Feature::for($tenant)->active('advancedReports'),
            'sso' => Feature::for($tenant)->active('sso'),
            'whiteLabel' => Feature::for($tenant)->active('whiteLabel'),
        ];

        // Get limits using Pennant (rich values)
        $limits = [
            'users' => Feature::for($tenant)->value('maxUsers'),
            'projects' => Feature::for($tenant)->value('maxProjects'),
            'storage' => Feature::for($tenant)->value('storageLimit'),
        ];

        // Get current usage
        $usage = [
            'users' => $tenant->getCurrentUsage('users'),
            'projects' => $tenant->getCurrentUsage('projects'),
            'storage' => $tenant->getCurrentUsage('storage'),
        ];

        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'description' => $plan->description,
            'price' => $plan->price,
            'formatted_price' => $plan->formatted_price,
            'features' => $features,
            'limits' => $limits,
            'usage' => $usage,
            'is_on_trial' => $tenant->isOnTrial(),
            'trial_ends_at' => $tenant->trial_ends_at?->toISOString(),
        ];
    }
}
