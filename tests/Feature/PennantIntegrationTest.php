<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class PennantIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed plans
        $this->artisan('db:seed', ['--class' => 'PlanSeeder']);
    }

    /** @test */
    public function starter_plan_has_limited_features(): void
    {
        $plan = Plan::where('slug', 'starter')->first();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);

        $this->assertFalse(Feature::for($tenant)->active('customRoles'));
        $this->assertFalse(Feature::for($tenant)->active('apiAccess'));
        $this->assertFalse(Feature::for($tenant)->active('advancedReports'));
    }

    /** @test */
    public function professional_plan_has_more_features(): void
    {
        $plan = Plan::where('slug', 'professional')->first();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);

        $this->assertTrue(Feature::for($tenant)->active('customRoles'));
        $this->assertTrue(Feature::for($tenant)->active('apiAccess'));
        $this->assertFalse(Feature::for($tenant)->active('advancedReports'));
    }

    /** @test */
    public function enterprise_plan_has_all_features(): void
    {
        $plan = Plan::where('slug', 'enterprise')->first();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);

        $this->assertTrue(Feature::for($tenant)->active('customRoles'));
        $this->assertTrue(Feature::for($tenant)->active('apiAccess'));
        $this->assertTrue(Feature::for($tenant)->active('advancedReports'));
        $this->assertTrue(Feature::for($tenant)->active('sso'));
        $this->assertTrue(Feature::for($tenant)->active('whiteLabel'));
    }

    /** @test */
    public function pennant_returns_correct_limits_as_rich_values(): void
    {
        $plan = Plan::where('slug', 'professional')->first();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);

        $this->assertEquals(50, Feature::for($tenant)->value('maxUsers'));
        $this->assertEquals(-1, Feature::for($tenant)->value('maxProjects')); // unlimited
        $this->assertEquals(10240, Feature::for($tenant)->value('storageLimit'));
    }

    /** @test */
    public function trial_tenant_gets_all_features(): void
    {
        $plan = Plan::where('slug', 'starter')->first();
        $tenant = Tenant::factory()->create([
            'plan_id' => $plan->id,
            'trial_ends_at' => now()->addDays(14),
        ]);

        // Even though Starter doesn't have these features,
        // trial tenants should get access to all
        $this->assertTrue(Feature::for($tenant)->active('customRoles'));
        $this->assertTrue(Feature::for($tenant)->active('apiAccess'));
    }

    /** @test */
    public function feature_overrides_take_precedence(): void
    {
        $plan = Plan::where('slug', 'starter')->first();
        $tenant = Tenant::factory()->create([
            'plan_id' => $plan->id,
            'plan_features_override' => [
                'customRoles' => true, // Enable for this tenant only
            ],
        ]);

        $this->assertTrue(Feature::for($tenant)->active('customRoles'));
        $this->assertFalse(Feature::for($tenant)->active('apiAccess'));
    }

    /** @test */
    public function limit_overrides_take_precedence(): void
    {
        $plan = Plan::where('slug', 'starter')->first();
        $tenant = Tenant::factory()->create([
            'plan_id' => $plan->id,
            'plan_limits_override' => [
                'users' => 10, // Override limit
            ],
        ]);

        $this->assertEquals(10, Feature::for($tenant)->value('maxUsers'));
    }
}
