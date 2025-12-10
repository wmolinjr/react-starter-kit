<?php

namespace Tests\Feature;

use App\Enums\AddonStatus;
use App\Enums\AddonType;
use App\Enums\BillingPeriod;
use App\Exceptions\Central\AddonException;
use App\Exceptions\Central\AddonLimitExceededException;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\AddonSubscription;
use App\Services\Central\AddonService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * AddonService Test Suite
 *
 * Tests addon service with addon_subscriptions in central database.
 */
class AddonServiceTest extends TenantTestCase
{
    protected AddonService $addonService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->addonService = app(AddonService::class);

        // Update tenant's plan limits (don't change slug - conflicts with seeded plans)
        $this->tenant->plan->update([
            'limits' => [
                'storage' => 10000,
                'users' => 5,
                'projects' => 10,
            ],
        ]);
    }

    #[Test]
    public function can_get_addon_from_database(): void
    {
        $addon = $this->addonService->getAddon('storage_50gb');

        $this->assertNotNull($addon);
        $this->assertInstanceOf(\App\Models\Central\Addon::class, $addon);
        // Name is stored as JSON and auto-translated, use getRawOriginal to get array
        $rawName = $addon->getRawOriginal('name');
        $this->assertIsArray(json_decode($rawName, true));
        $this->assertEquals('Storage 50GB', json_decode($rawName, true)['en']);
        $this->assertEquals(AddonType::QUOTA, $addon->type);
    }

    #[Test]
    public function returns_null_for_nonexistent_addon(): void
    {
        $addon = $this->addonService->getAddon('nonexistent_addon');

        $this->assertNull($addon);
    }

    #[Test]
    public function can_get_available_addons_for_tenant(): void
    {
        $available = $this->addonService->getAvailableAddons($this->tenant);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $available);
        $this->assertTrue($available->contains('slug', 'storage_50gb'));
        $this->assertTrue($available->contains('slug', 'extra_users_5'));
    }

    #[Test]
    public function can_purchase_addon(): void
    {
        $addon = $this->addonService->purchase(
            $this->tenant,
            'storage_50gb',
            1,
            BillingPeriod::MONTHLY
        );

        $this->assertInstanceOf(AddonSubscription::class, $addon);
        $this->assertEquals('storage_50gb', $addon->addon_slug);
        $this->assertEquals(AddonType::QUOTA, $addon->addon_type);
        $this->assertEquals(AddonStatus::ACTIVE, $addon->status);
        $this->assertEquals(4900, $addon->price);
    }

    #[Test]
    public function can_purchase_addon_with_quantity(): void
    {
        $addon = $this->addonService->purchase(
            $this->tenant,
            'extra_users_5',
            5,
            BillingPeriod::MONTHLY
        );

        $this->assertEquals(5, $addon->quantity);
        $this->assertEquals(24500, $addon->total_price); // 5 x $49
    }

    #[Test]
    public function can_purchase_yearly_addon(): void
    {
        $addon = $this->addonService->purchase(
            $this->tenant,
            'storage_50gb',
            1,
            BillingPeriod::YEARLY
        );

        $this->assertEquals(BillingPeriod::YEARLY, $addon->billing_period);
        $this->assertEquals(49000, $addon->price); // Yearly price
    }

    #[Test]
    public function throws_exception_for_nonexistent_addon(): void
    {
        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('Addon not found');

        $this->addonService->purchase($this->tenant, 'nonexistent_addon');
    }

    #[Test]
    public function throws_exception_for_unavailable_plan(): void
    {
        // Create a plan with unique slug that is NOT in feature_custom_roles available_for_plans
        $testPlan = Plan::factory()->create(['slug' => 'test-plan-'.uniqid()]);
        $tenant = Tenant::factory()->create(['plan_id' => $testPlan->id]);

        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('not available for your plan');

        // feature_custom_roles is only for 'starter' plan
        $this->addonService->purchase($tenant, 'custom_roles');
    }

    #[Test]
    public function throws_exception_when_below_minimum_quantity(): void
    {
        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('Minimum quantity');

        $this->addonService->purchase($this->tenant, 'storage_50gb', 0);
    }

    #[Test]
    public function throws_exception_when_exceeding_maximum_quantity(): void
    {
        $this->expectException(AddonLimitExceededException::class);
        $this->expectExceptionMessage('Maximum quantity');

        $this->addonService->purchase($this->tenant, 'storage_50gb', 100); // max is 20
    }

    #[Test]
    public function throws_exception_for_duplicate_feature_addon(): void
    {
        // Use starter plan which allows feature_custom_roles
        $starterPlan = \App\Models\Central\Plan::where('slug', 'starter')->first();
        $this->tenant->update(['plan_id' => $starterPlan->id]);
        $this->tenant->refresh();

        // Purchase feature addon first
        $this->addonService->purchase($this->tenant, 'custom_roles');

        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('already have this addon');

        // Try to purchase again
        $this->addonService->purchase($this->tenant, 'custom_roles');
    }

    #[Test]
    public function throws_exception_for_invalid_billing_period(): void
    {
        // Use starter plan which allows feature_custom_roles
        $starterPlan = \App\Models\Central\Plan::where('slug', 'starter')->first();
        $this->tenant->update(['plan_id' => $starterPlan->id]);
        $this->tenant->refresh();

        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('Billing period');

        // feature_custom_roles only has monthly billing
        $this->addonService->purchase(
            $this->tenant,
            'custom_roles',
            1,
            BillingPeriod::YEARLY
        );
    }

    #[Test]
    public function can_update_addon_quantity(): void
    {
        $addon = AddonSubscription::factory()->forTenant($this->tenant)->active()->create([
            'addon_slug' => 'extra_users_5',
            'quantity' => 5,
        ]);

        $updated = $this->addonService->updateQuantity($addon, 10);

        $this->assertEquals(10, $updated->quantity);
    }

    #[Test]
    public function throws_exception_when_updating_below_minimum(): void
    {
        $addon = AddonSubscription::factory()->forTenant($this->tenant)->active()->create([
            'addon_slug' => 'extra_users_5',
            'quantity' => 5,
        ]);

        $this->expectException(AddonException::class);

        $this->addonService->updateQuantity($addon, 0);
    }

    #[Test]
    public function can_cancel_addon(): void
    {
        $addon = AddonSubscription::factory()->forTenant($this->tenant)->active()->create();

        $this->addonService->cancel($addon, 'Customer request');

        $addon->refresh();

        $this->assertTrue($addon->isCanceled());
        $this->assertNotNull($addon->canceled_at);
        $this->assertStringContainsString('Customer request', $addon->notes);
    }

    #[Test]
    public function can_reactivate_canceled_addon(): void
    {
        $addon = AddonSubscription::factory()->forTenant($this->tenant)->canceled()->create();

        $reactivated = $this->addonService->reactivate($addon);

        $this->assertTrue($reactivated->isActive());
        $this->assertNull($reactivated->canceled_at);
    }

    #[Test]
    public function throws_exception_when_reactivating_non_canceled(): void
    {
        $addon = AddonSubscription::factory()->forTenant($this->tenant)->active()->create();

        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('not canceled');

        $this->addonService->reactivate($addon);
    }

    #[Test]
    public function can_sync_tenant_limits(): void
    {
        AddonSubscription::factory()->forTenant($this->tenant)->active()->storage()->create([
            'quantity' => 1,
            'expires_at' => now()->addMonth(),
        ]);

        $this->addonService->syncTenantLimits($this->tenant);

        $this->tenant->refresh();
        // Effective limits are calculated and stored
        $this->assertIsArray($this->tenant->plan_limits_override);
    }

    #[Test]
    public function can_check_if_purchase_allowed(): void
    {
        $canPurchase = $this->addonService->canPurchase($this->tenant, 'storage_50gb');

        $this->assertTrue($canPurchase);
    }

    #[Test]
    public function returns_false_for_invalid_purchase(): void
    {
        $canPurchase = $this->addonService->canPurchase($this->tenant, 'nonexistent');

        $this->assertFalse($canPurchase);
    }

    #[Test]
    public function can_get_active_addons_by_type(): void
    {
        // Create addons of different types
        AddonSubscription::factory()->forTenant($this->tenant)->active()->storage()->create();
        AddonSubscription::factory()->forTenant($this->tenant)->active()->users()->create();
        AddonSubscription::factory()->forTenant($this->tenant)->active()->feature()->create();

        $byType = $this->addonService->getActiveAddonsByType($this->tenant);

        // Both storage and users are QUOTA type now
        $this->assertArrayHasKey('quota', $byType);
        $this->assertArrayHasKey('feature', $byType);
        $this->assertCount(2, $byType['quota']); // storage + users
        $this->assertCount(1, $byType['feature']);
    }

    #[Test]
    public function can_calculate_total_monthly_cost(): void
    {
        AddonSubscription::factory()->forTenant($this->tenant)->active()->monthly()->create([
            'price' => 1000,
            'quantity' => 2,
        ]);

        AddonSubscription::factory()->forTenant($this->tenant)->active()->monthly()->create([
            'price' => 500,
            'quantity' => 1,
        ]);

        $total = $this->addonService->calculateTotalMonthlyCost($this->tenant);

        $this->assertEquals(2500, $total); // 2000 + 500
    }

    #[Test]
    public function yearly_cost_is_divided_by_twelve(): void
    {
        AddonSubscription::factory()->forTenant($this->tenant)->active()->yearly()->create([
            'price' => 12000, // $120/year
            'quantity' => 1,
        ]);

        $total = $this->addonService->calculateTotalMonthlyCost($this->tenant);

        $this->assertEquals(1000, $total); // $10/month
    }
}
