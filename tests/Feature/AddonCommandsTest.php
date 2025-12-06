<?php

namespace Tests\Feature;

use App\Enums\AddonStatus;
use App\Enums\AddonType;
use App\Enums\BillingPeriod;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\AddonSubscription;
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

    #[Test]
    public function migrate_overrides_dry_run_works(): void
    {
        $tenant = Tenant::factory()->create([
            'plan_id' => $this->plan->id,
            'plan_limits_override' => [
                'storage' => 50000,
                'users' => 10,
            ],
        ]);

        $this->artisan('addons:migrate-overrides', ['--dry-run' => true])
            ->assertSuccessful();

        // Verify no addons created
        $this->assertCount(0, $tenant->addons);

        // Should not have cleared overrides
        $tenant->refresh();
        $this->assertNotEmpty($tenant->plan_limits_override);
    }

    #[Test]
    public function migrate_overrides_creates_addons(): void
    {
        $tenant = Tenant::factory()->create([
            'plan_id' => $this->plan->id,
            'plan_limits_override' => [
                'storage' => 50000,
            ],
        ]);

        $this->artisan('addons:migrate-overrides')
            ->assertSuccessful();

        // Verify addon created for tenant
        $addon = $tenant->addons()->first();
        $this->assertNotNull($addon);
        $this->assertEquals('manual_storage_override', $addon->addon_slug);
        $this->assertEquals(AddonType::QUOTA, $addon->addon_type);
        $this->assertEquals(BillingPeriod::MANUAL, $addon->billing_period);
        $this->assertEquals(50000, $addon->quantity);

        // Should have cleared overrides (set to empty array)
        $tenant->refresh();
        $this->assertEmpty($tenant->plan_limits_override);
    }

    #[Test]
    public function migrate_overrides_can_target_specific_tenant(): void
    {
        $tenant1 = Tenant::factory()->create([
            'plan_id' => $this->plan->id,
            'plan_limits_override' => ['storage' => 50000],
        ]);

        $tenant2 = Tenant::factory()->create([
            'plan_id' => $this->plan->id,
            'plan_limits_override' => ['users' => 20],
        ]);

        $this->artisan('addons:migrate-overrides', ['--tenant' => $tenant1->id])
            ->assertSuccessful();

        // Only tenant1 should have addon
        $this->assertCount(1, $tenant1->addons);
        $this->assertCount(0, $tenant2->addons);

        // Only tenant1 overrides should be cleared
        $tenant1->refresh();
        $tenant2->refresh();
        $this->assertEmpty($tenant1->plan_limits_override);
        $this->assertNotEmpty($tenant2->plan_limits_override);
    }
}
