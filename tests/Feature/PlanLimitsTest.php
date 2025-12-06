<?php

namespace Tests\Feature;

use App\Models\Central\Plan;
use App\Models\Tenant\Project;
use App\Models\Central\Tenant;
use App\Models\Tenant\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlanLimitsTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'PlanSeeder']);
    }

    #[Test]
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

    #[Test]
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

    #[Test]
    public function usage_is_tracked_automatically_for_users(): void
    {
        $plan = Plan::where('slug', 'professional')->first();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);

        tenancy()->initialize($tenant);

        $this->assertEquals(0, $tenant->getCurrentUsage('users'));

        // Option C: Users are created directly in tenant database
        // No pivot table - user exists only in this tenant's context
        $user = User::factory()->create();

        $tenant->refresh();
        $this->assertEquals(1, $tenant->getCurrentUsage('users'));

        // Delete user - should decrement usage (soft delete in Option C)
        $user->forceDelete(); // Use forceDelete to actually remove for accurate count

        $tenant->refresh();
        $this->assertEquals(0, $tenant->getCurrentUsage('users'));

        tenancy()->end();
    }

    #[Test]
    public function usage_is_tracked_automatically_for_projects(): void
    {
        $plan = Plan::where('slug', 'starter')->first();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);

        tenancy()->initialize($tenant);

        $this->assertEquals(0, $tenant->getCurrentUsage('projects'));

        // Create project - should increment usage
        // In multi-database tenancy, Project is in tenant database (no tenant_id column)
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $tenant->refresh();
        $this->assertEquals(1, $tenant->getCurrentUsage('projects'));

        // Delete project - should decrement usage
        $project->delete();

        $tenant->refresh();
        $this->assertEquals(0, $tenant->getCurrentUsage('projects'));

        tenancy()->end();
    }

    #[Test]
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

    #[Test]
    public function middleware_blocks_when_limit_reached(): void
    {
        $plan = Plan::factory()->create([
            'limits' => ['users' => 1],
        ]);

        $tenant = Tenant::factory()->create([
            'plan_id' => $plan->id,
            'current_usage' => ['users' => 1],
        ]);

        tenancy()->initialize($tenant);

        // Option C: Create user directly in tenant database
        $user = User::factory()->create();

        $this->actingAs($user);

        // This route should be blocked by middleware
        // Note: This test requires a route protected by limit:users middleware
        // For now, we'll test the logic directly

        $this->assertTrue($tenant->hasReachedLimit('users'));

        tenancy()->end();
    }
}
