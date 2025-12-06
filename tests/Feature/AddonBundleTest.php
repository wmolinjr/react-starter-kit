<?php

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Models\Central\Addon;
use App\Models\Central\AddonBundle;
use App\Models\Central\Plan;
use App\Services\Central\AddonService;
use Database\Seeders\AddonBundleSeeder;
use Database\Seeders\AddonSeeder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * Addon Bundle Test Suite
 *
 * Tests bundle functionality including creation, pricing, and purchase.
 */
class AddonBundleTest extends TenantTestCase
{
    protected AddonService $addonService;

    protected Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->addonService = app(AddonService::class);
        $this->plan = Plan::where('slug', 'professional')->first();
        $this->tenant->update(['plan_id' => $this->plan->id]);

        // Seed addons and bundles for tests
        $this->seed(AddonSeeder::class);
        $this->seed(AddonBundleSeeder::class);
    }

    #[Test]
    public function can_create_bundle(): void
    {
        $bundle = AddonBundle::create([
            'slug' => 'test_bundle',
            'name' => ['en' => 'Test Bundle', 'pt_BR' => 'Pacote Teste'],
            'description' => ['en' => 'A test bundle'],
            'discount_percent' => 20,
            'active' => true,
        ]);

        $this->assertDatabaseHas('addon_bundles', [
            'slug' => 'test_bundle',
            'discount_percent' => 20,
        ], 'testing');
    }

    #[Test]
    public function bundle_has_many_addons(): void
    {
        $bundle = AddonBundle::where('slug', 'power_pack')->first();

        $this->assertNotNull($bundle);
        $this->assertCount(3, $bundle->addons);
    }

    #[Test]
    public function bundle_calculates_base_price(): void
    {
        $bundle = AddonBundle::where('slug', 'power_pack')->first();

        // Power Pack: storage_50gb ($49) + extra_users_5 ($49) + advanced_reports ($29) = $127
        $expectedBase = 4900 + 4900 + 2900; // 12700 cents

        $this->assertEquals($expectedBase, $bundle->getBasePriceMonthly());
    }

    #[Test]
    public function bundle_applies_discount(): void
    {
        $bundle = AddonBundle::where('slug', 'power_pack')->first();

        $base = $bundle->getBasePriceMonthly();
        $effective = $bundle->getEffectivePriceMonthly();

        // 20% discount
        $expectedEffective = (int) round($base * 0.8);

        $this->assertEquals($expectedEffective, $effective);
        $this->assertLessThan($base, $effective);
    }

    #[Test]
    public function bundle_calculates_savings(): void
    {
        $bundle = AddonBundle::where('slug', 'power_pack')->first();

        $savings = $bundle->getSavingsMonthly();

        // 20% of $127 = $25.40 savings
        $expectedSavings = (int) round($bundle->getBasePriceMonthly() * 0.2);

        $this->assertEquals($expectedSavings, $savings);
    }

    #[Test]
    public function bundle_is_available_for_plan(): void
    {
        $bundle = AddonBundle::where('slug', 'power_pack')->first();
        $professionalPlan = Plan::where('slug', 'professional')->first();
        $starterPlan = Plan::where('slug', 'starter')->first();

        $this->assertTrue($bundle->isAvailableForPlan($professionalPlan));
        $this->assertFalse($bundle->isAvailableForPlan($starterPlan));
    }

    #[Test]
    public function can_get_available_bundles_for_tenant(): void
    {
        $bundles = $this->addonService->getAvailableBundles($this->tenant);

        $this->assertGreaterThan(0, $bundles->count());

        // Power Pack and Enterprise Essentials should be available for Professional plan
        $slugs = $bundles->pluck('slug')->toArray();
        $this->assertContains('power_pack', $slugs);
    }

    #[Test]
    public function can_purchase_bundle(): void
    {
        $addons = $this->addonService->purchaseBundle(
            $this->tenant,
            'power_pack',
            BillingPeriod::MONTHLY
        );

        // Power Pack has 3 addons
        $this->assertCount(3, $addons);

        // All addons should be active
        foreach ($addons as $addon) {
            $this->assertTrue($addon->isActive());
            $this->assertNotNull($addon->metadata['bundle_purchase_id']);
            $this->assertEquals('power_pack', $addon->metadata['bundle_slug']);
        }

        // Verify in database
        $this->assertEquals(3, $this->tenant->activeAddons()->count());
    }

    #[Test]
    public function bundle_purchase_applies_discount_to_each_addon(): void
    {
        $addons = $this->addonService->purchaseBundle(
            $this->tenant,
            'power_pack',
            BillingPeriod::MONTHLY
        );

        // All addons should have 20% discount applied
        foreach ($addons as $addon) {
            $originalPrice = $addon->metadata['original_price'];
            $discountedPrice = $addon->price;

            $expectedDiscounted = (int) round($originalPrice * 0.8);
            $this->assertEquals($expectedDiscounted, $discountedPrice);
        }
    }

    #[Test]
    public function can_cancel_bundle(): void
    {
        $addons = $this->addonService->purchaseBundle(
            $this->tenant,
            'power_pack',
            BillingPeriod::MONTHLY
        );

        $purchaseId = $addons->first()->metadata['bundle_purchase_id'];

        $canceled = $this->addonService->cancelBundle($this->tenant, $purchaseId, 'Testing');

        $this->assertEquals(3, $canceled);
        $this->assertEquals(0, $this->tenant->activeAddons()->count());
    }

    #[Test]
    public function can_get_active_bundles(): void
    {
        $this->addonService->purchaseBundle($this->tenant, 'power_pack', BillingPeriod::MONTHLY);

        $activeBundles = $this->addonService->getActiveBundles($this->tenant);

        $this->assertCount(1, $activeBundles);
        $this->assertEquals('power_pack', $activeBundles->first()['bundle_slug']);
        $this->assertEquals(3, $activeBundles->first()['addon_count']);
    }

    #[Test]
    public function cannot_purchase_bundle_with_duplicate_feature(): void
    {
        // First, purchase advanced_reports individually
        $this->addonService->purchase($this->tenant, 'advanced_reports', 1, BillingPeriod::MONTHLY);

        // Try to purchase power_pack which includes advanced_reports
        $this->expectException(\App\Exceptions\Central\AddonException::class);
        $this->expectExceptionMessage('already have');

        $this->addonService->purchaseBundle($this->tenant, 'power_pack', BillingPeriod::MONTHLY);
    }

    #[Test]
    public function bundle_converts_to_frontend_format(): void
    {
        $bundle = AddonBundle::where('slug', 'power_pack')->first();
        $frontend = $bundle->toFrontend('en');

        $this->assertArrayHasKey('id', $frontend);
        $this->assertArrayHasKey('slug', $frontend);
        $this->assertArrayHasKey('name', $frontend);
        $this->assertArrayHasKey('discount_percent', $frontend);
        $this->assertArrayHasKey('price_monthly', $frontend);
        $this->assertArrayHasKey('savings_monthly', $frontend);
        $this->assertArrayHasKey('addons', $frontend);

        $this->assertEquals('Power Pack', $frontend['name']);
        $this->assertEquals(20, $frontend['discount_percent']);
        $this->assertCount(3, $frontend['addons']);
    }
}
