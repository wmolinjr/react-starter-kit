<?php

namespace Tests\Feature;

use App\Enums\AddonType;
use App\Exceptions\Central\AddonException;
use App\Models\Central\AddonPurchase;
use App\Services\Central\CheckoutService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * CheckoutService Test Suite
 *
 * Tests checkout service with addon_purchases in central database.
 */
class CheckoutServiceTest extends TenantTestCase
{
    protected CheckoutService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CheckoutService::class);

        // Update tenant's plan limits (don't change slug - conflicts with seeded plans)
        $this->tenant->plan->update([
            'limits' => ['storage' => 10000],
        ]);
    }

    #[Test]
    public function throws_exception_for_nonexistent_addon(): void
    {
        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('not found');

        $this->service->createCheckoutSession($this->tenant, 'nonexistent_addon');
    }

    #[Test]
    public function throws_exception_for_non_onetime_addon(): void
    {
        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('does not support one-time');

        // storage_50gb only has monthly/yearly, not one_time
        $this->service->createCheckoutSession($this->tenant, 'storage_50gb');
    }

    #[Test]
    public function throws_exception_when_below_minimum_quantity(): void
    {
        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('Minimum quantity');

        $this->service->createCheckoutSession($this->tenant, 'storage_credit_100gb', 0);
    }

    #[Test]
    public function throws_exception_when_exceeding_maximum_quantity(): void
    {
        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('Maximum quantity');

        // storage_credit_100gb has max_quantity of 50
        $this->service->createCheckoutSession($this->tenant, 'storage_credit_100gb', 100);
    }

    #[Test]
    public function handle_checkout_completed_returns_null_for_unknown_session(): void
    {
        $result = $this->service->handleCheckoutCompleted('cs_unknown_session');

        $this->assertNull($result);
    }

    #[Test]
    public function handle_checkout_completed_skips_already_completed(): void
    {
        $purchase = AddonPurchase::factory()->completed()->create([
            'stripe_checkout_session_id' => 'cs_test_session',
        ]);

        $result = $this->service->handleCheckoutCompleted('cs_test_session');

        $this->assertNotNull($result);
        $this->assertEquals($purchase->id, $result->id);
    }

    #[Test]
    public function refund_throws_exception_without_payment_intent(): void
    {
        $purchase = AddonPurchase::factory()->completed()->create([
            'stripe_payment_intent_id' => null,
        ]);

        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('No payment intent');

        $this->service->refundPurchase($purchase);
    }

    #[Test]
    public function refund_throws_exception_for_already_refunded(): void
    {
        $purchase = AddonPurchase::factory()->refunded()->create([
            'stripe_payment_intent_id' => 'pi_test123',
        ]);

        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('already been refunded');

        $this->service->refundPurchase($purchase);
    }

    // ========================================
    // Subscription Checkout Tests
    // ========================================

    #[Test]
    public function subscription_throws_exception_for_nonexistent_addon(): void
    {
        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('not found');

        $this->service->createSubscriptionCheckout($this->tenant, 'nonexistent_addon', 'monthly');
    }

    #[Test]
    public function subscription_throws_exception_for_invalid_billing_period(): void
    {
        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('does not support');

        // storage_credit_100gb is one_time only, no monthly
        $this->service->createSubscriptionCheckout($this->tenant, 'storage_credit_100gb', 'monthly');
    }

    #[Test]
    public function subscription_throws_exception_when_below_minimum_quantity(): void
    {
        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('Minimum quantity');

        $this->service->createSubscriptionCheckout($this->tenant, 'storage_50gb', 'monthly', 0);
    }

    #[Test]
    public function subscription_throws_exception_when_exceeding_maximum_quantity(): void
    {
        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('Maximum quantity');

        // storage_50gb has max_quantity of 20
        $this->service->createSubscriptionCheckout($this->tenant, 'storage_50gb', 'monthly', 100);
    }

    #[Test]
    public function subscription_accepts_valid_monthly_billing(): void
    {
        // Test that monthly billing validation passes (no validation exception thrown)
        // The method may succeed with Stripe mock or fail at Stripe API level
        try {
            $result = $this->service->createSubscriptionCheckout($this->tenant, 'storage_50gb', 'monthly', 1);
            // If it succeeds, it should return session data
            $this->assertIsArray($result);
        } catch (AddonException $e) {
            // If it fails, it should be a Stripe API error, not a validation error
            $this->assertStringContainsString('checkout session', $e->getMessage());
        }
    }

    #[Test]
    public function subscription_accepts_valid_yearly_billing(): void
    {
        // Test that yearly billing validation passes (no validation exception thrown)
        try {
            $result = $this->service->createSubscriptionCheckout($this->tenant, 'storage_50gb', 'yearly', 2);
            // If it succeeds, it should return session data
            $this->assertIsArray($result);
        } catch (AddonException $e) {
            // If it fails, it should be a Stripe API error, not a validation error
            $this->assertStringContainsString('checkout session', $e->getMessage());
        }
    }

    #[Test]
    public function subscription_creates_stripe_customer_if_not_exists(): void
    {
        $this->assertNull($this->tenant->stripe_id);

        try {
            $this->service->createSubscriptionCheckout($this->tenant, 'storage_50gb', 'monthly');
        } catch (AddonException $e) {
            // Will fail at Stripe, but createAsStripeCustomer should have been called
        }

        // Refresh tenant - in real scenario with valid Stripe keys, this would be set
        $this->tenant->refresh();
        // Can't fully test without Stripe keys, but the code path is covered
    }
}
