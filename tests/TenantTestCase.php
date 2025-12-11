<?php

namespace Tests;

use App\Enums\TenantPermission;
use App\Enums\TenantRole;
use App\Models\Central\Tenant;
use App\Models\Shared\Role;
use App\Models\Tenant\User;

/**
 * Base test case for tenant-scoped tests.
 *
 * MULTI-DATABASE TENANCY (Option C: Tenant-Only Users):
 * - Each tenant has a dedicated PostgreSQL database with its own users
 * - Tests use a fixed `testing_tenant` database for all tenants
 * - tenancy()->initialize() switches to tenant database connection
 * - Users are created directly in the tenant database (no pivot table)
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

        // Seed plans for test (required for user limits, features, etc.)
        \Artisan::call('db:seed', ['--class' => 'PlanSeeder']);

        // Seed addons for test (required for addon tests)
        \Artisan::call('db:seed', ['--class' => 'AddonSeeder']);

        // Seed payment settings for checkout tests
        $this->seedPaymentSettingsForTests();

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
        // Switches to tenant's database connection (testing_tenant in tests)
        tenancy()->initialize($this->tenant);

        // Sync permissions and roles for tests
        $this->syncPermissionsForTests();

        // Create owner user directly in database (Option C: no pivot table)
        $this->user = User::factory()->create();

        // Assign owner role (requires tenant context for Spatie Permission)
        $ownerRole = Role::findByName('owner', 'tenant');
        $this->user->assignRole($ownerRole);
        $this->user->load('roles');

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Authenticate user
        $this->actingAs($this->user);
    }

    /**
     * Generate a full URL for tenant routes.
     *
     * IMPORTANT: Use this instead of route() for tenant routes when there are
     * conflicting central routes (e.g., /admin/addons exists in both central and tenant).
     *
     * Laravel's route() helper uses APP_URL (localhost) which can match central routes
     * instead of tenant routes. Using tenantUrl() ensures the correct domain is used.
     *
     * @param  string  $path  The path (e.g., '/admin/addons' or 'admin/addons')
     * @return string Full URL with tenant domain
     */
    protected function tenantUrl(string $path): string
    {
        $path = ltrim($path, '/');

        return "http://{$this->tenantDomain}/{$path}";
    }

    /**
     * Generate a tenant URL from a route name.
     *
     * Use the full route name including prefixes:
     * - 'tenant.admin.projects.index'
     * - 'tenant.admin.settings.roles.index'
     *
     * @param  string  $name  Full route name (e.g., 'tenant.admin.addons.index')
     * @param  array  $parameters  Route parameters
     * @return string Full URL with tenant domain
     */
    protected function tenantRoute(string $name, array $parameters = []): string
    {
        // Get the path from the route
        $url = route($name, $parameters);
        $path = parse_url($url, PHP_URL_PATH);

        return $this->tenantUrl($path);
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
     *
     * OPTION C: Creates user directly in database (no pivot table).
     */
    protected function createTenantUser(string $role = 'member'): User
    {
        $user = User::factory()->create();

        // Tenant context is already initialized in setUp
        $roleModel = Role::findOrCreate($role, 'tenant');
        $user->assignRole($roleModel);
        $user->load('roles');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return $user;
    }

    /**
     * Create another tenant (for testing central database operations).
     *
     * MULTI-DATABASE TENANCY NOTE:
     * Each tenant has its own database, so cross-tenant data access
     * is physically impossible. This is useful for testing central
     * database operations like tenant listing, plan management, etc.
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

    /**
     * Sync permissions and roles for current tenant in tests.
     *
     * In production, tenant roles are created by SeedTenantDatabase job.
     * In tests, we create them directly in the testing_tenant database.
     */
    protected function syncPermissionsForTests(): void
    {
        // Run the permissions:sync command to create central permissions and roles
        \Artisan::call('permissions:sync');

        // Create tenant roles directly (like SeedTenantDatabase does in production)
        $this->seedTenantRolesForTests();
    }

    /**
     * Seed payment settings for checkout tests.
     * Creates Stripe (card) and Asaas (pix, boleto) gateways.
     */
    protected function seedPaymentSettingsForTests(): void
    {
        // Stripe for card payments (default)
        \App\Models\Central\PaymentSetting::firstOrCreate(
            ['gateway' => 'stripe'],
            [
                'display_name' => 'Stripe',
                'is_enabled' => true,
                'is_sandbox' => true,
                'is_default' => true,
                'enabled_payment_types' => ['card'],
                'available_countries' => [],
                'sandbox_credentials' => [
                    'secret_key' => 'sk_test_fake',
                    'publishable_key' => 'pk_test_fake',
                    'webhook_secret' => 'whsec_fake',
                ],
            ]
        );

        // Asaas for Brazilian payments (PIX, Boleto)
        \App\Models\Central\PaymentSetting::firstOrCreate(
            ['gateway' => 'asaas'],
            [
                'display_name' => 'Asaas',
                'is_enabled' => true,
                'is_sandbox' => true,
                'is_default' => false,
                'enabled_payment_types' => ['card', 'pix', 'boleto'],
                'available_countries' => ['BR'],
                'sandbox_credentials' => [
                    'api_key' => 'test_api_key_fake',
                ],
            ]
        );
    }

    /**
     * Seed tenant roles for tests.
     * Uses TenantRole enum as single source of truth.
     * Mirrors SeedTenantDatabase::seedRoles() for test consistency.
     */
    protected function seedTenantRolesForTests(): void
    {
        // Get all tenant permissions from enum (single source of truth)
        $allPermissions = TenantPermission::values();

        // Seed tenant permissions (category/description derived from enum via accessors)
        foreach ($allPermissions as $permissionName) {
            \App\Models\Shared\Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'tenant',
            ]);
        }

        // Seed roles using TenantRole enum (single source of truth)
        foreach (TenantRole::systemRoles() as $tenantRole) {
            $role = Role::firstOrCreate(
                ['name' => $tenantRole->value, 'guard_name' => 'tenant'],
                [
                    'display_name' => $tenantRole->name(),
                    'description' => $tenantRole->description(),
                    'is_protected' => $tenantRole->isSystemRole(),
                ]
            );

            // Use TenantRole enum for filtering
            $rolePermissions = $tenantRole->filterPermissions($allPermissions);

            // Sync permissions
            $role->syncPermissions($rolePermissions);
        }
    }
}
