<?php

namespace Tests\Feature;

use App\Models\Central\Plan;
use App\Models\Universal\Role;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use Laravel\Pennant\Feature;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PermissionSyncTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        // Sync permissions
        $this->artisan('permissions:sync');

        // Seed plans
        $this->artisan('db:seed', ['--class' => 'PlanSeeder']);
        $this->artisan('plans:sync-permissions');
    }

    #[Test]
    public function upgrading_plan_enables_new_permissions(): void
    {
        $starterPlan = Plan::where('slug', 'starter')->first();
        $proPlan = Plan::where('slug', 'professional')->first();

        $tenant = Tenant::factory()->create(['plan_id' => $starterPlan->id]);

        // Initialize tenancy (multi-database: no setPermissionsTeamId needed)
        tenancy()->initialize($tenant);

        // Regenerate permissions for starter plan
        $tenant->regeneratePlanPermissions();
        $initialPermissions = $tenant->getPlanEnabledPermissions();

        // Upgrade to Professional
        $tenant->update(['plan_id' => $proPlan->id]);

        // Permissions should be updated automatically by observer
        $tenant->refresh();
        $newPermissions = $tenant->getPlanEnabledPermissions();

        $this->assertGreaterThan(count($initialPermissions), count($newPermissions));
        $this->assertContains('roles:view', $newPermissions);
        $this->assertContains('apiTokens:view', $newPermissions);

        tenancy()->end();
    }

    #[Test]
    public function downgrading_plan_removes_permissions(): void
    {
        $proPlan = Plan::where('slug', 'professional')->first();
        $starterPlan = Plan::where('slug', 'starter')->first();

        $tenant = Tenant::factory()->create(['plan_id' => $proPlan->id]);

        tenancy()->initialize($tenant);

        $tenant->regeneratePlanPermissions();
        $proPermissions = $tenant->getPlanEnabledPermissions();

        // Downgrade to Starter
        $tenant->update(['plan_id' => $starterPlan->id]);

        $tenant->refresh();
        $starterPermissions = $tenant->getPlanEnabledPermissions();

        $this->assertLessThan(count($proPermissions), count($starterPermissions));
        $this->assertNotContains('roles:view', $starterPermissions);
        $this->assertNotContains('apiTokens:view', $starterPermissions);

        tenancy()->end();
    }

    #[Test]
    public function permissions_are_cached_in_plan_enabled_permissions_field(): void
    {
        $plan = Plan::where('slug', 'professional')->first();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);

        tenancy()->initialize($tenant);

        $this->assertNull($tenant->plan_enabled_permissions);

        $permissions = $tenant->regeneratePlanPermissions();

        $tenant->refresh();
        $this->assertNotNull($tenant->plan_enabled_permissions);
        $this->assertEquals($permissions, $tenant->plan_enabled_permissions);

        tenancy()->end();
    }

    #[Test]
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
