<?php

declare(strict_types=1);

namespace App\Listeners\Central;

use App\Events\Payment\WebhookReceived;
use App\Jobs\Central\SyncTenantPermissions;
use App\Models\Central\Customer;
use App\Models\Central\Plan;
use App\Services\Central\PlanPermissionResolver;
use Illuminate\Support\Facades\Log;

/**
 * SyncPermissionsOnSubscriptionChange
 *
 * Listens for payment provider webhook events related to subscription changes
 * and syncs tenant permissions accordingly.
 *
 * SUPPORTED PROVIDERS:
 * - Stripe: Handles subscription lifecycle events
 *   - customer.subscription.updated (plan upgrade/downgrade)
 *   - customer.subscription.deleted (subscription canceled)
 *   - customer.subscription.created (new subscription)
 * - Asaas: Handles one-time payment confirmations
 *   - Permission sync is done in checkout completion flow
 *
 * ARCHITECTURE:
 * - Updates tenant's plan_id based on provider price ID
 * - Dispatches SyncTenantPermissions job for async processing
 * - Detects downgrade for proper permission cleanup
 */
class SyncPermissionsOnSubscriptionChange
{
    /**
     * Provider-specific event handlers.
     */
    protected array $providerHandlers = [
        'stripe' => 'handleStripeEvent',
        'asaas' => 'handleAsaasEvent',
    ];

    /**
     * Event types we handle per provider.
     */
    protected array $handledEvents = [
        'stripe' => [
            'customer.subscription.updated',
            'customer.subscription.deleted',
            'customer.subscription.created',
        ],
        'asaas' => [
            'PAYMENT_CONFIRMED',
            'PAYMENT_RECEIVED',
        ],
    ];

    /**
     * Handle the webhook event.
     */
    public function handle(WebhookReceived $event): void
    {
        $handler = $this->providerHandlers[$event->provider] ?? null;

        if (! $handler || ! method_exists($this, $handler)) {
            return;
        }

        $this->{$handler}($event);
    }

