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

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
                'permissions' => $request->user() ? [
                    // Gates
                    'canManageTeam' => Gate::allows('manage-team'),
                    'canManageBilling' => Gate::allows('manage-billing'),
                    'canManageSettings' => Gate::allows('manage-settings'),
                    'canCreateResources' => Gate::allows('create-resources'),

                    // Role atual
                    'role' => $request->user()->currentTenantRole(),
                    'isOwner' => $request->user()->isOwner(),
                    'isAdmin' => $request->user()->hasRole('admin'),
                    'isAdminOrOwner' => $request->user()->isAdminOrOwner(),
                ] : null,
            ],
            'tenant' => tenancy()->initialized ? [
                'id' => tenant('id'),
                'name' => tenant('name'),
                'slug' => current_tenant()->slug,
                'domain' => $request->getHost(),
                'settings' => current_tenant()->settings,
            ] : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'impersonation' => [
                'isImpersonating' => $request->session()->has('impersonating_tenant'),
                'impersonatingTenant' => $request->session()->get('impersonating_tenant'),
                'impersonatingUser' => $request->session()->get('impersonating_user'),
            ],
        ];
    }
}
