<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

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
            'auth' => [
                'user' => $user ? array_merge(
                    $user->toArray(),
                    ['is_super_admin' => $user->is_super_admin ?? false]
                ) : null,
                'tenants' => $user ? $user->tenants->map(fn ($tenant) => [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'role' => $user->roleOn($tenant),
                    'is_current' => tenancy()->initialized && tenant('id') === $tenant->id,
                ]) : [],
                'permissions' => $user ? [
                    // Gates
                    'canManageTeam' => Gate::allows('manage-team'),
                    'canManageBilling' => Gate::allows('manage-billing'),
                    'canManageSettings' => Gate::allows('manage-settings'),
                    'canCreateResources' => Gate::allows('create-resources'),

                    // Role atual
                    'role' => $user->currentTenantRole(),
                    'isOwner' => $user->isOwner(),
                    'isAdmin' => $user->hasRole('admin'),
                    'isAdminOrOwner' => $user->isAdminOrOwner(),
                ] : null,
            ],
            'tenant' => tenancy()->initialized ? [
                'id' => tenant('id'),
                'name' => tenant('name'),
                'slug' => current_tenant()->slug,
                'domain' => $request->getHost(),
                'settings' => current_tenant()->settings,
                'subscription' => $this->getTenantSubscription(current_tenant()),
            ] : null,
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
}
