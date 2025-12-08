<?php

namespace App\Http\Controllers\Central\Admin;

use App\Enums\CentralPermission;
use App\Http\Controllers\Controller;
use App\Http\Resources\Central\PlanSummaryResource;
use App\Http\Resources\Central\TenantDetailResource;
use App\Http\Resources\Central\TenantEditResource;
use App\Http\Resources\Central\TenantResource;
use App\Models\Central\FederationGroup;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class TenantManagementController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:'.CentralPermission::TENANTS_VIEW->value, only: ['index']),
            new Middleware('can:'.CentralPermission::TENANTS_SHOW->value, only: ['show']),
            new Middleware('can:'.CentralPermission::TENANTS_EDIT->value, only: ['edit', 'update']),
            new Middleware('can:'.CentralPermission::TENANTS_DELETE->value, only: ['destroy']),
        ];
    }

    /**
     * Display tenant listing.
     *
     * Uses TenantResource for consistent data transformation.
     */
    public function index(Request $request): Response
    {
        $tenants = Tenant::query()
            ->with(['domains', 'plan'])
            ->when($request->search, fn ($q, $s) => $q->where('name', 'ilike', "%{$s}%"))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('central/admin/tenants/index', [
            'tenants' => TenantResource::collection($tenants),
            'filters' => $request->only(['search']),
            'isImpersonating' => session()->has('impersonating'),
        ]);
    }

    /**
     * Display tenant details.
     *
     * Uses TenantDetailResource for consistent data transformation.
     */
    public function show(Tenant $tenant): Response
    {
        $tenant->load([
            'domains',
            'plan',
            'addons',
            'federationGroups' => fn ($q) => $q->with('masterTenant')->withCount('federatedUsers'),
        ]);

        // Get federation groups that this tenant is NOT already a member of
        $availableFederationGroups = FederationGroup::query()
            ->where('is_active', true)
            ->whereDoesntHave('tenants', fn ($q) => $q->where('tenant_id', $tenant->id))
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('central/admin/tenants/show', [
            'tenant' => new TenantDetailResource($tenant),
            'availableFederationGroups' => $availableFederationGroups,
        ]);
    }

    /**
     * Display tenant edit form.
     *
     * Uses TenantEditResource and PlanSummaryResource for consistent data transformation.
     */
    public function edit(Tenant $tenant): Response
    {
        $tenant->load(['domains', 'plan']);

        return Inertia::render('central/admin/tenants/edit', [
            'tenant' => new TenantEditResource($tenant),
            'plans' => PlanSummaryResource::collection(
                Plan::active()->orderBy('sort_order')->get()
            ),
        ]);
    }

    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'plan_id' => 'nullable|exists:plans,id',
        ]);

        $tenant->update($validated);

        return back()->with('success', __('flash.tenant.updated'));
    }

    /**
     * Delete a tenant.
     *
     * OPTION C: TENANT-ONLY USERS
     * - No pivot table to detach
     * - Users are deleted with tenant database (via Stancl DeleteDatabase job)
     */
    public function destroy(Tenant $tenant)
    {
        $tenant->domains()->delete();
        $tenant->delete();

        return redirect()->route('central.admin.tenants.index')->with('success', __('flash.tenant.deleted'));
    }
}
