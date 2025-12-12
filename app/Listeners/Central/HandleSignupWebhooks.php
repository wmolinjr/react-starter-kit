<?php

declare(strict_types=1);

namespace App\Listeners\Central;

use App\Events\Payment\WebhookReceived;
use App\Jobs\Central\CompleteSignupJob;
use App\Models\Central\PendingSignup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Handle Signup Webhooks
 *
 * Listens to WebhookReceived events and processes signup-related payment confirmations.
 * Supports Stripe Checkout sessions and async payments (PIX, Boleto).
 */
class HandleSignupWebhooks implements ShouldQueue
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

    /**
     * Handle the webhook received event.
     */
    public function handle(WebhookReceived $event): void
    {
        // Route to appropriate handler based on event type
        match ($event->getType()) {
            // Stripe Checkout completed (card payments)
            'checkout.session.completed' => $this->handleStripeCheckoutCompleted($event),

            // Stripe invoice paid (subscription renewals - not signup related)
            'invoice.paid' => null,

            // PIX/Boleto payment confirmed
            'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($event),

            // Asaas payment confirmed
            'PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED' => $this->handleAsaasPaymentConfirmed($event),

            // Default: ignore non-signup events
            default => null,
        };
    }

    /**
     * Handle Stripe checkout.session.completed event.
     *
     * This is fired when a user completes Stripe Checkout for a new subscription.
     */
    protected function handleStripeCheckoutCompleted(WebhookReceived $event): void
    {
        $object = $event->getObject();
        $metadata = $object['metadata'] ?? [];

        // Check if this is a signup checkout (has signup_id in metadata)
        $signupId = $metadata['signup_id'] ?? null;
        if (! $signupId) {
            // Not a signup checkout, ignore
            return;
        }

        // Check if it's a new customer signup
        $signupType = $metadata['signup_type'] ?? null;
        if ($signupType !== 'new_customer') {
            // Not a new customer signup, might be addon purchase etc.
            return;
        }

        $signup = PendingSignup::find($signupId);
        if (! $signup) {
            Log::warning('Signup not found for checkout session', [
                'signup_id' => $signupId,
                'session_id' => $object['id'] ?? null,
            ]);

            return;
        }

        // Already completed (idempotency)
        if ($signup->isCompleted()) {
            Log::info('Signup already completed, skipping', [
                'signup_id' => $signupId,
            ]);

            return;
        }

        // Mark as paid
        $signup->markAsPaid();

        // Extract Stripe customer ID for later use
        $stripeCustomerId = $object['customer'] ?? null;
        $subscriptionId = $object['subscription'] ?? null;

        // Dispatch job to complete signup
        CompleteSignupJob::dispatch($signup, [
            'provider' => 'stripe',
            'provider_customer_id' => $stripeCustomerId,
            'provider_subscription_id' => $subscriptionId,
        ]);

        Log::info('Signup checkout completed, dispatched completion job', [
            'signup_id' => $signupId,
            'stripe_customer_id' => $stripeCustomerId,
            'subscription_id' => $subscriptionId,
        ]);
    }

    /**
     * Handle Stripe payment_intent.succeeded event.
     *
     * This is fired for PIX/Boleto payments when they're confirmed.
     */
    protected function handlePaymentIntentSucceeded(WebhookReceived $event): void
    {
        $object = $event->getObject();
        $paymentIntentId = $object['id'] ?? null;

        if (! $paymentIntentId) {
            return;
        }

        // Check if this payment intent is related to a signup
        $metadata = $object['metadata'] ?? [];
        $signupId = $metadata['signup_id'] ?? null;

        // If no signup_id in metadata, try to find by provider_payment_id
        $signup = $signupId
            ? PendingSignup::find($signupId)
            : PendingSignup::byPaymentId($paymentIntentId, 'stripe')->first();

        if (! $signup) {
            // Not a signup payment, ignore
            return;
        }

        if ($signup->isCompleted()) {
            Log::info('Signup already completed, skipping', [
                'signup_id' => $signup->id,
            ]);

            return;
        }

        $signup->markAsPaid();

        CompleteSignupJob::dispatch($signup, [
            'provider' => 'stripe',
            'payment_intent_id' => $paymentIntentId,
        ]);

        Log::info('Signup PIX/Boleto payment confirmed, dispatched completion job', [
            'signup_id' => $signup->id,
            'payment_intent_id' => $paymentIntentId,
        ]);
    }

    /**
     * Handle Asaas payment confirmation events.
     */
    protected function handleAsaasPaymentConfirmed(WebhookReceived $event): void
    {
        if ($event->provider !== 'asaas') {
            return;
        }

        $payload = $event->payload;
        $payment = $payload['payment'] ?? [];
        $paymentId = $payment['id'] ?? null;
        $externalReference = $payment['externalReference'] ?? '';

        if (! $paymentId) {
            return;
        }

        // Check if this is a signup payment (reference starts with signup_)
        if (! str_starts_with($externalReference, 'signup_')) {
            // Not a signup payment, ignore
            return;
        }

        $signupId = str_replace('signup_', '', $externalReference);
        $signup = PendingSignup::find($signupId);

        if (! $signup) {
            Log::warning('Signup not found for Asaas payment', [
                'signup_id' => $signupId,
                'payment_id' => $paymentId,
            ]);

            return;
        }

        if ($signup->isCompleted()) {
            Log::info('Signup already completed, skipping', [
                'signup_id' => $signup->id,
            ]);

            return;
        }

        $signup->markAsPaid();

        CompleteSignupJob::dispatch($signup, [
            'provider' => 'asaas',
            'provider_payment_id' => $paymentId,
        ]);

        Log::info('Signup Asaas payment confirmed, dispatched completion job', [
            'signup_id' => $signup->id,
            'payment_id' => $paymentId,
        ]);
    }

    /**
     * Handle job failure.
     */
    public function failed(WebhookReceived $event, \Throwable $exception): void
    {
        Log::error('HandleSignupWebhooks job failed', [
            'event_type' => $event->getType(),
            'provider' => $event->provider,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
