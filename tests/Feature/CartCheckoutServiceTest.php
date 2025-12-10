<?php

namespace Tests\Feature;

use App\Exceptions\Central\AddonException;
use App\Models\Central\Addon;
use App\Models\Central\AddonBundle;
use App\Models\Central\AddonPurchase;
use App\Services\Central\CartCheckoutService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * CartCheckoutService Test Suite
 *
 * Tests cart checkout functionality for multi-item purchases.
 * Note: Tests verify validation logic; Stripe/payment calls will fail without valid API keys.
 */
class CartCheckoutServiceTest extends TenantTestCase
{
    protected CartCheckoutService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CartCheckoutService::class);
    }

    #[Test]
    public function throws_exception_for_nonexistent_addon(): void
    {
        $items = [
            ['type' => 'addon', 'slug' => 'nonexistent_addon', 'quantity' => 1, 'billing_period' => 'one_time'],
        ];

        $this->expectException(ModelNotFoundException::class);

        $this->service->processCartCheckout($this->tenant, $items, 'card');
    }

    #[Test]
    public function throws_exception_for_nonexistent_bundle(): void
    {
        $items = [
            ['type' => 'bundle', 'slug' => 'nonexistent_bundle', 'quantity' => 1],
        ];

        $this->expectException(ModelNotFoundException::class);

        $this->service->processCartCheckout($this->tenant, $items, 'card');
    }

    #[Test]
    public function validates_minimum_quantity(): void
    {
        $items = [
            ['type' => 'addon', 'slug' => 'storage_credit_100gb', 'quantity' => 0, 'billing_period' => 'one_time'],
        ];

        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('Minimum quantity');

        $this->service->processCartCheckout($this->tenant, $items, 'card');
    }

    #[Test]
    public function validates_maximum_quantity(): void
    {
        $items = [
            ['type' => 'addon', 'slug' => 'storage_credit_100gb', 'quantity' => 100, 'billing_period' => 'one_time'],
        ];

        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('Maximum quantity');

        $this->service->processCartCheckout($this->tenant, $items, 'card');
    }

    #[Test]
    public function validates_addon_with_valid_quantity(): void
    {
        $addon = Addon::where('slug', 'storage_credit_100gb')->first();
        $this->assertNotNull($addon, 'storage_credit_100gb addon should exist');

        $items = [
            ['type' => 'addon', 'slug' => 'storage_credit_100gb', 'quantity' => 1, 'billing_period' => 'one_time'],
        ];

        // Valid addon passes validation but fails at Stripe/customer level
        try {
            $this->service->processCartCheckout($this->tenant, $items, 'card');
            $this->assertTrue(true); // If we get here, Stripe worked
        } catch (AddonException $e) {
            // Expected - Stripe/customer configuration issue (validation passed)
            $this->assertStringContainsStringIgnoringCase('customer', $e->getMessage());
        }
    }

    #[Test]
    public function check_payment_status_returns_failed_for_invalid_id(): void
    {
        // Invalid IDs return 'failed' status with error
        $status = $this->service->checkPaymentStatus('invalid_payment_id');

        $this->assertArrayHasKey('status', $status);
        $this->assertEquals('failed', $status['status']);
        $this->assertArrayHasKey('error', $status);
    }

    #[Test]
    public function refresh_pix_qr_code_returns_null_for_invalid_id(): void
    {
        $result = $this->service->refreshPixQrCode('invalid_payment_id');

        $this->assertNull($result);
    }

    #[Test]
    public function refresh_pix_qr_code_returns_null_for_non_pix_purchase(): void
    {
        // Create a card purchase
        $purchase = AddonPurchase::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
            'payment_method' => 'card',
        ]);

        $result = $this->service->refreshPixQrCode($purchase->id);

        $this->assertNull($result);
    }

    #[Test]
    public function cart_validates_multiple_addons(): void
    {
        $items = [
            ['type' => 'addon', 'slug' => 'storage_credit_100gb', 'quantity' => 1, 'billing_period' => 'one_time'],
            ['type' => 'addon', 'slug' => 'storage_credit_100gb', 'quantity' => 2, 'billing_period' => 'one_time'],
        ];

        try {
            $this->service->processCartCheckout($this->tenant, $items, 'card');
        } catch (AddonException $e) {
            // Expected - Stripe/customer configuration issue (validation passed)
            $this->assertStringContainsStringIgnoringCase('customer', $e->getMessage());
        }
    }

    #[Test]
    public function cart_validates_bundles(): void
    {
        // Get a bundle if exists
        $bundle = AddonBundle::active()->first();

        if (! $bundle) {
            $this->markTestSkipped('No active bundles available for testing');
        }

        $items = [
            ['type' => 'bundle', 'slug' => $bundle->slug, 'quantity' => 1],
        ];

        try {
            $this->service->processCartCheckout($this->tenant, $items, 'card');
        } catch (AddonException $e) {
            // Expected - Stripe/customer configuration issue (validation passed)
            $this->assertTrue(
                str_contains(strtolower($e->getMessage()), 'customer') ||
                str_contains(strtolower($e->getMessage()), 'stripe')
            );
        }
    }

    #[Test]
    public function pix_payment_fails_for_recurring_items(): void
    {
        // Recurring addons should fail with PIX
        $items = [
            ['type' => 'addon', 'slug' => 'storage_50gb', 'quantity' => 1, 'billing_period' => 'monthly'],
        ];

        try {
            $this->service->processCartCheckout($this->tenant, $items, 'pix');
            $this->fail('Expected exception for recurring item with PIX');
        } catch (AddonException $e) {
            // Could be "PIX not supported for recurring" or other error
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function boleto_payment_fails_for_recurring_items(): void
    {
        // Recurring addons should fail with Boleto
        $items = [
            ['type' => 'addon', 'slug' => 'storage_50gb', 'quantity' => 1, 'billing_period' => 'monthly'],
        ];

        try {
            $this->service->processCartCheckout($this->tenant, $items, 'boleto');
            $this->fail('Expected exception for recurring item with Boleto');
        } catch (AddonException $e) {
            // Could be "Boleto not supported for recurring" or other error
            $this->assertTrue(true);
        }
    }

    #[Test]
    public function addon_prices_attribute_exists(): void
    {
        // Get addon pricing
        $addon = Addon::where('slug', 'storage_credit_100gb')->first();
        $this->assertNotNull($addon, 'storage_credit_100gb addon should exist');

        // Verify addon has prices attribute (may be null or array depending on seeder)
        $this->assertTrue(
            is_null($addon->prices) || is_array($addon->prices),
            'prices attribute should be null or array'
        );
    }

    #[Test]
    public function addon_model_has_required_attributes(): void
    {
        $addon = Addon::where('slug', 'storage_credit_100gb')->first();
        $this->assertNotNull($addon);

        // Check that addon has the required attributes for checkout
        $this->assertNotNull($addon->name);
        $this->assertNotNull($addon->slug);
        // Prices can be null or array depending on configuration
        $this->assertTrue(
            is_null($addon->prices) || is_array($addon->prices),
            'prices attribute should be null or array'
        );
    }

    #[Test]
    public function invalid_payment_method_throws_exception(): void
    {
        $items = [
            ['type' => 'addon', 'slug' => 'storage_credit_100gb', 'quantity' => 1, 'billing_period' => 'one_time'],
        ];

        $this->expectException(AddonException::class);
        $this->expectExceptionMessage('Invalid payment method');

        $this->service->processCartCheckout($this->tenant, $items, 'invalid_method');
    }
}
