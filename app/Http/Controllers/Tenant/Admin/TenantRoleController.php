<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Enums\TenantPermission;
use App\Exceptions\Shared\RoleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreRoleRequest;
use App\Http\Requests\Tenant\UpdateRoleRequest;
use App\Http\Resources\Shared\RoleDetailResource;
use App\Http\Resources\Shared\RoleEditResource;
use App\Http\Resources\Shared\RoleResource;
use App\Models\Shared\Role;
use App\Services\Tenant\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tenant Role Controller
 *
 * MULTI-DATABASE TENANCY:
 * - All roles in tenant database belong to that tenant (no tenant_id needed)
 * - Users are in tenant database via model_has_roles table
 * - Each tenant has isolated roles/permissions tables
 */
class TenantRoleController extends Controller implements HasMiddleware
{
    public function __construct(
        protected RoleService $roleService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('plan:feature,customRoles'),
            new Middleware('permission:'.TenantPermission::ROLES_VIEW->value, only: ['index', 'show']),
            new Middleware('permission:'.TenantPermission::ROLES_CREATE->value, only: ['create', 'store']),
            new Middleware('permission:'.TenantPermission::ROLES_EDIT->value, only: ['edit', 'update']),
            new Middleware('permission:'.TenantPermission::ROLES_DELETE->value, only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        $tenant = tenant();

        return Inertia::render('tenant/admin/settings/roles/index', [
            'roles' => RoleResource::collection($this->roleService->getRolesWithStats()),
            'planInfo' => $this->roleService->getPlanInfo($tenant),
        ]);
    }

    public function create(): Response|RedirectResponse
    {
        $tenant = tenant();

        // Check if tenant can create custom roles
        if ($tenant->getLimit('customRoles') === 0) {
            return redirect()->route('tenant.admin.settings.roles.index')
                ->with('error', __('tenant.roles.custom_roles_not_available'));
        }

        // Check if limit reached
        if ($tenant->hasReachedLimit('customRoles')) {
            return redirect()->route('tenant.admin.settings.roles.index')
                ->with('error', __('tenant.roles.limit_reached'));
        }

        $permissions = $this->roleService->getAllowedPermissions($tenant);

        return Inertia::render('tenant/admin/settings/roles/create', [
            'permissions' => $this->roleService->formatPermissionsByCategory($permissions),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $role = $this->roleService->createRole(tenant(), $validated);

            return redirect()->route('tenant.admin.settings.roles.index')
                ->with('success', __('flash.role.created', ['name' => $role->display_name]));
        } catch (RoleException $e) {
            return back()->withErrors(['limit' => $e->getMessage()]);
        }
    }

    public function show(Role $role): Response
    {
        return Inertia::render('tenant/admin/settings/roles/show', [
            'role' => new RoleDetailResource($this->roleService->getRoleDetail($role)),
        ]);
    }

    public function edit(Role $role): Response
    {
        $tenant = tenant();
        $permissions = $this->roleService->getAllowedPermissions($tenant);

        return Inertia::render('tenant/admin/settings/roles/edit', [
            'role' => new RoleEditResource($this->roleService->getRoleForEdit($role, $tenant)),
            'permissions' => $this->roleService->formatPermissionsByCategory($permissions),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $role = $this->roleService->updateRole($role, tenant(), $validated);

            return redirect()->route('tenant.admin.settings.roles.index')
                ->with('success', __('flash.role.updated', ['name' => $role->display_name]));
        } catch (RoleException $e) {
            return back()->withErrors(['name' => $e->getMessage()]);
        }
    }

    public function destroy(Role $role): RedirectResponse
    {
        try {
            $name = $role->display_name;
            $this->roleService->deleteRole($role);

            return redirect()->route('tenant.admin.settings.roles.index')
                ->with('success', __('flash.role.deleted', ['name' => $name]));
        } catch (RoleException $e) {
            return back()->withErrors(['role' => $e->getMessage()]);
        }
    }
}
