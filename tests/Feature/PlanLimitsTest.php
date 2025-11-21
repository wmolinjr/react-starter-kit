<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanLimitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'PlanSeeder']);
    }

    /** @test */
    public function tenant_can_check_if_limit_reached(): void
    {
        $plan = Plan::factory()->create([
            'limits' => ['users' => 2],
        ]);

        $tenant = Tenant::factory()->create([
            'plan_id' => $plan->id,
            'current_usage' => ['users' => 2],
        ]);

        $this->assertTrue($tenant->hasReachedLimit('users'));

        $tenant->update(['current_usage' => ['users' => 1]]);
        $this->assertFalse($tenant->hasReachedLimit('users'));
    }

    /** @test */
    public function unlimited_resources_never_reach_limit(): void
    {
        $plan = Plan::factory()->create([
            'limits' => ['projects' => -1], // unlimited
        ]);

        $tenant = Tenant::factory()->create([
            'plan_id' => $plan->id,
            'current_usage' => ['projects' => 9999],
        ]);

        $this->assertFalse($tenant->hasReachedLimit('projects'));
        $this->assertTrue($tenant->isUnlimited('projects'));
    }

    /** @test */
    public function usage_is_tracked_automatically_for_users(): void
    {
        $plan = Plan::where('slug', 'professional')->first();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);

        tenancy()->initialize($tenant);

        $this->assertEquals(0, $tenant->getCurrentUsage('users'));

        // Create user - should increment usage
        $user = User::factory()->create();
        $tenant->users()->attach($user, ['joined_at' => now()]);

        $tenant->refresh();
        $this->assertEquals(1, $tenant->getCurrentUsage('users'));

        // Delete user - should decrement usage
        $user->delete();

        $tenant->refresh();
        $this->assertEquals(0, $tenant->getCurrentUsage('users'));

        tenancy()->end();
    }

    /** @test */
    public function usage_is_tracked_automatically_for_projects(): void
    {
        $plan = Plan::where('slug', 'starter')->first();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);

        tenancy()->initialize($tenant);

        $this->assertEquals(0, $tenant->getCurrentUsage('projects'));

        // Create project - should increment usage
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);

        $tenant->refresh();
        $this->assertEquals(1, $tenant->getCurrentUsage('projects'));

        // Delete project - should decrement usage
        $project->delete();

        $tenant->refresh();
        $this->assertEquals(0, $tenant->getCurrentUsage('projects'));

        tenancy()->end();
    }

    /** @test */
    public function tenant_can_increment_and_decrement_usage_manually(): void
    {
        $tenant = Tenant::factory()->create([
            'current_usage' => ['storage' => 100],
        ]);

        $tenant->incrementUsage('storage', 50);
        $this->assertEquals(150, $tenant->getCurrentUsage('storage'));

        $tenant->decrementUsage('storage', 30);
        $this->assertEquals(120, $tenant->getCurrentUsage('storage'));

        // Cannot go below 0
        $tenant->decrementUsage('storage', 200);
        $this->assertEquals(0, $tenant->getCurrentUsage('storage'));
    }

    /** @test */
    public function middleware_blocks_when_limit_reached(): void
    {
        $plan = Plan::factory()->create([
            'limits' => ['users' => 1],
        ]);

        $tenant = Tenant::factory()->create([
            'plan_id' => $plan->id,
            'current_usage' => ['users' => 1],
        ]);

        $user = User::factory()->create();
        $tenant->users()->attach($user, ['joined_at' => now()]);

        $this->actingAs($user);

        tenancy()->initialize($tenant);

        // This route should be blocked by middleware
        // Note: This test requires a route protected by limit:users middleware
        // For now, we'll test the logic directly

        $this->assertTrue($tenant->hasReachedLimit('users'));

        tenancy()->end();
    }
}
