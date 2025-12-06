<?php

namespace App\Listeners;

use App\Jobs\SyncTenantPermissions;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Services\Central\PlanPermissionResolver;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * SyncPermissionsOnSubscriptionChange
 *
 * Listens for Stripe webhook events related to subscription changes
 * and syncs tenant permissions accordingly.
 *
 * Handles:
 * - customer.subscription.updated (plan upgrade/downgrade)
 * - customer.subscription.deleted (subscription canceled)
 * - customer.subscription.created (new subscription)
 *
 * ARCHITECTURE:
 * - Updates tenant's plan_id based on Stripe price ID
 * - Dispatches SyncTenantPermissions job for async processing
 * - Detects downgrade for proper permission cleanup
 */
class SyncPermissionsOnSubscriptionChange
{
    /**
     * Stripe event types we handle.
     */
    protected array $handledEvents = [
        'customer.subscription.updated',
        'customer.subscription.deleted',
        'customer.subscription.created',
    ];

    /**
     * Handle the webhook event.
     */
    public function handle(WebhookReceived $event): void
    {
        $eventType = $event->payload['type'] ?? '';

        if (!in_array($eventType, $this->handledEvents)) {
            return;
        }

        Log::info("SyncPermissionsOnSubscriptionChange: Handling {$eventType}");

        match ($eventType) {
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event),
            'customer.subscription.created' => $this->handleSubscriptionCreated($event),
            default => null,
        };
    }

    /**
     * Handle subscription updated (plan change).
     */
    protected function handleSubscriptionUpdated(WebhookReceived $event): void
    {
        $customerId = $event->payload['data']['object']['customer'] ?? null;
        $status = $event->payload['data']['object']['status'] ?? null;

        if (!$customerId) {
            return;
        }

        // Only process active subscriptions
        if ($status !== 'active' && $status !== 'trialing') {
            return;
        }

        $tenant = Tenant::where('stripe_id', $customerId)->first();

        if (!$tenant) {
            Log::warning("SyncPermissionsOnSubscriptionChange: Tenant not found for customer {$customerId}");
            return;
        }

        // Get the new price ID from the subscription
        $priceId = $this->extractPriceId($event->payload);

        if (!$priceId) {
            return;
        }

        // Find the plan by Stripe price ID
        $newPlan = $this->findPlanByPriceId($priceId);

        if (!$newPlan) {
            Log::warning("SyncPermissionsOnSubscriptionChange: Plan not found for price {$priceId}");
            return;
        }

        // Check if this is a downgrade
        $oldPlan = $tenant->plan;
        $isDowngrade = $this->isDowngrade($oldPlan, $newPlan);

        // Update tenant's plan (this will trigger TenantObserver)
        if ($tenant->plan_id !== $newPlan->id) {
            Log::info("SyncPermissionsOnSubscriptionChange: Updating tenant {$tenant->id} from plan {$oldPlan?->slug} to {$newPlan->slug}");

            $tenant->update(['plan_id' => $newPlan->id]);

            // Dispatch async job for permission sync (in addition to observer)
            SyncTenantPermissions::dispatch($tenant, $isDowngrade);
        }
    }

    /**
     * Handle subscription deleted (canceled).
     */
    protected function handleSubscriptionDeleted(WebhookReceived $event): void
    {
        $customerId = $event->payload['data']['object']['customer'] ?? null;

        if (!$customerId) {
            return;
        }

        $tenant = Tenant::where('stripe_id', $customerId)->first();

        if (!$tenant) {
            return;
        }

        Log::info("SyncPermissionsOnSubscriptionChange: Subscription canceled for tenant {$tenant->id}");

        // When subscription is canceled, we might want to:
        // 1. Set plan to a free tier (if exists)
        // 2. Or set plan_id to null
        // For now, we don't change the plan automatically - let the admin decide
        // But we do sync permissions to ensure they reflect current state

        SyncTenantPermissions::dispatch($tenant, isDowngrade: true);
    }

    /**
     * Handle new subscription created.
     */
    protected function handleSubscriptionCreated(WebhookReceived $event): void
    {
        $customerId = $event->payload['data']['object']['customer'] ?? null;
        $status = $event->payload['data']['object']['status'] ?? null;

        if (!$customerId || $status !== 'active') {
            return;
        }

        $tenant = Tenant::where('stripe_id', $customerId)->first();

        if (!$tenant) {
            return;
        }

        $priceId = $this->extractPriceId($event->payload);
        $newPlan = $this->findPlanByPriceId($priceId);

        if ($newPlan && $tenant->plan_id !== $newPlan->id) {
            Log::info("SyncPermissionsOnSubscriptionChange: New subscription for tenant {$tenant->id} with plan {$newPlan->slug}");

            $tenant->update(['plan_id' => $newPlan->id]);
            SyncTenantPermissions::dispatch($tenant, isDowngrade: false);
        }
    }

    /**
     * Extract price ID from webhook payload.
     */
    protected function extractPriceId(array $payload): ?string
    {
        return $payload['data']['object']['items']['data'][0]['price']['id'] ?? null;
    }

    /**
     * Find plan by Stripe price ID.
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
        if (!$oldPlan) {
            return false; // New subscription is not a downgrade
        }

        $resolver = app(PlanPermissionResolver::class);
        return $resolver->isDowngrade($oldPlan, $newPlan);
    }
}
