<?php

namespace Tests\Feature;

use App\Enums\AddonType;
use App\Models\Central\AddonSubscription;
use App\Models\Central\AddonPurchase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * AddonPurchase Model Test Suite
 *
 * Tests purchase functionality in central database with tenant_id relationship.
 */
class AddonPurchaseTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set locale to 'en' and currency to USD for consistent formatting in tests
        app()->setLocale('en');
        config(['cashier.currency_locale' => 'en']);
        config(['cashier.currency' => 'usd']);
    }

    #[Test]
    public function can_create_addon_purchase(): void
    {
        $purchase = AddonPurchase::create([
            'tenant_id' => $this->tenant->id,
            'addon_slug' => 'storage_50gb',
            'addon_type' => AddonType::QUOTA->value,
            'quantity' => 1,
            'amount_paid' => 4900,
            'payment_method' => 'stripe_checkout',
            'status' => 'completed',
            'purchased_at' => now(),
            'valid_from' => now(),
            'valid_until' => now()->addYear(),
        ]);

        $this->assertDatabaseHas('addon_purchases', [
            'addon_slug' => 'storage_50gb',
            'amount_paid' => 4900,
            'tenant_id' => $this->tenant->id,
        ], 'testing');
    }

    #[Test]
    public function purchase_belongs_to_tenant(): void
    {
        $purchase = AddonPurchase::factory()->forTenant($this->tenant)->create();

        $this->assertNotNull($purchase->tenant);
        $this->assertEquals($this->tenant->id, $purchase->tenant->id);
    }

    #[Test]
    public function purchase_can_belong_to_subscription(): void
    {
        $subscription = AddonSubscription::factory()->forTenant($this->tenant)->create();

        $purchase = AddonPurchase::factory()->forSubscription($subscription)->create();

        $this->assertInstanceOf(AddonSubscription::class, $purchase->subscription);
        $this->assertEquals($subscription->id, $purchase->subscription->id);
    }

    #[Test]
    public function tenant_has_many_purchases(): void
    {
        AddonPurchase::factory()->forTenant($this->tenant)->count(3)->create();

        $this->assertCount(3, $this->tenant->addonPurchases);
    }

    #[Test]
    public function can_format_amount(): void
    {
        $purchase = AddonPurchase::factory()->forTenant($this->tenant)->create([
            'amount_paid' => 4999, // $49.99
        ]);

        $this->assertEquals('$49.99', $purchase->formatted_amount);
    }

    #[Test]
    public function is_completed_returns_true_for_completed(): void
    {
        $purchase = AddonPurchase::factory()->forTenant($this->tenant)->completed()->create();

        $this->assertTrue($purchase->isCompleted());
        $this->assertFalse($purchase->isPending());
        $this->assertFalse($purchase->isFailed());
    }

    #[Test]
    public function is_pending_returns_true_for_pending(): void
    {
        $purchase = AddonPurchase::factory()->forTenant($this->tenant)->pending()->create();

        $this->assertTrue($purchase->isPending());
        $this->assertFalse($purchase->isCompleted());
    }

    #[Test]
    public function is_failed_returns_true_for_failed(): void
    {
        $purchase = AddonPurchase::factory()->forTenant($this->tenant)->failed()->create();

        $this->assertTrue($purchase->isFailed());
        $this->assertNotNull($purchase->failure_reason);
    }

    #[Test]
    public function is_refunded_returns_true_for_refunded(): void
    {
        $purchase = AddonPurchase::factory()->forTenant($this->tenant)->refunded()->create();

        $this->assertTrue($purchase->isRefunded());
        $this->assertNotNull($purchase->refunded_at);
    }

    #[Test]
    public function can_mark_as_completed(): void
    {
        $purchase = AddonPurchase::factory()->forTenant($this->tenant)->pending()->create();

        $purchase->markAsCompleted();

        $this->assertEquals('completed', $purchase->status);
        $this->assertNotNull($purchase->purchased_at);
    }

    #[Test]
    public function can_mark_as_failed(): void
    {
        $purchase = AddonPurchase::factory()->forTenant($this->tenant)->pending()->create();

        $purchase->markAsFailed('Payment declined by bank');

        $this->assertEquals('failed', $purchase->status);
        $this->assertEquals('Payment declined by bank', $purchase->failure_reason);
    }

    #[Test]
    public function can_refund(): void
    {
        $purchase = AddonPurchase::factory()->forTenant($this->tenant)->completed()->create();

        $purchase->refund();

        $this->assertEquals('refunded', $purchase->status);
        $this->assertNotNull($purchase->refunded_at);
    }

    #[Test]
    public function can_consume(): void
    {
        $purchase = AddonPurchase::factory()->forTenant($this->tenant)->completed()->create([
            'is_consumed' => false,
        ]);

        $purchase->consume();

        $this->assertTrue($purchase->fresh()->is_consumed);
    }

    #[Test]
    public function is_valid_checks_date_range(): void
    {
        // Valid purchase
        $validPurchase = AddonPurchase::factory()->forTenant($this->tenant)->create([
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        // Not yet valid
        $futureStart = AddonPurchase::factory()->forTenant($this->tenant)->create([
            'valid_from' => now()->addDay(),
            'valid_until' => now()->addMonth(),
        ]);

        // Expired
        $expired = AddonPurchase::factory()->forTenant($this->tenant)->expired()->create();

        $this->assertTrue($validPurchase->isValid());
        $this->assertFalse($futureStart->isValid());
        $this->assertFalse($expired->isValid());
    }

    #[Test]
    public function completed_scope_filters_correctly(): void
    {
        AddonPurchase::factory()->forTenant($this->tenant)->completed()->count(2)->create();
        AddonPurchase::factory()->forTenant($this->tenant)->pending()->create();
        AddonPurchase::factory()->forTenant($this->tenant)->failed()->create();

        $completed = $this->tenant->addonPurchases()->completed()->get();

        $this->assertCount(2, $completed);
    }

    #[Test]
    public function pending_scope_filters_correctly(): void
    {
        AddonPurchase::factory()->forTenant($this->tenant)->completed()->create();
        AddonPurchase::factory()->forTenant($this->tenant)->pending()->count(3)->create();

        $pending = $this->tenant->addonPurchases()->pending()->get();

        $this->assertCount(3, $pending);
    }

    #[Test]
    public function valid_scope_filters_correctly(): void
    {
        // Valid purchases
        AddonPurchase::factory()->forTenant($this->tenant)->count(2)->create([
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addMonth(),
        ]);

        // Expired
        AddonPurchase::factory()->forTenant($this->tenant)->expired()->create();

        $valid = $this->tenant->addonPurchases()->valid()->get();

        $this->assertCount(2, $valid);
    }

    #[Test]
    public function unconsumed_scope_filters_correctly(): void
    {
        AddonPurchase::factory()->forTenant($this->tenant)->create([
            'is_consumed' => false,
        ]);
        AddonPurchase::factory()->forTenant($this->tenant)->create([
            'is_consumed' => false,
        ]);
        AddonPurchase::factory()->forTenant($this->tenant)->consumed()->create();

        $unconsumed = $this->tenant->addonPurchases()->unconsumed()->get();

        $this->assertCount(2, $unconsumed);
    }

    #[Test]
    public function can_soft_delete_purchase(): void
    {
        $purchase = AddonPurchase::factory()->forTenant($this->tenant)->create();

        $purchase->delete();

        $this->assertSoftDeleted('addon_purchases', ['id' => $purchase->id], 'testing');
    }
}
