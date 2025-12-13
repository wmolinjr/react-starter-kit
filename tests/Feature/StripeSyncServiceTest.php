<?php

namespace Tests\Feature;

use App\Models\Central\Addon;
use App\Models\Central\AddonBundle;
use App\Services\Central\StripeSyncService;
use App\Services\Payment\Gateways\StripeGateway;
use App\Services\Payment\PaymentGatewayManager;
use Database\Seeders\AddonBundleSeeder;
use Database\Seeders\AddonSeeder;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * StripeSyncService Test Suite
 *
 * Tests the Stripe synchronization service for addons and bundles.
 */
class StripeSyncServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Seed addons and bundles for tests
        $this->seed(AddonSeeder::class);
        $this->seed(AddonBundleSeeder::class);
    }

    /**
     * Create a mock StripeSyncService with mocked gateway
     */
    protected function createMockedService(array $productResponses = [], array $priceResponses = []): StripeSyncService
    {
        $mockGateway = Mockery::mock(StripeGateway::class);
        $mockGateway->shouldReceive('isAvailable')->andReturn(true);
        $mockGateway->shouldReceive('getIdentifier')->andReturn('stripe');

        foreach ($productResponses as $method => $response) {
            $mockGateway->shouldReceive($method)->andReturn($response);
        }

        foreach ($priceResponses as $method => $response) {
            $mockGateway->shouldReceive($method)->andReturn($response);
        }

        $mockManager = Mockery::mock(PaymentGatewayManager::class);
        $mockManager->shouldReceive('stripe')->andReturn($mockGateway);
        $mockManager->shouldReceive('getDefaultDriver')->andReturn('stripe');

        return new StripeSyncService($mockManager);
    }

    /*
    |--------------------------------------------------------------------------
    | Addon Dry Run Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function dry_run_returns_preview_for_all_addons(): void
    {
        $service = app(StripeSyncService::class);

        $preview = $service->dryRun();

        $this->assertIsArray($preview);
        $this->assertNotEmpty($preview);

        // Check structure of first item
        $first = $preview[0];
        $this->assertArrayHasKey('slug', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('locale', $first);
        $this->assertArrayHasKey('actions', $first);
    }

    #[Test]
    public function dry_run_shows_create_product_for_new_addons(): void
    {
        $service = app(StripeSyncService::class);

        // Get addon without provider_product_ids
        $addon = Addon::where('active', true)
            ->where(function ($q) {
                $q->whereNull('provider_product_ids')
                    ->orWhereJsonLength('provider_product_ids', 0);
            })
            ->first();
        $this->assertNotNull($addon, 'Expected at least one addon without provider_product_ids');

        $preview = $service->dryRun($addon->slug);

        $this->assertCount(1, $preview);
        $this->assertStringContainsString('Create Product', $preview[0]['actions'][0]);
    }

    #[Test]
    public function dry_run_shows_update_product_for_synced_addons(): void
    {
        // Create an addon with provider_product_ids
        $addon = Addon::factory()->create([
            'active' => true,
        ]);
        $addon->setProviderProductId('stripe', 'prod_test123');

        $service = app(StripeSyncService::class);
        $preview = $service->dryRun($addon->slug);

        $this->assertCount(1, $preview);
        $this->assertStringContainsString('Update Product', $preview[0]['actions'][0]);
    }

    #[Test]
    public function dry_run_shows_create_price_for_addons_without_prices(): void
    {
        $service = app(StripeSyncService::class);

        // Get addon with prices but without provider price IDs
        $addon = Addon::where('active', true)
            ->whereNotNull('price_monthly')
            ->where(function ($q) {
                $q->whereNull('provider_price_ids')
                    ->orWhereJsonLength('provider_price_ids', 0);
            })
            ->first();

        $this->assertNotNull($addon, 'Expected at least one addon with price but no provider price ID');

        $preview = $service->dryRun($addon->slug);

        // Should have action to create monthly price
        $actions = implode(' ', $preview[0]['actions']);
        $this->assertStringContainsString('Monthly Price', $actions);
    }

    /*
    |--------------------------------------------------------------------------
    | Bundle Dry Run Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function dry_run_bundles_returns_preview_for_all_bundles(): void
    {
        $service = app(StripeSyncService::class);

        $preview = $service->dryRunBundles();

        $this->assertIsArray($preview);
        $this->assertNotEmpty($preview);

        // Check structure of first item
        $first = $preview[0];
        $this->assertArrayHasKey('slug', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('locale', $first);
        $this->assertArrayHasKey('addon_count', $first);
        $this->assertArrayHasKey('discount_percent', $first);
        $this->assertArrayHasKey('actions', $first);
    }

    #[Test]
    public function dry_run_bundles_shows_create_product_for_new_bundles(): void
    {
        $service = app(StripeSyncService::class);

        // Get bundle without provider_product_ids
        $bundle = AddonBundle::where('active', true)
            ->where(function ($q) {
                $q->whereNull('provider_product_ids')
                    ->orWhereJsonLength('provider_product_ids', 0);
            })
            ->first();
        $this->assertNotNull($bundle, 'Expected at least one bundle without provider_product_ids');

        $preview = $service->dryRunBundles($bundle->slug);

        $this->assertCount(1, $preview);
        $this->assertStringContainsString('Create Product', $preview[0]['actions'][0]);
    }

    #[Test]
    public function dry_run_bundles_shows_update_product_for_synced_bundles(): void
    {
        // Create a bundle with provider_product_ids
        $bundle = AddonBundle::factory()->create([
            'active' => true,
        ]);
        $bundle->setProviderProductId('stripe', 'prod_bundle_test123');

        $service = app(StripeSyncService::class);
        $preview = $service->dryRunBundles($bundle->slug);

        $this->assertCount(1, $preview);
        $this->assertStringContainsString('Update Product', $preview[0]['actions'][0]);
    }

    #[Test]
    public function dry_run_bundles_shows_prices_to_create(): void
    {
        $service = app(StripeSyncService::class);

        // Get bundle that should have prices calculated
        $bundle = AddonBundle::where('active', true)
            ->where(function ($q) {
                $q->whereNull('provider_price_ids')
                    ->orWhereJsonLength('provider_price_ids', 0);
            })
            ->with('addons')
            ->first();

        $this->assertNotNull($bundle, 'Expected at least one bundle without provider price IDs');

        $preview = $service->dryRunBundles($bundle->slug);

        // If bundle has addons with prices, should show price actions
        if ($bundle->getEffectivePriceMonthly() > 0) {
            $actions = implode(' ', $preview[0]['actions']);
            $this->assertStringContainsString('Monthly Price', $actions);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Bundle Sync Tests (with Mocked Gateway)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function sync_bundle_creates_product_and_prices(): void
    {
        $service = $this->createMockedService(
            productResponses: [
                'createProduct' => ['id' => 'prod_test_bundle'],
            ],
            priceResponses: [
                'createPrice' => ['id' => 'price_test'],
            ]
        );

        // Create a bundle with addons that have prices
        $addon1 = Addon::factory()->create([
            'price_monthly' => 5000,
            'price_yearly' => 50000,
            'active' => true,
        ]);
        $addon2 = Addon::factory()->create([
            'price_monthly' => 3000,
            'price_yearly' => 30000,
            'active' => true,
        ]);

        $bundle = AddonBundle::factory()->create([
            'active' => true,
            'discount_percent' => 10,
        ]);
        $bundle->addons()->attach([
            $addon1->id => ['quantity' => 1, 'sort_order' => 0],
            $addon2->id => ['quantity' => 1, 'sort_order' => 1],
        ]);
        $bundle->load('addons');

        // Sync bundle
        $result = $service->syncBundle($bundle);

        $this->assertTrue($result['product_synced']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals('prod_test_bundle', $result['provider_product_id']);

        // Verify database was updated with provider-agnostic column
        $bundle->refresh();
        $this->assertEquals('prod_test_bundle', $bundle->getProviderProductId('stripe'));
    }

    #[Test]
    public function sync_bundle_updates_existing_product(): void
    {
        $service = $this->createMockedService(
            productResponses: [
                'updateProduct' => ['id' => 'prod_existing'],
            ]
        );

        // Create a bundle that already has provider IDs
        $addon = Addon::factory()->create([
            'price_monthly' => 5000,
            'active' => true,
        ]);

        $bundle = AddonBundle::factory()->create([
            'active' => true,
        ]);
        $bundle->setProviderProductId('stripe', 'prod_existing');
        $bundle->setProviderPriceId('stripe', 'monthly', 'price_existing_monthly');
        $bundle->setProviderPriceId('stripe', 'yearly', 'price_existing_yearly');
        $bundle->addons()->attach($addon->id, ['quantity' => 1, 'sort_order' => 0]);
        $bundle->load('addons');

        // Sync bundle
        $result = $service->syncBundle($bundle);

        $this->assertTrue($result['product_synced']);
        $this->assertEmpty($result['errors']);
    }

    #[Test]
    public function sync_all_bundles_syncs_only_active_bundles(): void
    {
        // Deactivate all existing bundles first
        AddonBundle::query()->update(['active' => false]);

        // Create specific test bundles
        $addon = Addon::factory()->create(['price_monthly' => 1000, 'active' => true]);

        $activeBundle1 = AddonBundle::factory()->create(['active' => true]);
        $activeBundle1->addons()->attach($addon->id, ['quantity' => 1, 'sort_order' => 0]);

        $activeBundle2 = AddonBundle::factory()->create(['active' => true]);
        $activeBundle2->addons()->attach($addon->id, ['quantity' => 1, 'sort_order' => 0]);

        $inactiveBundle = AddonBundle::factory()->create(['active' => false]);
        $inactiveBundle->addons()->attach($addon->id, ['quantity' => 1, 'sort_order' => 0]);

        $service = $this->createMockedService(
            productResponses: [
                'createProduct' => ['id' => 'prod_'.uniqid()],
            ],
            priceResponses: [
                'createPrice' => ['id' => 'price_'.uniqid()],
            ]
        );

        $results = $service->syncAllBundles();

        $this->assertCount(2, $results);
    }

    /*
    |--------------------------------------------------------------------------
    | Locale Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function service_uses_default_english_locale(): void
    {
        $service = app(StripeSyncService::class);

        $this->assertEquals('en', $service->getLocale());
    }

    #[Test]
    public function service_can_change_locale(): void
    {
        $service = app(StripeSyncService::class);

        $service->setLocale('pt_BR');

        $this->assertEquals('pt_BR', $service->getLocale());
    }

    #[Test]
    public function dry_run_respects_locale_parameter(): void
    {
        $service = app(StripeSyncService::class);

        $previewEn = $service->dryRun(null, 'en');
        $previewPt = $service->dryRun(null, 'pt_BR');

        // Both should have locale field set correctly
        if (! empty($previewEn)) {
            $this->assertEquals('en', $previewEn[0]['locale']);
        }
        if (! empty($previewPt)) {
            $this->assertEquals('pt_BR', $previewPt[0]['locale']);
        }
    }

    #[Test]
    public function dry_run_bundles_respects_locale_parameter(): void
    {
        $service = app(StripeSyncService::class);

        $previewEn = $service->dryRunBundles(null, 'en');
        $previewPt = $service->dryRunBundles(null, 'pt_BR');

        // Both should have locale field set correctly
        if (! empty($previewEn)) {
            $this->assertEquals('en', $previewEn[0]['locale']);
        }
        if (! empty($previewPt)) {
            $this->assertEquals('pt_BR', $previewPt[0]['locale']);
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
