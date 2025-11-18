<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
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

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            'tenant' => function () use ($request) {
                // First try to get tenant from container (set by IdentifyTenantByDomain middleware)
                if (app()->has('tenant')) {
                    $tenant = app('tenant');
                    return [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'slug' => $tenant->slug,
                        'settings' => $tenant->settings,
                    ];
                }

                // Fallback to user's current tenant
                if ($request->user()?->current_tenant_id) {
                    $tenant = $request->user()->currentTenant;
                    if ($tenant) {
                        return [
                            'id' => $tenant->id,
                            'name' => $tenant->name,
                            'slug' => $tenant->slug,
                            'settings' => $tenant->settings,
                        ];
                    }
                }

                return null;
            },
            'tenants' => $request->user()?->tenants()->get()->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'role' => $t->pivot->role,
            ]),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
