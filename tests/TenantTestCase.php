<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Base test case for tenant-scoped tests.
 *
 * Automatically creates a tenant, user, and initializes tenant context.
 * All tests extending this class will run within a tenant context.
 */
abstract class TenantTestCase extends TestCase
{
    protected Tenant $tenant;
    protected User $user;
    protected string $tenantDomain;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test tenant
        $this->tenant = Tenant::factory()->create([
            'slug' => 'test-tenant-'.uniqid(),
        ]);

        $domain = $this->tenant->domains()->create([
            'domain' => $this->tenant->slug.'.myapp.test',
            'is_primary' => true,
        ]);

        // Store domain for HTTP requests
        $this->tenantDomain = $domain->domain;

        // Create owner user
        $this->user = User::factory()->create();
        $this->tenant->users()->attach($this->user->id, [
            'joined_at' => now(),
        ]);

        // Initialize tenant context (Following Stancl Tenancy v3 best practices)
        // Keep tenant initialized throughout the test - this is the recommended approach
        tenancy()->initialize($this->tenant);
        setPermissionsTeamId($this->tenant->id);

        // Create and assign owner role (requires tenant context)
        $ownerRole = Role::findOrCreate('owner', 'web');
        $this->user->assignRole($ownerRole);
        $this->user->load('roles');

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Authenticate user
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        // End tenant context if still initialized
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    /**
     * Create additional user with specific role for current tenant.
     */
    protected function createTenantUser(string $role = 'member'): User
    {
        $user = User::factory()->create();

        $this->tenant->users()->attach($user->id, [
            'joined_at' => now(),
        ]);

        // Tenant context is already initialized in setUp
        $roleModel = Role::findOrCreate($role, 'web');
        $user->assignRole($roleModel);
        $user->load('roles');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return $user;
    }

    /**
     * Create another tenant (for testing isolation).
     */
    protected function createOtherTenant(): Tenant
    {
        $otherTenant = Tenant::factory()->create([
            'slug' => 'other-tenant-'.uniqid(),
        ]);

        $otherTenant->domains()->create([
            'domain' => $otherTenant->slug.'.myapp.test',
            'is_primary' => true,
        ]);

        return $otherTenant;
    }
}
