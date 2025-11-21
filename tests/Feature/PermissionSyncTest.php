<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class PermissionSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sync permissions
        $this->artisan('permissions:sync');

        // Seed plans
        $this->artisan('db:seed', ['--class' => 'PlanSeeder']);
        $this->artisan('plans:sync-permissions');
    }

    /** @test */
    public function upgrading_plan_enables_new_permissions(): void
    {
        $starterPlan = Plan::where('slug', 'starter')->first();
        $proPlan = Plan::where('slug', 'professional')->first();

        $tenant = Tenant::factory()->create(['plan_id' => $starterPlan->id]);

        // Initialize tenancy
        tenancy()->initialize($tenant);
        setPermissionsTeamId($tenant->id);

        // Regenerate permissions for starter plan
        $tenant->regeneratePlanPermissions();
        $initialPermissions = $tenant->getPlanEnabledPermissions();

        // Upgrade to Professional
        $tenant->update(['plan_id' => $proPlan->id]);

        // Permissions should be updated automatically by observer
        $tenant->refresh();
        $newPermissions = $tenant->getPlanEnabledPermissions();

        $this->assertGreaterThan(count($initialPermissions), count($newPermissions));
        $this->assertContains('tenant.roles:view', $newPermissions);
        $this->assertContains('tenant.apiTokens:view', $newPermissions);

        tenancy()->end();
    }

    /** @test */
    public function downgrading_plan_removes_permissions(): void
    {
        $proPlan = Plan::where('slug', 'professional')->first();
        $starterPlan = Plan::where('slug', 'starter')->first();

        $tenant = Tenant::factory()->create(['plan_id' => $proPlan->id]);

        tenancy()->initialize($tenant);
        setPermissionsTeamId($tenant->id);

        $tenant->regeneratePlanPermissions();
        $proPermissions = $tenant->getPlanEnabledPermissions();

        // Downgrade to Starter
        $tenant->update(['plan_id' => $starterPlan->id]);

        $tenant->refresh();
        $starterPermissions = $tenant->getPlanEnabledPermissions();

        $this->assertLessThan(count($proPermissions), count($starterPermissions));
        $this->assertNotContains('tenant.roles:view', $starterPermissions);
        $this->assertNotContains('tenant.apiTokens:view', $starterPermissions);

        tenancy()->end();
    }

    /** @test */
    public function permissions_are_cached_in_plan_enabled_permissions_field(): void
    {
        $plan = Plan::where('slug', 'professional')->first();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);

        tenancy()->initialize($tenant);
        setPermissionsTeamId($tenant->id);

        $this->assertNull($tenant->plan_enabled_permissions);

        $permissions = $tenant->regeneratePlanPermissions();

        $tenant->refresh();
        $this->assertNotNull($tenant->plan_enabled_permissions);
        $this->assertEquals($permissions, $tenant->plan_enabled_permissions);

        tenancy()->end();
    }

    /** @test */
    public function pennant_cache_is_flushed_when_plan_changes(): void
    {
        $starterPlan = Plan::where('slug', 'starter')->first();
        $proPlan = Plan::where('slug', 'professional')->first();

        $tenant = Tenant::factory()->create(['plan_id' => $starterPlan->id]);

        // Check initial feature
        $this->assertFalse(Feature::for($tenant)->active('customRoles'));

        // Upgrade plan
        $tenant->update(['plan_id' => $proPlan->id]);

        // Feature should now be available (cache was flushed by observer)
        $this->assertTrue(Feature::for($tenant)->active('customRoles'));
    }
}
