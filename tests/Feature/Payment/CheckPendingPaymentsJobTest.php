<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Events\Payment\PaymentConfirmed;
use App\Events\Payment\PaymentFailed;
use App\Jobs\Central\CheckPendingPaymentsJob;
use App\Models\Central\AddonPurchase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * Tests for the CheckPendingPaymentsJob
 *
 * This job polls payment providers for pending PIX/Boleto payments
 * as a fallback mechanism when webhooks fail.
 */
class CheckPendingPaymentsJobTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([PaymentConfirmed::class, PaymentFailed::class]);
    }

    #[Test]
    public function job_can_be_instantiated(): void
    {
        $job = new CheckPendingPaymentsJob;

        $this->assertInstanceOf(CheckPendingPaymentsJob::class, $job);
    }

    #[Test]
    public function job_skips_recent_pending_payments(): void
    {
        // Create a purchase that is too recent (created now)
        AddonPurchase::factory()->pending()->forTenant($this->tenant)->create([
            'stripe_payment_intent_id' => 'pay_test_recent',
            'payment_method' => 'pix',
            'metadata' => ['provider' => 'asaas'],
            'created_at' => now(), // Just created
        ]);

        // Default is 5 minutes, so this purchase is too recent
        $job = new CheckPendingPaymentsJob(olderThanMinutes: 5);
        $job->handle(app(\App\Services\Payment\PaymentGatewayManager::class));

        // Should not dispatch any events (purchase is too recent)
        Event::assertNotDispatched(PaymentConfirmed::class);
        Event::assertNotDispatched(PaymentFailed::class);
    }

    #[Test]
    public function job_checks_old_pending_payments(): void
    {
        // Create an old pending purchase
        $purchase = AddonPurchase::factory()->pending()->forTenant($this->tenant)->create([
            'stripe_payment_intent_id' => 'pay_test_old',
            'payment_method' => 'pix',
            'metadata' => ['provider' => 'asaas'],
            'created_at' => now()->subMinutes(10), // 10 minutes ago
        ]);

        // Mock Asaas API to return still pending
        Http::fake([
            '*/payments/pay_test_old' => Http::response([
                'id' => 'pay_test_old',
                'status' => 'PENDING',
                'billingType' => 'PIX',
            ]),
        ]);

        $job = new CheckPendingPaymentsJob(olderThanMinutes: 5);
        $job->handle(app(\App\Services\Payment\PaymentGatewayManager::class));

        // Purchase is still pending, no events should be dispatched
        Event::assertNotDispatched(PaymentConfirmed::class);
        Event::assertNotDispatched(PaymentFailed::class);
    }

    #[Test]
    public function job_dispatches_confirmed_event_when_payment_succeeded(): void
    {
        // Create an old pending purchase
        $purchase = AddonPurchase::factory()->pending()->forTenant($this->tenant)->create([
            'stripe_payment_intent_id' => 'pay_confirmed',
            'payment_method' => 'pix',
            'metadata' => ['provider' => 'asaas'],
            'created_at' => now()->subMinutes(10),
        ]);

        // Mock Asaas API to return confirmed
        Http::fake([
            '*/payments/pay_confirmed' => Http::response([
                'id' => 'pay_confirmed',
                'status' => 'CONFIRMED',
                'billingType' => 'PIX',
                'value' => 49.99,
                'netValue' => 48.50,
                'paymentDate' => now()->format('Y-m-d'),
            ]),
        ]);

        $job = new CheckPendingPaymentsJob(olderThanMinutes: 5);
        $job->handle(app(\App\Services\Payment\PaymentGatewayManager::class));

        Event::assertDispatched(PaymentConfirmed::class, function ($event) use ($purchase) {
            return $event->purchase->id === $purchase->id
                && $event->provider === 'asaas'
                && $event->paymentIntentId === 'pay_confirmed'
                && $event->metadata['polled'] === true;
        });
    }

    #[Test]
    public function job_dispatches_failed_event_when_payment_overdue(): void
    {
        // Create an old pending purchase
        $purchase = AddonPurchase::factory()->pending()->forTenant($this->tenant)->create([
            'stripe_payment_intent_id' => 'pay_overdue',
            'payment_method' => 'boleto',
            'metadata' => ['provider' => 'asaas'],
            'created_at' => now()->subMinutes(10),
        ]);

        // Mock Asaas API to return overdue
        Http::fake([
            '*/payments/pay_overdue' => Http::response([
                'id' => 'pay_overdue',
                'status' => 'OVERDUE',
                'billingType' => 'BOLETO',
            ]),
        ]);

        $job = new CheckPendingPaymentsJob(olderThanMinutes: 5);
        $job->handle(app(\App\Services\Payment\PaymentGatewayManager::class));

        Event::assertDispatched(PaymentFailed::class, function ($event) use ($purchase) {
            return $event->purchase->id === $purchase->id
                && $event->provider === 'asaas'
                && str_contains($event->reason, 'vencido')
                && $event->metadata['polled'] === true;
        });
    }

    #[Test]
    public function job_detects_asaas_provider_from_pix_payment_method(): void
    {
        $purchase = AddonPurchase::factory()->pending()->forTenant($this->tenant)->create([
            'stripe_payment_intent_id' => 'pay_pix_detect',
            'payment_method' => 'pix',
            'metadata' => [], // No explicit provider
            'created_at' => now()->subMinutes(10),
        ]);

        Http::fake([
            '*/payments/pay_pix_detect' => Http::response([
                'id' => 'pay_pix_detect',
                'status' => 'RECEIVED',
                'billingType' => 'PIX',
            ]),
        ]);

        $job = new CheckPendingPaymentsJob(olderThanMinutes: 5);
        $job->handle(app(\App\Services\Payment\PaymentGatewayManager::class));

        Event::assertDispatched(PaymentConfirmed::class, function ($event) {
            return $event->provider === 'asaas';
        });
    }

    #[Test]
    public function job_detects_asaas_provider_from_boleto_payment_method(): void
    {
        $purchase = AddonPurchase::factory()->pending()->forTenant($this->tenant)->create([
            'stripe_payment_intent_id' => 'pay_boleto_detect',
            'payment_method' => 'boleto',
            'metadata' => [], // No explicit provider
            'created_at' => now()->subMinutes(10),
        ]);

        Http::fake([
            '*/payments/pay_boleto_detect' => Http::response([
                'id' => 'pay_boleto_detect',
                'status' => 'CONFIRMED',
                'billingType' => 'BOLETO',
            ]),
        ]);

        $job = new CheckPendingPaymentsJob(olderThanMinutes: 5);
        $job->handle(app(\App\Services\Payment\PaymentGatewayManager::class));

        Event::assertDispatched(PaymentConfirmed::class, function ($event) {
            return $event->provider === 'asaas';
        });
    }

    #[Test]
    public function job_respects_limit_parameter(): void
    {
        // Create 5 old pending purchases
        for ($i = 1; $i <= 5; $i++) {
            AddonPurchase::factory()->pending()->forTenant($this->tenant)->create([
                'stripe_payment_intent_id' => "pay_limit_{$i}",
                'payment_method' => 'pix',
                'metadata' => ['provider' => 'asaas'],
                'created_at' => now()->subMinutes(10 + $i),
            ]);
        }

        // Mock all as confirmed
        Http::fake([
            '*/payments/*' => Http::response([
                'id' => 'pay_limit_1',
                'status' => 'CONFIRMED',
                'billingType' => 'PIX',
            ]),
        ]);

        // Only process 2 purchases
        $job = new CheckPendingPaymentsJob(olderThanMinutes: 5, limit: 2);
        $job->handle(app(\App\Services\Payment\PaymentGatewayManager::class));

        // Should only dispatch 2 events (limit respected)
        $this->assertLessThanOrEqual(2, Event::dispatched(PaymentConfirmed::class)->count());
    }

    #[Test]
    public function job_ignores_very_old_payments(): void
    {
        // Create a purchase older than 7 days
        AddonPurchase::factory()->pending()->forTenant($this->tenant)->create([
            'stripe_payment_intent_id' => 'pay_very_old',
            'payment_method' => 'pix',
            'metadata' => ['provider' => 'asaas'],
            'created_at' => now()->subDays(10),
        ]);

        $job = new CheckPendingPaymentsJob(olderThanMinutes: 5);
        $job->handle(app(\App\Services\Payment\PaymentGatewayManager::class));

        // Should not check payments older than 7 days
        Event::assertNotDispatched(PaymentConfirmed::class);
        Event::assertNotDispatched(PaymentFailed::class);
    }

    #[Test]
    public function job_ignores_purchases_without_payment_id(): void
    {
        // Create a purchase without payment ID
        AddonPurchase::factory()->pending()->forTenant($this->tenant)->create([
            'stripe_payment_intent_id' => null,
            'payment_method' => 'pix',
            'created_at' => now()->subMinutes(10),
        ]);

        $job = new CheckPendingPaymentsJob(olderThanMinutes: 5);
        $job->handle(app(\App\Services\Payment\PaymentGatewayManager::class));

        // Should not try to check payment without ID
        Event::assertNotDispatched(PaymentConfirmed::class);
        Event::assertNotDispatched(PaymentFailed::class);
    }

    #[Test]
    public function job_handles_api_errors_gracefully(): void
    {
        $purchase = AddonPurchase::factory()->pending()->forTenant($this->tenant)->create([
            'stripe_payment_intent_id' => 'pay_error',
            'payment_method' => 'pix',
            'metadata' => ['provider' => 'asaas'],
            'created_at' => now()->subMinutes(10),
        ]);

        // Mock API error
        Http::fake([
            '*/payments/pay_error' => Http::response(['error' => 'Not found'], 404),
        ]);

        // Should not throw, just log error
        $job = new CheckPendingPaymentsJob(olderThanMinutes: 5);
        $job->handle(app(\App\Services\Payment\PaymentGatewayManager::class));

        // No events dispatched due to error
        Event::assertNotDispatched(PaymentConfirmed::class);
        Event::assertNotDispatched(PaymentFailed::class);
    }

    #[Test]
    public function job_has_correct_tags_for_horizon(): void
    {
        $job = new CheckPendingPaymentsJob;

        $tags = $job->tags();

        $this->assertContains('payment', $tags);
        $this->assertContains('pending-check', $tags);
    }

    #[Test]
    public function job_has_correct_queue_settings(): void
    {
        $job = new CheckPendingPaymentsJob;

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(300, $job->timeout);
    }
}