    /**
     * Handle Stripe subscription events.
     */
    protected function handleStripeEvent(WebhookReceived $event): void
    {
        $eventType = $event->getType();

        if (! in_array($eventType, $this->handledEvents['stripe'] ?? [])) {
            return;
        }

        Log::info("SyncPermissionsOnSubscriptionChange: Handling Stripe {$eventType}");

        match ($eventType) {
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event),
            'customer.subscription.created' => $this->handleSubscriptionCreated($event),
            default => null,
        };
    }

    /**
     * Handle Asaas payment events.
     *
     * Asaas is used for PIX and Boleto payments which don't have native
     * subscription support. Permission sync for Asaas payments is handled
     * in the checkout completion flow when the payment is confirmed.
     */
    protected function handleAsaasEvent(WebhookReceived $event): void
    {
        // Asaas payment confirmations are typically handled in the checkout flow
        // where the SignupService completes the signup after payment confirmation.
        // This is a placeholder for future Asaas subscription-like features.

        $eventType = $event->getType();
        Log::debug("SyncPermissionsOnSubscriptionChange: Asaas event {$eventType} - handled in checkout flow");
    }

    /**
     * Handle subscription updated (plan change).
     */
    protected function handleSubscriptionUpdated(WebhookReceived $event): void
    {
        $object = $event->getObject();
        $providerCustomerId = $object['customer'] ?? null;
        $status = $object['status'] ?? null;

        if (! $providerCustomerId) {
            return;
        }

        // Only process active subscriptions
        if ($status !== 'active' && $status !== 'trialing') {
            return;
        }

        $customer = $this->findCustomerByProviderId($event->provider, $providerCustomerId);

        if (! $customer) {
            Log::warning("SyncPermissionsOnSubscriptionChange: Customer not found for provider customer {$providerCustomerId}");

            return;
        }

        // Get the new price ID from the subscription
        $priceId = $this->extractPriceId($event->payload);

        if (! $priceId) {
            return;
        }

        // Find the plan by provider price ID
        $newPlan = $this->findPlanByPriceId($priceId);

        if (! $newPlan) {
            Log::warning("SyncPermissionsOnSubscriptionChange: Plan not found for price {$priceId}");

            return;
        }

        // Update all tenants owned by this customer
        foreach ($customer->ownedTenants as $tenant) {
            $oldPlan = $tenant->plan;
            $isDowngrade = $this->isDowngrade($oldPlan, $newPlan);

            if ($tenant->plan_id !== $newPlan->id) {
                Log::info("SyncPermissionsOnSubscriptionChange: Updating tenant {$tenant->id} from plan {$oldPlan?->slug} to {$newPlan->slug}");

                $tenant->update(['plan_id' => $newPlan->id]);

                // Dispatch async job for permission sync
                SyncTenantPermissions::dispatch($tenant, $isDowngrade);
            }
        }
    }

    /**
     * Handle subscription deleted (canceled).
     */
    protected function handleSubscriptionDeleted(WebhookReceived $event): void
    {
        $object = $event->getObject();
        $providerCustomerId = $object['customer'] ?? null;

        if (! $providerCustomerId) {
            return;
        }

        $customer = $this->findCustomerByProviderId($event->provider, $providerCustomerId);

        if (! $customer) {
            return;
        }

        Log::info("SyncPermissionsOnSubscriptionChange: Subscription canceled for customer {$customer->id}");

        // Sync permissions for all tenants owned by this customer
        foreach ($customer->ownedTenants as $tenant) {
            SyncTenantPermissions::dispatch($tenant, isDowngrade: true);
        }
    }

    /**
     * Handle new subscription created.
     */
    protected function handleSubscriptionCreated(WebhookReceived $event): void
    {
        $object = $event->getObject();
        $providerCustomerId = $object['customer'] ?? null;
        $status = $object['status'] ?? null;

        if (! $providerCustomerId || $status !== 'active') {
            return;
        }

        $customer = $this->findCustomerByProviderId($event->provider, $providerCustomerId);

        if (! $customer) {
            return;
        }

        $priceId = $this->extractPriceId($event->payload);
        $newPlan = $this->findPlanByPriceId($priceId);

        if (! $newPlan) {
            return;
        }

        foreach ($customer->ownedTenants as $tenant) {
            if ($tenant->plan_id !== $newPlan->id) {
                Log::info("SyncPermissionsOnSubscriptionChange: New subscription for tenant {$tenant->id} with plan {$newPlan->slug}");

                $tenant->update(['plan_id' => $newPlan->id]);
                SyncTenantPermissions::dispatch($tenant, isDowngrade: false);
            }
        }
    }

    /**
     * Find customer by provider ID.
     */
    protected function findCustomerByProviderId(string $provider, string $providerCustomerId): ?Customer
    {
        return Customer::whereJsonContains("provider_ids->{$provider}", $providerCustomerId)->first();
    }

    /**
     * Extract price ID from webhook payload.
     */
    protected function extractPriceId(array $payload): ?string
    {
        return $payload['data']['object']['items']['data'][0]['price']['id'] ?? null;
    }

    /**
     * Find plan by provider price ID.
     */
    protected function findPlanByPriceId(string $priceId): ?Plan
    {
        // Plans have prices stored as JSON with stripe_price_id
        return Plan::whereJsonContains('prices', [['stripe_price_id' => $priceId]])->first()
            ?? Plan::where('stripe_price_id', $priceId)->first();
    }

    /**
     * Check if this is a downgrade (loses permissions).
     */
    protected function isDowngrade(?Plan $oldPlan, Plan $newPlan): bool
    {
        if (! $oldPlan) {
            return false; // New subscription is not a downgrade
        }

        $resolver = app(PlanPermissionResolver::class);

        return $resolver->isDowngrade($oldPlan, $newPlan);
    }
}
