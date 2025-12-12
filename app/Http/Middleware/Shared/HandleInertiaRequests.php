<?php

namespace App\Http\Middleware\Shared;

use App\Enums\PlanFeature;
use App\Enums\PlanLimit;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Laravel\Pennant\Feature;
use Stancl\Tenancy\Features\UserImpersonation;

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

        // Check central guard first, then default guard
        // This fixes the issue where central admins have null auth.user
        // because $request->user() uses the default 'tenant' guard
        $user = auth('central')->user() ?? $request->user();

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
                // Signup wizard flash data
                'pendingSignup' => fn () => $request->session()->get('pendingSignup'),
                'paymentResult' => fn () => $request->session()->get('paymentResult'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Stancl/Tenancy v4 native impersonation + Admin Mode (Option C)
            'impersonation' => [
                'isImpersonating' => UserImpersonation::isImpersonating() || session('tenancy_impersonating'),
                'isAdminMode' => (bool) session('tenancy_admin_mode'),
            ],
            'addons' => fn () => $this->getAddonsData(tenant()),
            'locale' => app()->getLocale(),
            'fallbackLocale' => config('app.fallback_locale'),
            'availableLocales' => config('app.locales'),
            'localeLabels' => collect(config('app.locale_labels'))
                ->only(config('app.locales'))
                ->toArray(),
            'currency' => stripe_currency_config(),
        ];
    }

    /**
     * Get authentication data for the current user.
     *
     * OPTION C ARCHITECTURE:
     * - Users exist ONLY in tenant databases (complete isolation)
     * - A user belongs to exactly one tenant (the database they're in)
     * - No tenants() relationship on User model
     *
     * ADMIN MODE:
     * - Central admin enters tenant without logging in as a user
     * - Provides full permissions for viewing (all tenant permissions)
     * - Role shows as 'Admin Mode'
     *
     * PERFORMANCE: Usa cache do Spatie Permission (isolado por tenant via SpatiePermissionsBootstrapper)
     * - getAllPermissions() cached automaticamente
     * - hasRole() cached automaticamente
     * - Cache invalidado automaticamente quando roles/permissions mudam
     */
    protected function getAuthData($user): array
    {
        // Admin Mode: Central admin viewing tenant without specific user
        if (! $user && session('tenancy_admin_mode') && tenancy()->initialized) {
            return $this->getAdminModeAuthData();
        }

        if (! $user) {
            return [
                'user' => null,
                'tenant' => null,
                'permissions' => [],
                'role' => null,
                'guard' => null,
            ];
        }

        // Central\User: Uses Spatie roles/permissions with guard 'central'
        // Roles: super-admin, central-admin, support-admin
        // NO bypass - permissions are explicit via assigned roles
        if ($user instanceof \App\Models\Central\User) {
            return [
                'user' => $user->toArray(),
                'tenant' => null, // Central admins don't belong to tenants
                'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
                'role' => $user->getRoleName(),
                'isSuperAdmin' => $user->isSuperAdmin(),
                'guard' => 'central', // Indicate which guard is active
            ];
        }

        // Customer: Billing entity - no Spatie roles/permissions
        // Authenticated via 'customer' guard at /account/* routes
        if ($user instanceof \App\Models\Central\Customer) {
            return [
                'user' => $user->toArray(),
                'tenant' => null, // Customers manage tenants, don't belong to them
                'permissions' => [], // Customers don't use Spatie Permission
                'role' => 'customer',
                'guard' => 'customer', // Indicate which guard is active
            ];
        }

        return [
            'user' => $user->toArray(),

            // Option C: User belongs to exactly one tenant (current context)
            // No multi-tenant switching - users are isolated per database
            'tenant' => $this->getCurrentTenantForUser($user),

            // Permissions: merge user role permissions with tenant plan-enabled permissions
            // User gets their role permissions PLUS any permissions enabled by tenant's plan
            // CACHE: getAllPermissions() usa cache do Spatie automaticamente
            'permissions' => $this->getMergedPermissions($user),

            // Role info: para UI apenas (badges, display, etc) - NÃO usar para autorização
            'role' => $this->getRoleData($user),

            'guard' => 'tenant', // Indicate which guard is active
        ];
    }

    /**
     * Get current tenant info for the authenticated user.
     *
     * OPTION C: Users exist only in their tenant's database.
     * They belong to exactly one tenant - the current one.
     */
    protected function getCurrentTenantForUser($user): ?array
    {
        if (! tenancy()->initialized) {
            return null;
        }

        $tenant = tenant();

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'role' => $user->currentTenantRole(),
            'is_current' => true,
        ];
    }

    /**
     * Get merged permissions from user role and tenant plan.
     *
     * Combines:
     * - User's role permissions (from Spatie Permission)
     * - Plan-enabled permissions (from tenant's plan features)
     *
     * This allows users to access features like auditLog that are plan-based,
     * not role-based. Only admins/owners get these plan permissions.
     */
    protected function getMergedPermissions($user): array
    {
        // Get user's role permissions
        $rolePermissions = $user->getAllPermissions()->pluck('name')->toArray();

        // If no tenant context, return only role permissions
        if (! tenancy()->initialized) {
            return $rolePermissions;
        }

        // Only merge plan permissions for admin/owner roles
        // Members should not get plan-level permissions automatically
        if (! $user->isAdminOrOwner()) {
            return $rolePermissions;
        }

        // Get plan-enabled permissions from tenant
        $tenant = tenant();
        $planPermissions = $tenant->getPlanEnabledPermissions();

        // Merge and deduplicate
        return array_values(array_unique(array_merge($rolePermissions, $planPermissions)));
    }

    /**
     * Get role data for UI display.
     *
     * Multi-database tenancy: Super Admin role is in central database,
     * tenant roles are in tenant database. SpatiePermissionsBootstrapper
     * handles database switching automatically.
     */
    protected function getRoleData($user): array
    {
        // Check Super Admin (stored in central database)
        $isSuperAdmin = $user->hasRole('Super Admin');

        return [
            'name' => $user->currentTenantRole(),
            'isOwner' => $user->isOwner(),
            'isAdmin' => $user->hasRole('admin'),
            'isAdminOrOwner' => $user->isAdminOrOwner(),
            'isSuperAdmin' => $isSuperAdmin,
        ];
    }

    /**
     * Get auth data for Admin Mode (central admin viewing tenant).
     *
     * Admin Mode allows central admins to enter a tenant without logging in
     * as a specific user. They get full permissions for viewing the tenant.
     */
    protected function getAdminModeAuthData(): array
    {
        $tenant = tenant();

        // Get all tenant permissions (full access for admin mode)
        $allPermissions = \App\Enums\TenantPermission::values();

        return [
            'user' => [
                'id' => null,
                'name' => __('Admin Mode'),
                'email' => __('Central Administrator'),
            ],
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'role' => 'admin-mode',
                'is_current' => true,
            ],
            'permissions' => $allPermissions,
            'role' => [
                'name' => 'admin-mode',
                'isOwner' => false,
                'isAdmin' => true,
                'isAdminOrOwner' => true,
                'isSuperAdmin' => true,
                'isAdminMode' => true,
            ],
            'guard' => 'admin-mode',
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
            'slug' => tenant()->slug,
            'domain' => $request->getHost(),
            'settings' => tenant()->settings,
            'subscription' => $this->getTenantSubscription(tenant()),
            'plan' => $this->getPlanData(tenant()),
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
            'name' => $subscription->provider_price_id,
            'active' => $subscription->isActive(),
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

        // Get features using Pennant (dynamic from PlanFeature enum)
        $features = [];
        foreach (PlanFeature::frontendFeatures() as $featureKey) {
            $features[$featureKey] = Feature::for($tenant)->active($featureKey);
        }

        // Get limits using Pennant (dynamic from PlanLimit enum)
        $limits = [];
        foreach (PlanLimit::cases() as $limit) {
            $limits[$limit->value] = Feature::for($tenant)->value($limit->pennantFeatureName());
        }

        // Get current usage (dynamic from PlanLimit enum)
        $usage = [];
        foreach (PlanLimit::values() as $limitKey) {
            $usage[$limitKey] = $tenant->getCurrentUsage($limitKey);
        }

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

    /**
     * Get addons data for the current tenant.
     */
    protected function getAddonsData($tenant): ?array
    {
        if (! $tenant) {
            return null;
        }

        $addonService = app(\App\Services\Central\AddonService::class);

        return [
            'active' => $tenant->activeAddons->map(fn ($addon) => [
                'id' => $addon->id,
                'slug' => $addon->addon_slug,
                'name' => $addon->name,
                'type' => $addon->addon_type->value,
                'quantity' => $addon->quantity,
                'price' => $addon->formatted_price,
                'total_price' => $addon->formatted_total_price,
                'billing_period' => $addon->billing_period->value,
                'status' => $addon->status->value,
                'started_at' => $addon->started_at?->toISOString(),
                'expires_at' => $addon->expires_at?->toISOString(),
                'is_recurring' => $addon->isRecurring(),
                'is_metered' => $addon->isMetered(),
                'metered_usage' => $addon->metered_usage,
            ]),
            'catalog' => $this->getAddonCatalog($tenant),
            'monthly_cost' => $addonService->calculateTotalMonthlyCost($tenant),
            'formatted_monthly_cost' => format_stripe_price($addonService->calculateTotalMonthlyCost($tenant)),
        ];
    }

    /**
     * Get addon catalog for tenant's plan.
     */
    protected function getAddonCatalog($tenant): array
    {
        // Try database first
        $dbAddons = \App\Models\Central\Addon::with('plans')->active()->orderBy('sort_order')->get();

        return $dbAddons->map(function ($addon) use ($tenant) {
            $isAvailable = $tenant->plan ? $addon->isAvailableForPlan($tenant->plan) : false;
            $currentQuantity = $tenant->getAddonQuantity($addon->slug);

            return [
                'slug' => $addon->slug,
                'name' => $addon->name,
                'description' => $addon->description ?? '',
                'type' => $addon->type->value,
                'billing' => $this->formatBillingFromDatabase($addon),
                'min_quantity' => $addon->min_quantity,
                'max_quantity' => $addon->max_quantity ?? 100,
                'features' => $addon->features ?? [],
                'badge' => $addon->badge,
                'is_available' => $isAvailable,
                'current_quantity' => $currentQuantity,
            ];
        })->values()->toArray();
    }

    /**
     * Format billing from database Addon model
     */
    protected function formatBillingFromDatabase(\App\Models\Central\Addon $addon): array
    {
        $billing = [];

        if ($addon->price_monthly) {
            $billing['monthly'] = [
                'price' => $addon->price_monthly,
                'formatted_price' => format_stripe_price($addon->price_monthly),
                'interval' => 'month',
            ];
        }

        if ($addon->price_yearly) {
            $billing['yearly'] = [
                'price' => $addon->price_yearly,
                'formatted_price' => format_stripe_price($addon->price_yearly),
                'interval' => 'year',
            ];
        }

        if ($addon->price_one_time) {
            $billing['one_time'] = [
                'price' => $addon->price_one_time,
                'formatted_price' => format_stripe_price($addon->price_one_time),
                'interval' => null,
            ];
        }

        return $billing;
    }
}
