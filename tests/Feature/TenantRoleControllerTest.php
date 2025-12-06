<?php

namespace Tests\Feature;

use App\Jobs\SyncTenantPermissions;
use App\Models\Universal\Permission;
use App\Models\Central\Plan;
use App\Models\Universal\Role;
use App\Services\Central\PlanPermissionResolver;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

class TenantRoleControllerTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles permissions exist in tenant database
        $this->seedRolesPermissions();

        // Sync permissions for tenant's plan
        $resolver = new PlanPermissionResolver();
        $allowedPermissions = $resolver->resolve($this->tenant);
        $this->tenant->forceFill([
            'plan_enabled_permissions' => $allowedPermissions,
        ])->saveQuietly();
    }

    /**
     * Seed roles-related permissions for testing.
     */
    protected function seedRolesPermissions(): void
    {
        $permissions = [
            ['name' => 'roles:view', 'category' => 'roles'],
            ['name' => 'roles:create', 'category' => 'roles'],
            ['name' => 'roles:edit', 'category' => 'roles'],
            ['name' => 'roles:delete', 'category' => 'roles'],
            // Additional permissions used in tests
            ['name' => 'team:view', 'category' => 'team'],
            ['name' => 'sso:manage', 'category' => 'sso'],
            ['name' => 'sso:configure', 'category' => 'sso'],
            ['name' => 'projects:view', 'category' => 'projects'],
        ];

        foreach ($permissions as $permData) {
            Permission::firstOrCreate([
                'name' => $permData['name'],
                'guard_name' => 'web',
            ], [
                'category' => $permData['category'],
            ]);
        }

        // Give owner role the roles permissions
        $ownerRole = Role::where('name', 'owner')->first();
        if ($ownerRole) {
            $ownerRole->givePermissionTo([
                'roles:view',
                'roles:create',
                'roles:edit',
                'roles:delete',
            ]);
        }

        // Clear permission cache
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    #[Test]
    public function index_shows_plan_info(): void
    {
        $response = $this->get($this->tenantRoute('tenant.admin.settings.roles.index'));

        $response->assertOk();
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('tenant/admin/settings/roles/index')
                ->has('roles')
                ->has('planInfo')
                ->has('planInfo.canCreateCustomRoles')
                ->has('planInfo.customRolesLimit')
                ->has('planInfo.customRolesCount')
                ->has('planInfo.hasReachedLimit')
                ->has('planInfo.planName')
        );
    }

    #[Test]
    public function create_only_shows_plan_permissions(): void
    {
        $response = $this->get($this->tenantRoute('tenant.admin.settings.roles.create'));

        $response->assertOk();
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('tenant/admin/settings/roles/create')
                ->has('permissions')
        );

        // Verify that only plan-allowed permissions are shown
        $inertiaData = $response->original->getData()['page']['props'];
        $permissions = $inertiaData['permissions'];

        // Professional plan should have roles permissions
        $this->assertNotEmpty($permissions);

        // Extract all permission names from the new structure
        // Structure: ['category' => ['label' => '...', 'permissions' => [...]]]
        $allPermissionNames = collect($permissions)
            ->flatMap(fn ($category) => collect($category['permissions'])->pluck('name'))
            ->toArray();

        // Should include roles permissions (Pro feature)
        $this->assertContains('roles:view', $allPermissionNames);

        // Should NOT include enterprise-only permissions
        $this->assertNotContains('sso:configure', $allPermissionNames);
        $this->assertNotContains('reports:view', $allPermissionNames);
    }

    #[Test]
    public function store_validates_permissions_against_plan(): void
    {
        // Create an enterprise permission that Pro plan shouldn't have
        Permission::firstOrCreate([
            'name' => 'sso:configure',
            'guard_name' => 'web',
        ], [
            'category' => 'sso',
        ]);

        $response = $this->post($this->tenantRoute('tenant.admin.settings.roles.store'), [
            'name' => 'test-custom-role',
            'display_name' => 'Test Custom Role',
            'description' => 'A test role',
            'permissions' => [
                // Try to include an enterprise permission
                Permission::where('name', 'sso:configure')->first()?->id,
                Permission::where('name', 'projects:view')->first()?->id,
            ],
        ]);

        $response->assertRedirect();

        // Role should be created
        $role = Role::where('name', 'test-custom-role')->first();
        $this->assertNotNull($role);

        // Role should have projects:view (allowed)
        $this->assertTrue($role->hasPermissionTo('projects:view'));

        // Role should NOT have sso:configure (not in Pro plan)
        $this->assertFalse($role->hasPermissionTo('sso:configure'));
    }

    #[Test]
    public function store_respects_custom_roles_limit(): void
    {
        // Get the professional plan's custom roles limit
        $limit = $this->tenant->getLimit('customRoles');

        // Create roles up to the limit
        for ($i = 0; $i < $limit; $i++) {
            Role::create([
                'name' => "custom-role-{$i}",
                'display_name' => "Custom Role {$i}",
                'guard_name' => 'web',
                'is_protected' => false,
            ]);
        }

        // Try to create one more
        $response = $this->post($this->tenantRoute('tenant.admin.settings.roles.store'), [
            'name' => 'one-too-many',
            'display_name' => 'One Too Many',
            'description' => 'Should fail',
            'permissions' => [],
        ]);

        $response->assertSessionHasErrors('limit');
    }

    #[Test]
    public function update_validates_permissions_against_plan(): void
    {
        // Create a custom role
        $role = Role::create([
            'name' => 'custom-manager',
            'display_name' => 'Custom Manager',
            'guard_name' => 'web',
            'is_protected' => false,
        ]);
        $role->givePermissionTo('projects:view');

        // Create an enterprise permission that Pro plan shouldn't have
        Permission::firstOrCreate([
            'name' => 'sso:manage',
            'guard_name' => 'web',
        ], [
            'category' => 'sso',
        ]);

        $response = $this->put($this->tenantRoute('tenant.admin.settings.roles.update', ['role' => $role]), [
            'name' => 'custom-manager',
            'display_name' => 'Custom Manager Updated',
            'description' => 'Updated description',
            'permissions' => [
                Permission::where('name', 'sso:manage')->first()?->id,
                Permission::where('name', 'team:view')->first()?->id,
            ],
        ]);

        $response->assertRedirect();

        $role->refresh();

        // Role should have team:view (allowed)
        $this->assertTrue($role->hasPermissionTo('team:view'));

        // Role should NOT have sso:manage (not in Pro plan)
        $this->assertFalse($role->hasPermissionTo('sso:manage'));
    }

    #[Test]
    public function create_redirects_when_custom_roles_not_available(): void
    {
        // Switch tenant to Starter plan (no custom roles)
        $starterPlan = Plan::where('slug', 'starter')->first();
        $this->tenant->update(['plan_id' => $starterPlan->id]);
        $this->tenant->refresh();

        // Update plan_enabled_permissions cache
        $resolver = new PlanPermissionResolver();
        $allowedPermissions = $resolver->resolve($this->tenant);
        $this->tenant->forceFill([
            'plan_enabled_permissions' => $allowedPermissions,
        ])->saveQuietly();

        // Try to access create page (should redirect because feature middleware blocks it)
        // The feature:customRoles middleware redirects to billing page with error message
        $response = $this->get($this->tenantRoute('tenant.admin.settings.roles.create'));

        // Should be redirected to billing page by the feature middleware
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function edit_filters_permissions_by_plan(): void
    {
        // Create a custom role with some permissions
        $role = Role::create([
            'name' => 'test-role-edit',
            'display_name' => 'Test Role for Edit',
            'guard_name' => 'web',
            'is_protected' => false,
        ]);
        $role->givePermissionTo(['projects:view', 'team:view']);

        $response = $this->get($this->tenantRoute('tenant.admin.settings.roles.edit', ['role' => $role]));

        $response->assertOk();
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('tenant/admin/settings/roles/edit')
                ->has('role')
                ->has('permissions')
                ->where('role.name', 'test-role-edit')
        );

        // Verify that only plan-allowed permissions are shown
        $inertiaData = $response->original->getData()['page']['props'];
        $permissions = $inertiaData['permissions'];

        // Flatten all permission names
        $allPermissionNames = collect($permissions)
            ->flatten(1)
            ->pluck('name')
            ->toArray();

        // Should NOT include enterprise-only permissions
        $this->assertNotContains('sso:configure', $allPermissionNames);
    }

    #[Test]
    public function professional_plan_shows_custom_roles_as_available(): void
    {
        $response = $this->get($this->tenantRoute('tenant.admin.settings.roles.index'));

        $response->assertOk();
        $response->assertInertia(
            fn (Assert $page) => $page
                ->where('planInfo.canCreateCustomRoles', true)
                ->where('planInfo.customRolesLimit', 5) // Pro plan has 5 custom roles
        );
    }
}
