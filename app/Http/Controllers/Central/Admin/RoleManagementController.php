<?php

namespace App\Http\Controllers\Central\Admin;

use App\Enums\CentralPermission;
use App\Exceptions\Shared\RoleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\StoreRoleRequest;
use App\Http\Requests\Central\UpdateRoleRequest;
use App\Http\Resources\Shared\RoleDetailResource;
use App\Http\Resources\Shared\RoleEditResource;
use App\Http\Resources\Shared\RoleResource;
use App\Models\Shared\Role;
use App\Services\Central\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * RoleManagementController
 *
 * MULTI-DATABASE TENANCY:
 * - This controller manages roles in the CENTRAL database
 * - All roles here are central admin roles (Super Admin, Central Admin)
 * - Tenant roles (owner, admin, member) are in each tenant's database
 */
class RoleManagementController extends Controller implements HasMiddleware
{
    public function __construct(
        protected RoleService $roleService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:'.CentralPermission::ROLES_VIEW->value, only: ['index', 'show']),
            new Middleware('can:'.CentralPermission::ROLES_CREATE->value, only: ['create', 'store']),
            new Middleware('can:'.CentralPermission::ROLES_EDIT->value, only: ['edit', 'update']),
            new Middleware('can:'.CentralPermission::ROLES_DELETE->value, only: ['destroy']),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('central/admin/roles/index', [
            'centralRoles' => RoleResource::collection($this->roleService->getAllRoles()),
        ]);
    }

    public function create(): Response
    {
        $permissions = $this->roleService->getAllPermissions();

        return Inertia::render('central/admin/roles/create', [
            'permissions' => $this->roleService->formatPermissionsByCategory($permissions),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = $this->roleService->createRole($request->validated());

        return redirect()->route('central.admin.roles.index')
            ->with('success', __('flash.role.created', ['name' => $role->trans('display_name')]));
    }

    public function show(Role $role): Response
    {
        return Inertia::render('central/admin/roles/show', [
            'role' => new RoleDetailResource($this->roleService->getRoleDetail($role)),
        ]);
    }

    public function edit(Role $role): Response
    {
        $permissions = $this->roleService->getAllPermissions();

        return Inertia::render('central/admin/roles/edit', [
            'role' => new RoleEditResource($this->roleService->getRoleForEdit($role)),
            'permissions' => $this->roleService->formatPermissionsByCategory($permissions),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        try {
            $role = $this->roleService->updateRole($role, $request->validated());

            return redirect()->route('central.admin.roles.index')
                ->with('success', __('flash.role.updated', ['name' => $role->trans('display_name')]));
        } catch (RoleException $e) {
            return back()->withErrors(['name' => $e->getMessage()]);
        }
    }

    public function destroy(Role $role): RedirectResponse
    {
        try {
            $name = $role->trans('display_name');
            $this->roleService->deleteRole($role);

            return redirect()->route('central.admin.roles.index')
                ->with('success', __('flash.role.deleted', ['name' => $name]));
        } catch (RoleException $e) {
            return back()->withErrors(['role' => $e->getMessage()]);
        }
    }
}
