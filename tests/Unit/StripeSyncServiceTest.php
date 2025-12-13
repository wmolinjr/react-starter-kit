<?php

namespace Tests\Unit;

use App\Models\Central\Addon;
use App\Services\Central\StripeSyncService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StripeSyncServiceTest extends TestCase
{
    protected StripeSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Clean up any existing addons to avoid unique constraint violations
        Addon::query()->delete();

        $this->service = app(StripeSyncService::class);
    }

    #[Test]
    public function it_sets_and_gets_locale(): void
    {
        $this->assertEquals('en', $this->service->getLocale());

        $this->service->setLocale('pt_BR');
        $this->assertEquals('pt_BR', $this->service->getLocale());
    }

    #[Test]
    public function it_returns_self_when_setting_locale(): void
    {
        $result = $this->service->setLocale('es');

        $this->assertInstanceOf(StripeSyncService::class, $result);
    }

    #[Test]
    public function it_returns_supported_locales(): void
    {
        $locales = $this->service->getSupportedLocales();

        $this->assertIsArray($locales);
        $this->assertContains('en', $locales);
    }

    #[Test]
    public function dry_run_returns_empty_array_when_no_active_addons(): void
    {
        $preview = $this->service->dryRun();

        $this->assertIsArray($preview);
        $this->assertEmpty($preview);
    }

    #[Test]
    public function dry_run_shows_create_product_action_for_new_addon(): void
    {
        Addon::create([
            'slug' => 'test-addon',
            'name' => ['en' => 'Test Addon', 'pt_BR' => 'Addon de Teste'],
            'type' => 'feature',
            'active' => true,
        ]);

        $preview = $this->service->dryRun(null, 'en');

        $this->assertCount(1, $preview);
        $this->assertEquals('test-addon', $preview[0]['slug']);
        $this->assertEquals('Test Addon', $preview[0]['name']);
        $this->assertContains('Create Product: "Test Addon"', $preview[0]['actions']);
    }

    #[Test]
    public function dry_run_shows_update_product_action_for_existing_addon(): void
    {
        $addon = Addon::create([
            'slug' => 'test-addon',
            'name' => ['en' => 'Test Addon'],
            'type' => 'feature',
            'active' => true,
        ]);
        $addon->setProviderProductId('stripe', 'prod_existing123');

        $preview = $this->service->dryRun(null, 'en');

        $this->assertCount(1, $preview);
        $this->assertContains('Update Product: "Test Addon"', $preview[0]['actions']);
    }

    #[Test]
    public function dry_run_shows_create_price_actions(): void
    {
        Addon::create([
            'slug' => 'test-addon',
            'name' => ['en' => 'Test Addon'],
            'type' => 'feature',
            'active' => true,
            'price_monthly' => 1999,
            'price_yearly' => 19990,
            'price_one_time' => 9999,
        ]);

        $preview = $this->service->dryRun(null, 'en');

        $this->assertCount(1, $preview);
        $actions = $preview[0]['actions'];

        $this->assertTrue(
            collect($actions)->contains(fn ($a) => str_contains($a, 'Monthly Price'))
        );
        $this->assertTrue(
            collect($actions)->contains(fn ($a) => str_contains($a, 'Yearly Price'))
        );
        $this->assertTrue(
            collect($actions)->contains(fn ($a) => str_contains($a, 'One-Time Price'))
        );
    }

    #[Test]
    public function dry_run_filters_by_addon_slug(): void
    {
        Addon::create([
            'slug' => 'addon-one',
            'name' => ['en' => 'Addon One'],
            'type' => 'feature',
            'active' => true,
        ]);

        Addon::create([
            'slug' => 'addon-two',
            'name' => ['en' => 'Addon Two'],
            'type' => 'feature',
            'active' => true,
        ]);

        $preview = $this->service->dryRun('addon-one', 'en');

        $this->assertCount(1, $preview);
        $this->assertEquals('addon-one', $preview[0]['slug']);
    }

    #[Test]
    public function dry_run_uses_locale_for_translated_name(): void
    {
        Addon::create([
            'slug' => 'test-addon',
            'name' => ['en' => 'Test Addon', 'pt_BR' => 'Addon de Teste'],
            'type' => 'feature',
            'active' => true,
        ]);

        $previewEn = $this->service->dryRun(null, 'en');
        $previewPt = $this->service->dryRun(null, 'pt_BR');

        $this->assertEquals('Test Addon', $previewEn[0]['name']);
        $this->assertEquals('Addon de Teste', $previewPt[0]['name']);
    }

    #[Test]
    public function dry_run_excludes_inactive_addons(): void
    {
        Addon::create([
            'slug' => 'inactive-addon',
            'name' => ['en' => 'Inactive Addon'],
            'type' => 'feature',
            'active' => false,
        ]);

        $preview = $this->service->dryRun();

        $this->assertEmpty($preview);
    }

    #[Test]
    public function dry_run_skips_prices_that_already_have_provider_ids(): void
    {
        $addon = Addon::create([
            'slug' => 'test-addon',
            'name' => ['en' => 'Test Addon'],
            'type' => 'feature',
            'active' => true,
            'price_monthly' => 1999,
        ]);
        $addon->setProviderPriceId('stripe', 'monthly', 'price_existing123');

        $preview = $this->service->dryRun(null, 'en');

        $actions = $preview[0]['actions'];
        $this->assertFalse(
            collect($actions)->contains(fn ($a) => str_contains($a, 'Monthly Price'))
        );
    }

    #[Test]
    public function dry_run_includes_locale_in_result(): void
    {
        Addon::create([
            'slug' => 'test-addon',
            'name' => ['en' => 'Test Addon'],
            'type' => 'feature',
            'active' => true,
        ]);

        $preview = $this->service->dryRun(null, 'pt_BR');

        $this->assertEquals('pt_BR', $preview[0]['locale']);
    }
}
