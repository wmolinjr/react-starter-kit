<?php

namespace Tests\Unit;

use App\Models\Central\Plan;
use App\Services\Central\PlanSyncService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlanSyncServiceTest extends TestCase
{
    protected PlanSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Clean up any existing plans to avoid unique constraint violations
        Plan::query()->delete();

        $this->service = new PlanSyncService;
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

        $this->assertInstanceOf(PlanSyncService::class, $result);
    }

    #[Test]
    public function it_returns_supported_locales(): void
    {
        $locales = $this->service->getSupportedLocales();

        $this->assertIsArray($locales);
        $this->assertContains('en', $locales);
    }

    #[Test]
    public function dry_run_returns_empty_array_when_no_active_plans(): void
    {
        $preview = $this->service->dryRun();

        $this->assertIsArray($preview);
        $this->assertEmpty($preview);
    }

    #[Test]
    public function dry_run_shows_create_product_action_for_new_plan(): void
    {
        Plan::create([
            'slug' => 'starter',
            'name' => ['en' => 'Starter', 'pt_BR' => 'Iniciante'],
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        $preview = $this->service->dryRun(null, 'en');

        $this->assertCount(1, $preview);
        $this->assertEquals('starter', $preview[0]['slug']);
        $this->assertEquals('Starter', $preview[0]['name']);
        $this->assertContains('Create Product: "Starter"', $preview[0]['actions']);
    }

    #[Test]
    public function dry_run_shows_update_product_action_for_existing_plan(): void
    {
        Plan::create([
            'slug' => 'starter',
            'name' => ['en' => 'Starter'],
            'billing_period' => 'monthly',
            'is_active' => true,
            'stripe_product_id' => 'prod_existing123',
        ]);

        $preview = $this->service->dryRun(null, 'en');

        $this->assertCount(1, $preview);
        $this->assertContains('Update Product: "Starter"', $preview[0]['actions']);
    }

    #[Test]
    public function dry_run_shows_create_price_action(): void
    {
        Plan::create([
            'slug' => 'starter',
            'name' => ['en' => 'Starter'],
            'billing_period' => 'monthly',
            'is_active' => true,
            'price' => 2900,
        ]);

        $preview = $this->service->dryRun(null, 'en');

        $this->assertCount(1, $preview);
        $actions = $preview[0]['actions'];

        $this->assertTrue(
            collect($actions)->contains(fn ($a) => str_contains($a, 'Create Price'))
        );
    }

    #[Test]
    public function dry_run_filters_by_plan_slug(): void
    {
        Plan::create([
            'slug' => 'starter',
            'name' => ['en' => 'Starter'],
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        Plan::create([
            'slug' => 'professional',
            'name' => ['en' => 'Professional'],
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        $preview = $this->service->dryRun('starter', 'en');

        $this->assertCount(1, $preview);
        $this->assertEquals('starter', $preview[0]['slug']);
    }

    #[Test]
    public function dry_run_uses_locale_for_translated_name(): void
    {
        Plan::create([
            'slug' => 'starter',
            'name' => ['en' => 'Starter', 'pt_BR' => 'Iniciante'],
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        $previewEn = $this->service->dryRun(null, 'en');
        $previewPt = $this->service->dryRun(null, 'pt_BR');

        $this->assertEquals('Starter', $previewEn[0]['name']);
        $this->assertEquals('Iniciante', $previewPt[0]['name']);
    }

    #[Test]
    public function dry_run_excludes_inactive_plans(): void
    {
        Plan::create([
            'slug' => 'inactive-plan',
            'name' => ['en' => 'Inactive Plan'],
            'billing_period' => 'monthly',
            'is_active' => false,
        ]);

        $preview = $this->service->dryRun();

        $this->assertEmpty($preview);
    }

    #[Test]
    public function dry_run_skips_prices_that_already_have_stripe_ids(): void
    {
        Plan::create([
            'slug' => 'starter',
            'name' => ['en' => 'Starter'],
            'billing_period' => 'monthly',
            'is_active' => true,
            'price' => 2900,
            'stripe_price_id' => 'price_existing123',
        ]);

        $preview = $this->service->dryRun(null, 'en');

        $actions = $preview[0]['actions'];
        $this->assertFalse(
            collect($actions)->contains(fn ($a) => str_contains($a, 'Create Price'))
        );
    }

    #[Test]
    public function dry_run_includes_locale_in_result(): void
    {
        Plan::create([
            'slug' => 'starter',
            'name' => ['en' => 'Starter'],
            'billing_period' => 'monthly',
            'is_active' => true,
        ]);

        $preview = $this->service->dryRun(null, 'pt_BR');

        $this->assertEquals('pt_BR', $preview[0]['locale']);
    }

    #[Test]
    public function dry_run_respects_sort_order(): void
    {
        Plan::create([
            'slug' => 'enterprise',
            'name' => ['en' => 'Enterprise'],
            'billing_period' => 'monthly',
            'is_active' => true,
            'sort_order' => 3,
        ]);

        Plan::create([
            'slug' => 'starter',
            'name' => ['en' => 'Starter'],
            'billing_period' => 'monthly',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Plan::create([
            'slug' => 'professional',
            'name' => ['en' => 'Professional'],
            'billing_period' => 'monthly',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $preview = $this->service->dryRun(null, 'en');

        $this->assertCount(3, $preview);
        $this->assertEquals('starter', $preview[0]['slug']);
        $this->assertEquals('professional', $preview[1]['slug']);
        $this->assertEquals('enterprise', $preview[2]['slug']);
    }

    #[Test]
    public function dry_run_shows_correct_billing_period_in_price_action(): void
    {
        Plan::create([
            'slug' => 'annual',
            'name' => ['en' => 'Annual Plan'],
            'billing_period' => 'yearly',
            'is_active' => true,
            'price' => 29900,
        ]);

        $preview = $this->service->dryRun(null, 'en');

        $actions = $preview[0]['actions'];
        $this->assertTrue(
            collect($actions)->contains(fn ($a) => str_contains($a, '/yearly'))
        );
    }
}
