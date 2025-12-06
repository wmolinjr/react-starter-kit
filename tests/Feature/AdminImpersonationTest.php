<?php

namespace Tests\Feature;

use App\Models\Central\User as Admin;
use App\Models\Central\Tenant;
use Database\Seeders\PlanSeeder;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Admin Impersonation Test Suite
 *
 * Tests the impersonation system for central admins (Option C architecture).
 *
 * OPTION C: Admins can impersonate tenants in two modes:
 * - Admin Mode: Access tenant without assuming a user identity
 * - User Impersonation: Access tenant as a specific user
 */
class AdminImpersonationTest extends TestCase
{
    protected Admin $superAdmin;

    protected Tenant $tenant;

    protected static bool $planSeeded = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed plans only once per class (required for tenant creation)
        if (! static::$planSeeded) {
            $this->seed(PlanSeeder::class);
            static::$planSeeded = true;
        }

        // Create super admin with unique email
        $this->superAdmin = Admin::factory()->superAdmin()->create([
            'email' => 'super-'.Str::random(8).'@example.com',
        ]);

        // Create tenant with a user (using unique slug)
        $this->tenant = $this->createTenantWithUser();
    }

    /**
     * Helper to create a tenant with an owner user.
     */
    protected function createTenantWithUser(): Tenant
    {
        $uniqueId = Str::random(8);

        $tenant = Tenant::create([
            'name' => 'Test Company '.$uniqueId,
            'slug' => 'test-company-'.$uniqueId,
            'settings' => [
                '_seed_owner' => [
                    'name' => 'Test Owner',
                    'email' => 'owner-'.$uniqueId.'@test.com',
                    'password' => 'password',
                ],
            ],
        ]);

        // Create domain for the tenant
        $tenant->domains()->create([
            'domain' => 'test-company-'.$uniqueId.'.localhost',
        ]);

        return $tenant;
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::$planSeeded = false;
    }

    /*
    |--------------------------------------------------------------------------
    | Impersonation Page Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function super_admin_can_access_impersonation_page(): void
    {
        $response = $this->actingAs($this->superAdmin, 'central')
            ->get($this->centralUrl("/admin/tenants/{$this->tenant->id}/impersonate"));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('central/admin/tenants/impersonate')
            ->has('tenant')
            ->has('users')
        );
    }

    #[Test]
    public function regular_admin_cannot_access_impersonation_page(): void
    {
        // Admin without role cannot access impersonation
        $regularAdmin = Admin::factory()->create();

        $response = $this->actingAs($regularAdmin, 'central')
            ->get($this->centralUrl("/admin/tenants/{$this->tenant->id}/impersonate"));

        $response->assertForbidden();
    }

    #[Test]
    public function guest_cannot_access_impersonation_page(): void
    {
        $response = $this->get($this->centralUrl("/admin/tenants/{$this->tenant->id}/impersonate"));

        $response->assertRedirect();
    }

    /*
    |--------------------------------------------------------------------------
    | Admin Mode Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function regular_admin_cannot_enter_admin_mode(): void
    {
        // Admin without role cannot enter admin mode
        $regularAdmin = Admin::factory()->create();

        $response = $this->actingAs($regularAdmin, 'central')
            ->post($this->centralUrl("/admin/tenants/{$this->tenant->id}/impersonate/admin-mode"));

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | User Impersonation Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function cannot_impersonate_with_invalid_uuid(): void
    {
        // Use a valid UUID format that doesn't exist
        $fakeUserId = '00000000-0000-0000-0000-000000000000';

        $response = $this->actingAs($this->superAdmin, 'central')
            ->post($this->centralUrl("/admin/tenants/{$this->tenant->id}/impersonate/as/{$fakeUserId}"));

        $response->assertNotFound();
    }

    #[Test]
    public function regular_admin_cannot_impersonate_user(): void
    {
        // Admin without role cannot impersonate
        $regularAdmin = Admin::factory()->create();
        // Use a fake UUID since we can't guarantee users exist
        $fakeUserId = '00000000-0000-0000-0000-000000000000';

        $response = $this->actingAs($regularAdmin, 'central')
            ->post($this->centralUrl("/admin/tenants/{$this->tenant->id}/impersonate/as/{$fakeUserId}"));

        // Should be forbidden before checking if user exists
        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Tenant Access Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function impersonation_page_shows_tenant_info(): void
    {
        $response = $this->actingAs($this->superAdmin, 'central')
            ->get($this->centralUrl("/admin/tenants/{$this->tenant->id}/impersonate"));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('tenant.id', $this->tenant->id)
            ->has('tenant.name')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Can Access Tenant Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function super_admin_can_access_any_tenant(): void
    {
        $this->assertTrue($this->superAdmin->canAccessTenant($this->tenant));
    }

    #[Test]
    public function regular_admin_cannot_access_tenant(): void
    {
        // Admin without impersonate permission cannot access tenant
        $regularAdmin = Admin::factory()->create();

        $this->assertFalse($regularAdmin->canAccessTenant($this->tenant));
    }

    /*
    |--------------------------------------------------------------------------
    | Nonexistent Tenant Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function impersonation_page_returns_404_for_nonexistent_tenant(): void
    {
        $response = $this->actingAs($this->superAdmin, 'central')
            ->get($this->centralUrl('/admin/tenants/nonexistent-uuid/impersonate'));

        $response->assertNotFound();
    }

    #[Test]
    public function admin_mode_returns_404_for_nonexistent_tenant(): void
    {
        $response = $this->actingAs($this->superAdmin, 'central')
            ->post($this->centralUrl('/admin/tenants/nonexistent-uuid/impersonate/admin-mode'));

        $response->assertNotFound();
    }
}
