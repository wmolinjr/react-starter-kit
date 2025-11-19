<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;

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

        // Create owner user
        $this->user = User::factory()->create();
        $this->tenant->users()->attach($this->user->id, [
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        // Initialize tenant context
        tenancy()->initialize($this->tenant);

        // Set default SERVER variables for tenant domain
        $this->withServerVariables([
            'HTTP_HOST' => $domain->domain,
            'SERVER_NAME' => $domain->domain,
        ]);

        // Authenticate user
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        // End tenant context
        tenancy()->end();

        parent::tearDown();
    }

    /**
     * Create additional user with specific role for current tenant.
     */
    protected function createTenantUser(string $role = 'member'): User
    {
        $user = User::factory()->create();

        $this->tenant->users()->attach($user->id, [
            'role' => $role,
            'joined_at' => now(),
        ]);

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
