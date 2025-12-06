<?php

namespace Tests\Concerns;

use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Models\Central\Tenant;
use App\Models\Shared\Permission;
use App\Models\Shared\Role;

/**
 * Trait for tests that need tenant context WITHOUT automatic user authentication.
 *
 * Use this for tests that:
 * - Test login/registration flows (where we need to create users without logging in)
 * - Need tenant context for Tenant\User model (SoftDeletes requires tenant database)
 * - Don't want the automatic actingAs() from TenantTestCase
 *
 * @see TenantTestCase for tests that need full tenant setup with authenticated user
 */
trait WithTenant
{
    protected Tenant $tenant;
    protected string $tenantDomain;

    /**
     * Initialize tenant context for tests.
     * Call this in setUp() after parent::setUp().
     */
    protected function initializeTenant(): void
    {
        // Seed plans for test (required for user limits, features, etc.)
        \Artisan::call('db:seed', ['--class' => 'PlanSeeder']);

        // Seed addons for test (required for addon tests)
        \Artisan::call('db:seed', ['--class' => 'AddonSeeder']);

        // Get professional plan for testing (has good limits)
        $professionalPlan = \App\Models\Central\Plan::where('slug', 'professional')->first();

        // Create test tenant with a plan
        $this->tenant = Tenant::factory()->create([
            'slug' => 'test-tenant-'.uniqid(),
            'plan_id' => $professionalPlan?->id,
        ]);

        $domain = $this->tenant->domains()->create([
            'domain' => $this->tenant->slug.'.myapp.test',
            'is_primary' => true,
        ]);

        // Store domain for HTTP requests
        $this->tenantDomain = $domain->domain;

        // Initialize tenant context (Stancl Tenancy v4)
        tenancy()->initialize($this->tenant);

        // Sync permissions and roles for tests
        $this->syncTenantPermissions();
    }

    /**
     * Generate a full URL for tenant routes.
     */
    protected function tenantUrl(string $path): string
    {
        $path = ltrim($path, '/');
        return "http://{$this->tenantDomain}/{$path}";
    }

    /**
     * Sync permissions and roles for current tenant in tests.
     */
    protected function syncTenantPermissions(): void
    {
        // Run the permissions:sync command to create central permissions and roles
        \Artisan::call('permissions:sync');

        // Seed tenant permissions from enum
        foreach (TenantPermission::values() as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'tenant',
            ]);
        }

        // Seed tenant roles from enum
        foreach (TenantRole::systemRoles() as $tenantRole) {
            $role = Role::firstOrCreate(
                ['name' => $tenantRole->value, 'guard_name' => 'tenant'],
                [
                    'display_name' => $tenantRole->displayName(),
                    'description' => $tenantRole->description(),
                    'is_protected' => $tenantRole->isSystemRole(),
                ]
            );

            $rolePermissions = $tenantRole->filterPermissions(TenantPermission::values());
            $role->syncPermissions($rolePermissions);
        }
    }
}
