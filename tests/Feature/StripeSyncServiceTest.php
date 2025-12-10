<?php

namespace Tests\Feature;

use App\Models\Central\Addon;
use App\Models\Central\AddonBundle;
use App\Services\Central\StripeSyncService;
use Database\Seeders\AddonBundleSeeder;
use Database\Seeders\AddonSeeder;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Stripe\Price;
use Stripe\Service\PriceService;
use Stripe\Service\ProductService;
use Stripe\StripeClient;
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

        // Get addon without stripe_product_id
        $addon = Addon::where('active', true)->whereNull('stripe_product_id')->first();
        $this->assertNotNull($addon, 'Expected at least one addon without stripe_product_id');

        $preview = $service->dryRun($addon->slug);

        $this->assertCount(1, $preview);
        $this->assertStringContainsString('Create Product', $preview[0]['actions'][0]);
    }

    #[Test]
    public function dry_run_shows_update_product_for_synced_addons(): void
    {
        // Create an addon with stripe_product_id
        $addon = Addon::factory()->create([
            'stripe_product_id' => 'prod_test123',
            'active' => true,
        ]);

        $service = app(StripeSyncService::class);
        $preview = $service->dryRun($addon->slug);

        $this->assertCount(1, $preview);
        $this->assertStringContainsString('Update Product', $preview[0]['actions'][0]);
    }

    #[Test]
    public function dry_run_shows_create_price_for_addons_without_prices(): void
    {
        $service = app(StripeSyncService::class);

        // Get addon with prices but without stripe price IDs
        $addon = Addon::where('active', true)
            ->whereNotNull('price_monthly')
            ->whereNull('stripe_price_monthly_id')
            ->first();

        $this->assertNotNull($addon, 'Expected at least one addon with price but no stripe price ID');

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

        // Get bundle without stripe_product_id
        $bundle = AddonBundle::where('active', true)->whereNull('stripe_product_id')->first();
        $this->assertNotNull($bundle, 'Expected at least one bundle without stripe_product_id');

        $preview = $service->dryRunBundles($bundle->slug);

        $this->assertCount(1, $preview);
        $this->assertStringContainsString('Create Product', $preview[0]['actions'][0]);
    }

    #[Test]
    public function dry_run_bundles_shows_update_product_for_synced_bundles(): void
    {
        // Create a bundle with stripe_product_id
        $bundle = AddonBundle::factory()->create([
            'stripe_product_id' => 'prod_bundle_test123',
            'active' => true,
        ]);

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
            ->whereNull('stripe_price_monthly_id')
            ->with('addons')
            ->first();

        $this->assertNotNull($bundle, 'Expected at least one bundle without stripe price IDs');

        $preview = $service->dryRunBundles($bundle->slug);

        // If bundle has addons with prices, should show price actions
        if ($bundle->getEffectivePriceMonthly() > 0) {
            $actions = implode(' ', $preview[0]['actions']);
            $this->assertStringContainsString('Monthly Price', $actions);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Bundle Sync Tests (with Mocked Stripe)
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function sync_bundle_creates_product_and_prices(): void
    {
        // Create a mock StripeClient
        $mockStripe = Mockery::mock(StripeClient::class);

        // Mock products service
        $mockProductService = Mockery::mock(ProductService::class);
        // Use stdClass to avoid Stripe SDK static method issues with Mockery
        $mockProduct = (object) ['id' => 'prod_test_bundle'];

        $mockProductService->shouldReceive('create')
            ->once()
            ->andReturn($mockProduct);

        $mockStripe->products = $mockProductService;

        // Mock prices service
        $mockPriceService = Mockery::mock(PriceService::class);

        $mockPriceService->shouldReceive('create')
            ->andReturnUsing(function ($data) {
                // Use stdClass to avoid Stripe SDK static method issues with Mockery
                return (object) ['id' => 'price_'.($data['metadata']['billing_period'] ?? 'test')];
            });

        $mockStripe->prices = $mockPriceService;

        // Create service with mocked client
        $service = new StripeSyncService;
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('stripe');
        $property->setAccessible(true);
        $property->setValue($service, $mockStripe);

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
        $this->assertEquals('prod_test_bundle', $result['stripe_product_id']);

        // Verify database was updated
        $bundle->refresh();
        $this->assertEquals('prod_test_bundle', $bundle->stripe_product_id);
    }

    #[Test]
    public function sync_bundle_updates_existing_product(): void
    {
        // Create a mock StripeClient
        $mockStripe = Mockery::mock(StripeClient::class);

        // Mock products service - should update, not create
        $mockProductService = Mockery::mock(ProductService::class);
        // Use stdClass to avoid Stripe SDK static method issues with Mockery
        $mockProduct = (object) ['id' => 'prod_existing'];

        $mockProductService->shouldReceive('update')
            ->once()
            ->with('prod_existing', Mockery::any())
            ->andReturn($mockProduct);

        $mockProductService->shouldNotReceive('create');

        $mockStripe->products = $mockProductService;

        // Mock prices service (no prices to create since IDs exist)
        $mockPriceService = Mockery::mock(PriceService::class);
        $mockStripe->prices = $mockPriceService;

        // Create service with mocked client
        $service = new StripeSyncService;
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('stripe');
        $property->setAccessible(true);
        $property->setValue($service, $mockStripe);

        // Create a bundle that already has stripe IDs
        $addon = Addon::factory()->create([
            'price_monthly' => 5000,
            'active' => true,
        ]);

        $bundle = AddonBundle::factory()->create([
            'stripe_product_id' => 'prod_existing',
            'stripe_price_monthly_id' => 'price_existing_monthly',
            'stripe_price_yearly_id' => 'price_existing_yearly',
            'active' => true,
        ]);
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

        // Create a mock StripeClient that tracks calls
        $mockStripe = Mockery::mock(StripeClient::class);

        $mockProductService = Mockery::mock(ProductService::class);
        $mockProductService->shouldReceive('create')
            ->times(2) // Only 2 active bundles
            ->andReturnUsing(function () {
                // Use stdClass to avoid Stripe SDK static method issues with Mockery
                return (object) ['id' => 'prod_'.uniqid()];
            });

        $mockStripe->products = $mockProductService;

        $mockPriceService = Mockery::mock(PriceService::class);
        $mockPriceService->shouldReceive('create')->andReturnUsing(function ($data) {
            // Use stdClass to avoid Stripe SDK static method issues with Mockery
            return (object) ['id' => 'price_'.uniqid()];
        });
        $mockStripe->prices = $mockPriceService;

        $service = new StripeSyncService;
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('stripe');
        $property->setAccessible(true);
        $property->setValue($service, $mockStripe);

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
