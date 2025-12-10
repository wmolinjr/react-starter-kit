<?php

namespace Tests\Feature;

use App\Models\Central\AddonSubscription;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Addon Commands Test Suite
 *
 * Tests addon commands with addon_subscriptions in central database.
 */
class AddonCommandsTest extends TestCase
{
    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = Plan::factory()->create([
            'slug' => 'test-plan-'.uniqid(),
            'limits' => [
                'storage' => 10000,
                'users' => 5,
            ],
        ]);
    }

    #[Test]
    public function sync_addons_command_works(): void
    {
        $tenant = Tenant::factory()->create([
            'plan_id' => $this->plan->id,
        ]);

        // Create addon for tenant (now in central database)
        AddonSubscription::factory()->forTenant($tenant)->active()->storage()->create();

        // Use --all flag since stripe_id column might not exist in test db
        $this->artisan('addons:sync', ['--all' => true])
            ->assertSuccessful();
    }

    #[Test]
    public function sync_addons_can_target_specific_tenant(): void
    {
        $tenant = Tenant::factory()->create([
            'plan_id' => $this->plan->id,
        ]);

        $this->artisan('addons:sync', ['--tenant' => $tenant->id])
            ->assertSuccessful();
    }

    #[Test]
    public function sync_addons_all_flag_includes_all_tenants(): void
    {
        Tenant::factory()->count(3)->create([
            'plan_id' => $this->plan->id,
        ]);

        $this->artisan('addons:sync', ['--all' => true])
            ->assertSuccessful();
    }
}
