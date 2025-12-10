<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Central\Payment;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Trait for models that can have subscriptions (Customer).
 *
 * Provides subscription management without coupling to any specific payment provider.
 */
trait HasSubscriptions
{
    /**
     * Get all subscriptions for this customer.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'customer_id');
    }

    /**
     * Get all payments for this customer.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'customer_id');
    }

    /**
     * Get a specific subscription by type.
     */
    public function subscription(string $type = 'default'): ?Subscription
    {
        return $this->subscriptions()
            ->where('type', $type)
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->first();
    }

    /**
     * Get subscription for a specific tenant.
     */
    public function subscriptionForTenant(Tenant $tenant, string $type = 'default'): ?Subscription
    {
        return $this->subscriptions()
            ->where('tenant_id', $tenant->id)
            ->where('type', $type)
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->first();
    }

    /**
     * Check if customer has an active subscription.
     */
    public function subscribed(string $type = 'default'): bool
    {
        $subscription = $this->subscription($type);

        return $subscription !== null && $subscription->isActive();
    }

    /**
     * Check if customer is on trial.
     */
    public function onTrial(string $type = 'default'): bool
    {
        $subscription = $this->subscription($type);

        return $subscription !== null && $subscription->onTrial();
    }

    /**
     * Check if any subscription is on grace period.
     */
    public function onGracePeriod(string $type = 'default'): bool
    {
        $subscription = $this->subscription($type);

        return $subscription !== null && $subscription->onGracePeriod();
    }

    /**
     * Get all active subscriptions.
     */
    public function activeSubscriptions(): Collection
    {
        return $this->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->get();
    }

    /**
     * Check if customer has any active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->exists();
    }

    /**
     * Get total monthly billing across all subscriptions.
     */
    public function getTotalMonthlyBilling(): int
    {
        return (int) $this->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->where('billing_period', 'monthly')
            ->sum('amount');
    }

    /**
     * Get total yearly billing across all subscriptions.
     */
    public function getTotalYearlyBilling(): int
    {
        return (int) $this->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->where('billing_period', 'yearly')
            ->sum('amount');
    }
}
