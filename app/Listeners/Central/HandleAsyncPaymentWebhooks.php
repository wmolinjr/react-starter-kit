<?php

declare(strict_types=1);

namespace App\Listeners\Central;

use App\Enums\AddonStatus;
use App\Enums\BillingPeriod;
use App\Events\Payment\PaymentConfirmed;
use App\Events\Payment\PaymentFailed;
use App\Models\Central\Addon;
use App\Models\Central\AddonSubscription;
use App\Services\Central\AddonService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Handle Async Payment Webhooks
 *
 * Processes PaymentConfirmed and PaymentFailed events for async payment methods
 * like PIX and Boleto that don't confirm immediately at checkout.
 */
class HandleAsyncPaymentWebhooks implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     */
    public string $queue = 'high';

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    public function __construct(
        protected AddonService $addonService
    ) {}

    /**
     * Handle PaymentConfirmed event.
     */
    public function handlePaymentConfirmed(PaymentConfirmed $event): void
    {
        $purchase = $event->purchase;

        // Skip if already completed (idempotency)
        if ($purchase->isCompleted()) {
            Log::info('Async payment already confirmed, skipping', [
                'purchase_id' => $purchase->id,
                'provider' => $event->provider,
            ]);

            return;
        }

        // Mark purchase as completed
        $purchase->update([
            'stripe_payment_intent_id' => $event->paymentIntentId,
            'metadata' => array_merge($purchase->metadata ?? [], $event->metadata),
        ]);
        $purchase->markAsCompleted();

        // Create or activate associated subscription
        $this->activateAddonSubscription($purchase);

        // Sync tenant limits
        if ($purchase->tenant) {
            $this->addonService->syncTenantLimits($purchase->tenant);
        }

        Log::info('Async payment confirmed successfully', [
            'purchase_id' => $purchase->id,
            'provider' => $event->provider,
            'payment_intent' => $event->paymentIntentId,
            'addon_slug' => $purchase->addon_slug,
        ]);
    }

    /**
     * Handle PaymentFailed event.
     */
    public function handlePaymentFailed(PaymentFailed $event): void
    {
        $purchase = $event->purchase;

        // Skip if already processed
        if ($purchase->isFailed() || $purchase->isCompleted()) {
            Log::info('Async payment already processed, skipping', [
                'purchase_id' => $purchase->id,
                'status' => $purchase->status,
            ]);

            return;
        }

        // Mark purchase as failed
        $purchase->update([
            'metadata' => array_merge($purchase->metadata ?? [], $event->metadata),
        ]);
        $purchase->markAsFailed($event->reason);

        // Cancel any pending subscription
        $pendingSubscription = AddonSubscription::where('tenant_id', $purchase->tenant_id)
            ->where('addon_slug', $purchase->addon_slug)
            ->where('status', AddonStatus::PENDING)
            ->first();

        if ($pendingSubscription) {
            $pendingSubscription->cancel('Payment failed: '.$event->reason);
        }

        Log::warning('Async payment failed', [
            'purchase_id' => $purchase->id,
            'provider' => $event->provider,
            'reason' => $event->reason,
            'addon_slug' => $purchase->addon_slug,
        ]);
    }

    /**
     * Activate addon subscription after successful payment.
     */
    protected function activateAddonSubscription($purchase): void
    {
        // Check if subscription already exists and is active
        $existingSubscription = AddonSubscription::where('tenant_id', $purchase->tenant_id)
            ->where('addon_slug', $purchase->addon_slug)
            ->whereIn('status', [AddonStatus::ACTIVE, AddonStatus::PENDING])
            ->first();

        if ($existingSubscription) {
            // Activate pending subscription
            if ($existingSubscription->status === AddonStatus::PENDING) {
                $existingSubscription->update([
                    'status' => AddonStatus::ACTIVE,
                    'started_at' => now(),
                ]);

                Log::info('Activated pending subscription', [
                    'subscription_id' => $existingSubscription->id,
                ]);
            }

            return;
        }

        // Create new subscription from purchase
        $addon = Addon::where('slug', $purchase->addon_slug)->first();

        if (! $addon) {
            Log::warning('Addon not found for purchase activation', [
                'addon_slug' => $purchase->addon_slug,
                'purchase_id' => $purchase->id,
            ]);

            return;
        }

        AddonSubscription::create([
            'tenant_id' => $purchase->tenant_id,
            'addon_slug' => $purchase->addon_slug,
            'addon_type' => $addon->type->value,
            'name' => $addon->name,
            'description' => $addon->description,
            'quantity' => $purchase->quantity,
            'price' => $purchase->amount_paid / max($purchase->quantity, 1),
            'billing_period' => BillingPeriod::ONE_TIME,
            'status' => AddonStatus::ACTIVE,
            'started_at' => now(),
            'expires_at' => $purchase->valid_until,
        ]);

        Log::info('Created addon subscription from async payment', [
            'purchase_id' => $purchase->id,
            'addon_slug' => $purchase->addon_slug,
        ]);
    }

    /**
     * Handle job failure.
     */
    public function failed(PaymentConfirmed|PaymentFailed $event, \Throwable $exception): void
    {
        $purchase = $event->purchase;

        Log::error('HandleAsyncPaymentWebhooks job failed', [
            'purchase_id' => $purchase->id,
            'event_type' => $event instanceof PaymentConfirmed ? 'confirmed' : 'failed',
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
