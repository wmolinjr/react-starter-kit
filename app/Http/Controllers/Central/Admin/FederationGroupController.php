<?php

namespace App\Http\Controllers\Central\Admin;

use App\Enums\CentralPermission;
use App\Exceptions\Central\FederationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\AddTenantToGroupRequest;
use App\Http\Requests\Central\StoreFederationGroupRequest;
use App\Http\Requests\Central\UpdateFederationGroupRequest;
use App\Http\Resources\Central\FederatedUserDetailResource;
use App\Http\Resources\Central\FederatedUserResource;
use App\Http\Resources\Central\FederationGroupDetailResource;
use App\Http\Resources\Central\FederationGroupResource;
use App\Http\Resources\Central\TenantSummaryResource;
use App\Models\Central\FederatedUser;
use App\Models\Central\FederationGroup;
use App\Models\Central\Tenant;
use App\Services\Central\FederationService;
use App\Services\Central\FederationSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class FederationGroupController extends Controller implements HasMiddleware
{
    public function __construct(
        protected FederationService $federationService,
        protected FederationSyncService $syncService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:' . CentralPermission::FEDERATION_VIEW->value, only: ['index', 'show', 'showUser']),
            new Middleware('can:' . CentralPermission::FEDERATION_CREATE->value, only: ['create', 'store']),
            new Middleware('can:' . CentralPermission::FEDERATION_EDIT->value, only: ['edit', 'update', 'addTenant', 'removeTenant', 'syncUser', 'retrySync']),
            new Middleware('can:' . CentralPermission::FEDERATION_DELETE->value, only: ['destroy']),
        ];
    }

    /**
     * List all federation groups.
     */
    public function index(): Response
    {
        $groups = FederationGroup::query()
            ->with('masterTenant')
            ->withCount(['tenants', 'federatedUsers'])
            ->orderBy('name')
            ->get();

        return Inertia::render('central/admin/federation/index', [
            'groups' => FederationGroupResource::collection($groups),
            'stats' => $this->federationService->getOverallStats(),
        ]);
    }

    /**
     * Show create form.
     */
    public function create(): Response
    {
        // Get tenants that are not in any federation group
        $availableTenants = Tenant::query()
            ->whereDoesntHave('federationGroups', function ($query) {
                $query->whereNull('federation_group_tenants.left_at');
            })
            ->orderBy('name')
            ->get();

        return Inertia::render('central/admin/federation/create', [
            'availableTenants' => TenantSummaryResource::collection($availableTenants),
            'syncStrategies' => FederationGroup::SYNC_STRATEGIES,
            'defaultSyncFields' => FederationGroup::DEFAULT_SYNC_FIELDS,
        ]);
    }

    /**
     * Store a new federation group.
     */
    public function store(StoreFederationGroupRequest $request): RedirectResponse
    {
        try {
            $validated = $request->validated();
            $masterTenant = Tenant::findOrFail($validated['master_tenant_id']);

            $group = $this->federationService->createGroup(
                name: $validated['name'],
                masterTenant: $masterTenant,
                description: $validated['description'] ?? null,
                syncStrategy: $validated['sync_strategy'],
                settings: $validated['settings'] ?? []
            );

            return redirect()->route('central.admin.federation.show', $group)
                ->with('success', __('flash.federation.group_created'));

        } catch (FederationException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Show a federation group.
     */
    public function show(FederationGroup $group): Response
    {
        $group->load([
            'masterTenant',
            'tenants',
            'federatedUsers.masterTenant',
            'federatedUsers.links.tenant',
        ]);

        // Get tenants that can be added to this group
        $availableTenants = Tenant::query()
            ->whereDoesntHave('federationGroups', function ($query) {
                $query->whereNull('federation_group_tenants.left_at');
            })
            ->orderBy('name')
            ->get();

        return Inertia::render('central/admin/federation/show', [
            'group' => new FederationGroupDetailResource($group),
            'availableTenants' => TenantSummaryResource::collection($availableTenants),
            'stats' => $this->federationService->getGroupStats($group),
        ]);
    }

    /**
     * Show edit form.
     */
    public function edit(FederationGroup $group): Response
    {
        $group->load(['masterTenant', 'tenants']);

        // For editing, we need tenants that are either:
        // 1. Already in this group (for master tenant selection)
        // 2. Available (not in any group)
        $groupTenantIds = $group->tenants->pluck('id')->toArray();

        $tenants = Tenant::query()
            ->where(function ($query) use ($groupTenantIds) {
                // Tenants in this group
                $query->whereIn('id', $groupTenantIds)
                    // OR tenants not in any group
                    ->orWhereDoesntHave('federationGroups', function ($q) {
                        $q->whereNull('federation_group_tenants.left_at');
                    });
            })
            ->orderBy('name')
            ->get();

        return Inertia::render('central/admin/federation/edit', [
            'group' => new FederationGroupDetailResource($group),
            'tenants' => TenantSummaryResource::collection($tenants),
            'syncStrategies' => FederationGroup::SYNC_STRATEGIES,
            'defaultSyncFields' => FederationGroup::DEFAULT_SYNC_FIELDS,
        ]);
    }

    /**
     * Update a federation group.
     */
    public function update(UpdateFederationGroupRequest $request, FederationGroup $group): RedirectResponse
    {
        $this->federationService->updateGroup($group, $request->validated());

        return redirect()->route('central.admin.federation.show', $group)
            ->with('success', __('flash.federation.group_updated'));
    }

    /**
     * Delete a federation group.
     */
    public function destroy(FederationGroup $group): RedirectResponse
    {
        try {
            $this->federationService->deleteGroup($group);

            return redirect()->route('central.admin.federation.index')
                ->with('success', __('flash.federation.group_deleted'));

        } catch (FederationException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Add a tenant to the group.
     */
    public function addTenant(AddTenantToGroupRequest $request, FederationGroup $group): RedirectResponse
    {
        try {
            $tenant = Tenant::findOrFail($request->validated()['tenant_id']);

            $this->federationService->addTenantToGroup(
                $group,
                $tenant,
                $request->validated()['settings'] ?? []
            );

            return back()->with('success', __('flash.federation.tenant_added', ['tenant' => $tenant->name]));

        } catch (FederationException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Remove a tenant from the group.
     */
    public function removeTenant(FederationGroup $group, Tenant $tenant): RedirectResponse
    {
        try {
            $this->federationService->removeTenantFromGroup($group, $tenant);

            return back()->with('success', __('flash.federation.tenant_removed', ['tenant' => $tenant->name]));

        } catch (FederationException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Show federated user details.
     */
    public function showUser(FederationGroup $group, FederatedUser $user): Response
    {
        $user->load(['masterTenant', 'federationGroup', 'links.tenant']);

        return Inertia::render('central/admin/federation/user', [
            'group' => new FederationGroupResource($group),
            'user' => new FederatedUserDetailResource($user),
        ]);
    }

    /**
     * Trigger sync for a specific user.
     */
    public function syncUser(FederationGroup $group, FederatedUser $user): RedirectResponse
    {
        $results = $this->syncService->syncUserToAllTenants($user);

        $message = __('flash.federation.user_synced', [
            'success' => count($results['success']),
            'failed' => count($results['failed']),
        ]);

        return back()->with(
            count($results['failed']) > 0 ? 'warning' : 'success',
            $message
        );
    }

    /**
     * Retry failed syncs for a group.
     */
    public function retrySync(FederationGroup $group): RedirectResponse
    {
        \App\Jobs\Central\Federation\RetryFailedSyncsJob::dispatch($group);

        return back()->with('success', __('flash.federation.retry_queued'));
    }
}
