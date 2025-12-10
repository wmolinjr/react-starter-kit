<?php

namespace Tests\Feature;

use App\Enums\AddonStatus;
use App\Enums\AddonType;
use App\Enums\BillingPeriod;
use App\Models\Central\AddonSubscription;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * AddonSubscription Model Test Suite
 *
 * Tests addon functionality in central database with tenant_id relationship.
 */
class AddonSubscriptionTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set locale to 'en' and currency to USD for consistent formatting in tests
        app()->setLocale('en');
        config(['cashier.currency_locale' => 'en']);
        config(['cashier.currency' => 'usd']);

        // Update tenant's plan limits for addon testing (don't change slug - conflicts with seeded plans)
        $this->tenant->plan->update([
            'limits' => [
                'storage' => 10000, // 10GB
                'users' => 5,
                'projects' => 10,
            ],
        ]);
    }

    #[Test]
    public function can_create_tenant_addon(): void
    {
        $addon = AddonSubscription::create([
            'tenant_id' => $this->tenant->id,
            'addon_slug' => 'storage_50gb',
            'addon_type' => AddonType::QUOTA,
            'name' => 'Storage 50GB',
            'quantity' => 1,
            'price' => 4900,
            'billing_period' => BillingPeriod::MONTHLY,
            'status' => AddonStatus::ACTIVE,
            'started_at' => now(),
        ]);

        $this->assertDatabaseHas('addon_subscriptions', [
            'addon_slug' => 'storage_50gb',
            'price' => 4900,
            'tenant_id' => $this->tenant->id,
        ], 'testing');

        $this->assertTrue($addon->isActive());
        $this->assertFalse($addon->isExpired());
    }

    #[Test]
    public function addon_type_enum_casts_correctly(): void
    {
        $addon = AddonSubscription::factory()->forTenant($this->tenant)->create([
            'addon_type' => AddonType::QUOTA,
        ]);

        $this->assertInstanceOf(AddonType::class, $addon->addon_type);
        $this->assertEquals(AddonType::QUOTA, $addon->addon_type);
        // Test label returns translated value (use 'en' for consistent testing)
        $this->assertEquals('Quota Increase', $addon->addon_type->label('en'));
    }

    #[Test]
    public function addon_status_enum_casts_correctly(): void
    {
        $addon = AddonSubscription::factory()->forTenant($this->tenant)->create([
            'status' => AddonStatus::ACTIVE,
        ]);

        $this->assertInstanceOf(AddonStatus::class, $addon->status);
        $this->assertEquals(AddonStatus::ACTIVE, $addon->status);
        $this->assertEquals('green', $addon->status->color());
    }

    #[Test]
    public function billing_period_enum_casts_correctly(): void
    {
        $addon = AddonSubscription::factory()->forTenant($this->tenant)->create([
            'billing_period' => BillingPeriod::YEARLY,
        ]);

        $this->assertInstanceOf(BillingPeriod::class, $addon->billing_period);
        $this->assertEquals('year', $addon->billing_period->interval());
    }

    #[Test]
    public function addon_belongs_to_tenant(): void
    {
        $addon = AddonSubscription::factory()->forTenant($this->tenant)->create();

        $this->assertNotNull($addon->tenant);
        $this->assertEquals($this->tenant->id, $addon->tenant->id);
    }

    #[Test]
    public function tenant_has_many_addons(): void
    {
        AddonSubscription::factory()->forTenant($this->tenant)->count(3)->create();

        $this->assertCount(3, $this->tenant->addons);
    }

    #[Test]
    public function active_scope_filters_correctly(): void
    {
        // Active addon
        AddonSubscription::factory()->forTenant($this->tenant)->create([
            'status' => AddonStatus::ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        // Expired addon
        AddonSubscription::factory()->forTenant($this->tenant)->create([
            'status' => AddonStatus::ACTIVE,
            'expires_at' => now()->subDay(),
        ]);

        // Canceled addon
        AddonSubscription::factory()->forTenant($this->tenant)->create([
            'status' => AddonStatus::CANCELED,
        ]);

        $activeAddons = $this->tenant->activeAddons()->get();

        $this->assertCount(1, $activeAddons);
        $this->assertTrue($activeAddons->first()->isActive());
    }

    #[Test]
    public function can_calculate_total_price(): void
    {
        $addon = AddonSubscription::factory()->forTenant($this->tenant)->create([
            'price' => 1000, // R$10
            'quantity' => 5,
        ]);

        $this->assertEquals(5000, $addon->total_price);
        $this->assertEquals('R$50.00', $addon->formatted_total_price);
    }

    #[Test]
    public function can_format_price(): void
    {
        $addon = AddonSubscription::factory()->forTenant($this->tenant)->create([
            'price' => 4999, // R$49.99
        ]);

        $this->assertEquals('R$49.99', $addon->formatted_price);
    }

    #[Test]
    public function can_cancel_addon(): void
    {
        $addon = AddonSubscription::factory()->forTenant($this->tenant)->create([
            'status' => AddonStatus::ACTIVE,
        ]);

        $addon->cancel('Customer request');

        $this->assertTrue($addon->isCanceled());
        $this->assertNotNull($addon->canceled_at);
        $this->assertStringContainsString('Customer request', $addon->notes);
    }

    #[Test]
    public function is_recurring_returns_true_for_monthly(): void
    {
        $addon = AddonSubscription::factory()->forTenant($this->tenant)->monthly()->create();

        $this->assertTrue($addon->isRecurring());
        $this->assertFalse($addon->isOneTime());
        $this->assertFalse($addon->isMetered());
    }

    #[Test]
    public function is_recurring_returns_true_for_yearly(): void
    {
        $addon = AddonSubscription::factory()->forTenant($this->tenant)->yearly()->create();

        $this->assertTrue($addon->isRecurring());
    }

    #[Test]
    public function is_one_time_returns_true_for_one_time(): void
    {
        $addon = AddonSubscription::factory()->forTenant($this->tenant)->oneTime()->create();

        $this->assertTrue($addon->isOneTime());
        $this->assertFalse($addon->isRecurring());
    }

    #[Test]
    public function is_metered_returns_true_for_metered(): void
    {
        $addon = AddonSubscription::factory()->forTenant($this->tenant)->metered()->create();

        $this->assertTrue($addon->isMetered());
        $this->assertFalse($addon->isRecurring());
    }

    #[Test]
    public function can_increment_metered_usage(): void
    {
        $addon = AddonSubscription::factory()->forTenant($this->tenant)->metered()->create([
            'metered_usage' => 0,
        ]);

        $addon->incrementMeteredUsage(100);

        $this->assertEquals(100, $addon->fresh()->metered_usage);
    }

    #[Test]
    public function can_reset_metered_usage(): void
    {
        $addon = AddonSubscription::factory()->forTenant($this->tenant)->metered()->create([
            'metered_usage' => 500,
        ]);

        $addon->resetMeteredUsage();

        $addon->refresh();
        $this->assertEquals(0, $addon->metered_usage);
        $this->assertNotNull($addon->metered_reset_at);
    }

    #[Test]
    public function addon_factory_states_work(): void
    {
        $storage = AddonSubscription::factory()->forTenant($this->tenant)->storage()->create();
        $this->assertEquals(AddonType::QUOTA, $storage->addon_type);
        $this->assertEquals('storage_50gb', $storage->addon_slug);

        $users = AddonSubscription::factory()->forTenant($this->tenant)->users()->create();
        $this->assertEquals(AddonType::QUOTA, $users->addon_type);
        $this->assertEquals('extra_users_5', $users->addon_slug);

        $feature = AddonSubscription::factory()->forTenant($this->tenant)->feature()->create();
        $this->assertEquals(AddonType::FEATURE, $feature->addon_type);

        $credit = AddonSubscription::factory()->forTenant($this->tenant)->credit()->create();
        $this->assertEquals(AddonType::CREDIT, $credit->addon_type);

        $metered = AddonSubscription::factory()->forTenant($this->tenant)->metered()->create();
        $this->assertEquals(AddonType::METERED, $metered->addon_type);
    }

    #[Test]
    public function scope_by_type_filters_correctly(): void
    {
        // Create different addon types
        AddonSubscription::factory()->forTenant($this->tenant)->storage()->create();
        AddonSubscription::factory()->forTenant($this->tenant)->users()->create();
        AddonSubscription::factory()->forTenant($this->tenant)->feature()->create();
        AddonSubscription::factory()->forTenant($this->tenant)->metered()->create();

        // QUOTA type (storage and users are both QUOTA)
        $quotaAddons = $this->tenant->addons()->byType(AddonType::QUOTA)->get();
        $this->assertCount(2, $quotaAddons);

        // FEATURE type
        $featureAddons = $this->tenant->addons()->byType(AddonType::FEATURE)->get();
        $this->assertCount(1, $featureAddons);

        // METERED type (also works with string)
        $meteredAddons = $this->tenant->addons()->byType('metered')->get();
        $this->assertCount(1, $meteredAddons);
    }

    #[Test]
    public function can_soft_delete_addon(): void
    {
        $addon = AddonSubscription::factory()->forTenant($this->tenant)->create();

        $addon->delete();

        $this->assertSoftDeleted('addon_subscriptions', ['id' => $addon->id], 'testing');
        $this->assertNotNull(AddonSubscription::withTrashed()->find($addon->id));
    }
}
