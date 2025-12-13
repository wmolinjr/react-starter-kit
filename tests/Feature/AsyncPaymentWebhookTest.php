<?php

namespace Tests\Feature;

use App\Enums\AddonStatus;
use App\Events\Payment\PaymentConfirmed;
use App\Events\Payment\PaymentFailed;
use App\Listeners\Central\HandleAsyncPaymentWebhooks;
use App\Models\Central\Addon;
use App\Models\Central\AddonPurchase;
use App\Models\Central\AddonSubscription;
use App\Services\Central\AddonService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * Async Payment Webhook Test Suite
 *
 * Tests the PaymentConfirmed and PaymentFailed events and their listener
 * for handling asynchronous payment methods like PIX and Boleto.
 */
class AsyncPaymentWebhookTest extends TenantTestCase
{
    protected HandleAsyncPaymentWebhooks $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->listener = new HandleAsyncPaymentWebhooks(
            app(AddonService::class)
        );
    }

    #[Test]
    public function payment_confirmed_marks_purchase_as_completed(): void
    {
        $purchase = AddonPurchase::factory()->pending()->forTenant($this->tenant)->create([
            'provider' => 'stripe',
            'provider_payment_intent_id' => null,
        ]);

        $event = new PaymentConfirmed(
            $purchase,
            'stripe',
            'pi_test_123',
            ['session_id' => 'cs_test']
        );

        $this->listener->handlePaymentConfirmed($event);

        $purchase->refresh();

        $this->assertTrue($purchase->isCompleted());
        $this->assertEquals('pi_test_123', $purchase->provider_payment_intent_id);
        $this->assertNotNull($purchase->purchased_at);
    }

    #[Test]
    public function payment_confirmed_skips_already_completed_purchase(): void
    {
        $purchase = AddonPurchase::factory()->completed()->forTenant($this->tenant)->create([
            'provider' => 'stripe',
            'provider_payment_intent_id' => 'pi_original',
            'purchased_at' => now()->subHour(),
        ]);

        $originalPurchasedAt = $purchase->purchased_at->toISOString();

        $event = new PaymentConfirmed(
            $purchase,
            'stripe',
            'pi_new_attempt',
            []
        );

        $this->listener->handlePaymentConfirmed($event);

        $purchase->refresh();

        // Should not change the original payment intent
        $this->assertEquals('pi_original', $purchase->provider_payment_intent_id);
        $this->assertEquals($originalPurchasedAt, $purchase->purchased_at->toISOString());
    }

    #[Test]
    public function payment_confirmed_creates_addon_subscription(): void
    {
        // Ensure we have the addon in the database
        $addon = Addon::where('slug', 'storage_credit_100gb')->first();

        $this->assertNotNull($addon, 'storage_credit_100gb addon should exist from seeder');

        $purchase = AddonPurchase::factory()->pending()->forTenant($this->tenant)->create([
            'addon_slug' => 'storage_credit_100gb',
            'addon_type' => $addon->type->value,
            'quantity' => 1,
            'amount_paid' => 9900,
            'valid_until' => now()->addYear(),
        ]);

        // Ensure no subscription exists before
        $existingSubscription = AddonSubscription::where('tenant_id', $this->tenant->id)
            ->where('addon_slug', 'storage_credit_100gb')
            ->first();

        $this->assertNull($existingSubscription);

        $event = new PaymentConfirmed(
            $purchase,
            'stripe',
            'pi_test_sub_123',
            ['event_type' => 'checkout.session.async_payment_succeeded']
        );

        $this->listener->handlePaymentConfirmed($event);

        // Check subscription was created
        $subscription = AddonSubscription::where('tenant_id', $this->tenant->id)
            ->where('addon_slug', 'storage_credit_100gb')
            ->first();

        $this->assertNotNull($subscription);
        $this->assertEquals(AddonStatus::ACTIVE, $subscription->status);
        $this->assertNotNull($subscription->started_at);
    }

    #[Test]
    public function payment_confirmed_activates_pending_subscription(): void
    {
        $addon = Addon::where('slug', 'storage_50gb')->first();

        $this->assertNotNull($addon, 'storage_50gb addon should exist from seeder');

        // Create a pending subscription (started_at is required by DB, but we track activation separately)
        $pendingSubscription = AddonSubscription::factory()->forTenant($this->tenant)->create([
            'addon_slug' => 'storage_50gb',
            'status' => AddonStatus::PENDING,
            'started_at' => now()->subMinute(), // Created but not yet activated
        ]);

        $purchase = AddonPurchase::factory()->pending()->forTenant($this->tenant)->create([
            'addon_slug' => 'storage_50gb',
            'addon_type' => $addon->type->value,
        ]);

        $event = new PaymentConfirmed(
            $purchase,
            'stripe',
            'pi_activate_123',
            []
        );

        $this->listener->handlePaymentConfirmed($event);

        $pendingSubscription->refresh();

        $this->assertEquals(AddonStatus::ACTIVE, $pendingSubscription->status);
        // started_at should be updated to now (activation time)
        $this->assertTrue($pendingSubscription->started_at->isToday());
    }

    #[Test]
    public function payment_failed_marks_purchase_as_failed(): void
    {
        $purchase = AddonPurchase::factory()->pending()->forTenant($this->tenant)->create();

        $event = new PaymentFailed(
            $purchase,
            'stripe',
            'Card declined by issuer',
            ['error_code' => 'card_declined']
        );

        $this->listener->handlePaymentFailed($event);

        $purchase->refresh();

        $this->assertTrue($purchase->isFailed());
        $this->assertEquals('Card declined by issuer', $purchase->failure_reason);
    }

    #[Test]
    public function payment_failed_skips_already_processed_purchase(): void
    {
        // Already completed
        $completedPurchase = AddonPurchase::factory()->completed()->forTenant($this->tenant)->create();

        $event = new PaymentFailed(
            $completedPurchase,
            'stripe',
            'Late failure notification',
            []
        );

        $this->listener->handlePaymentFailed($event);

        $completedPurchase->refresh();

        // Should still be completed, not failed
        $this->assertTrue($completedPurchase->isCompleted());
        $this->assertNull($completedPurchase->failure_reason);
    }

    #[Test]
    public function payment_failed_cancels_pending_subscription(): void
    {
        // Create a pending subscription
        $pendingSubscription = AddonSubscription::factory()->forTenant($this->tenant)->create([
            'addon_slug' => 'storage_50gb',
            'status' => AddonStatus::PENDING,
        ]);

        $purchase = AddonPurchase::factory()->pending()->forTenant($this->tenant)->create([
            'addon_slug' => 'storage_50gb',
        ]);

        $event = new PaymentFailed(
            $purchase,
            'stripe',
            'PIX expired',
            []
        );

        $this->listener->handlePaymentFailed($event);

        $pendingSubscription->refresh();

        $this->assertTrue($pendingSubscription->isCanceled());
        $this->assertStringContainsString('PIX expired', $pendingSubscription->notes);
    }

    #[Test]
    public function payment_confirmed_stores_metadata(): void
    {
        $purchase = AddonPurchase::factory()->pending()->forTenant($this->tenant)->create([
            'metadata' => ['original_key' => 'original_value'],
        ]);

        $event = new PaymentConfirmed(
            $purchase,
            'asaas',
            'pay_asaas_123',
            [
                'session_id' => 'cs_asaas',
                'pix_txid' => 'txid_123456',
            ]
        );

        $this->listener->handlePaymentConfirmed($event);

        $purchase->refresh();

        // Metadata should be merged
        $this->assertEquals('original_value', $purchase->metadata['original_key']);
        $this->assertEquals('cs_asaas', $purchase->metadata['session_id']);
        $this->assertEquals('txid_123456', $purchase->metadata['pix_txid']);
    }

    #[Test]
    public function payment_failed_stores_metadata(): void
    {
        $purchase = AddonPurchase::factory()->pending()->forTenant($this->tenant)->create([
            'metadata' => [],
        ]);

        $event = new PaymentFailed(
            $purchase,
            'asaas',
            'Boleto vencido',
            [
                'payment_id' => 'pay_123',
                'overdue_days' => 5,
            ]
        );

        $this->listener->handlePaymentFailed($event);

        $purchase->refresh();

        $this->assertEquals('pay_123', $purchase->metadata['payment_id']);
        $this->assertEquals(5, $purchase->metadata['overdue_days']);
    }

    #[Test]
    public function events_have_correct_tenant_id(): void
    {
        $purchase = AddonPurchase::factory()->pending()->forTenant($this->tenant)->create();

        $confirmedEvent = new PaymentConfirmed($purchase, 'stripe', 'pi_123', []);
        $failedEvent = new PaymentFailed($purchase, 'stripe', 'Test failure', []);

        $this->assertEquals($this->tenant->id, $confirmedEvent->getTenantId());
        $this->assertEquals($this->tenant->id, $failedEvent->getTenantId());
    }

    #[Test]
    public function payment_confirmed_event_has_amount(): void
    {
        $purchase = AddonPurchase::factory()->pending()->forTenant($this->tenant)->create([
            'amount_paid' => 4999,
        ]);

        $event = new PaymentConfirmed($purchase, 'stripe', 'pi_123', []);

        $this->assertEquals(4999, $event->getAmount());
    }
}
