<?php

declare(strict_types=1);

namespace App\Contracts\Payment;

use App\DTOs\Payment\SubscriptionResult;
use App\Models\Central\Customer;
use App\Models\Central\Subscription;

/**
 * Subscription Gateway Interface
 *
 * Extends PaymentGatewayInterface with subscription capabilities.
 * Not all gateways support subscriptions (e.g., PIX-only gateways).
 */
interface SubscriptionGatewayInterface extends PaymentGatewayInterface
{
    /**
     * Create a new subscription.
     *
     * @param  Customer  $customer  The customer subscribing
     * @param  string  $priceId  Internal price/plan ID
     * @param array{
     *   tenant_id?: string,
     *   billing_period?: string,
     *   trial_days?: int,
     *   payment_method_id?: string,
     *   metadata?: array,
     * } $options
     */
    public function createSubscription(
        Customer $customer,
        string $priceId,
        array $options = []
    ): SubscriptionResult;

    /**
     * Cancel a subscription.
     *
     * @param  Subscription  $subscription  The subscription to cancel
     * @param  bool  $immediately  Cancel now or at period end
     */
    public function cancelSubscription(
        Subscription $subscription,
        bool $immediately = false
    ): SubscriptionResult;

    /**
     * Update/change subscription plan.
     *
     * @param  Subscription  $subscription  Current subscription
     * @param  string  $newPriceId  New price/plan ID
     * @param array{
     *   prorate?: bool,
     *   billing_cycle_anchor?: string,
     * } $options
     */
    public function updateSubscription(
        Subscription $subscription,
        string $newPriceId,
        array $options = []
    ): SubscriptionResult;

    /**
     * Pause a subscription.
     */
    public function pauseSubscription(Subscription $subscription): SubscriptionResult;

    /**
     * Resume a paused subscription.
     */
    public function resumeSubscription(Subscription $subscription): SubscriptionResult;

    /**
     * Sync subscription status from provider.
     *
     * Fetches current status from provider and updates local record.
     */
    public function syncSubscription(Subscription $subscription): SubscriptionResult;

    /**
     * Create a checkout session for subscription.
     *
     * Returns URL for hosted checkout page.
     *
     * @param  Customer  $customer  The customer
     * @param  string  $priceId  Price/plan ID
     * @param array{
     *   success_url: string,
     *   cancel_url: string,
     *   trial_days?: int,
     *   metadata?: array,
     * } $options
     * @return string Checkout URL
     */
    public function createCheckoutSession(
        Customer $customer,
        string $priceId,
        array $options = []
    ): string;

    /**
     * Create a billing portal session.
     *
     * Returns URL for customer to manage subscription.
     *
     * @param  Customer  $customer  The customer
     * @param  string  $returnUrl  URL to return after portal
     * @return string Portal URL
     */
    public function createBillingPortalSession(
        Customer $customer,
        string $returnUrl
    ): string;

    /**
     * Retrieve subscription details from provider.
     */
    public function retrieveSubscription(string $subscriptionId): array;

    // =========================================================================
    // Subscription Items (Multi-Item Subscriptions / Addons)
    // =========================================================================

    /**
     * Add a price/item to an existing subscription.
     *
     * Used for addons that are billed as part of the main subscription.
     *
     * @param  Subscription  $subscription  The subscription
     * @param  string  $priceId  Provider price ID
     * @param  int  $quantity  Quantity of items
     * @return array{provider_item_id: string, quantity: int}
     */
    public function addSubscriptionItem(
        Subscription $subscription,
        string $priceId,
        int $quantity = 1
    ): array;

    /**
     * Update quantity of a subscription item.
     *
     * @param  Subscription  $subscription  The subscription
     * @param  string  $priceId  Provider price ID
     * @param  int  $quantity  New quantity
     */
    public function updateSubscriptionItem(
        Subscription $subscription,
        string $priceId,
        int $quantity
    ): void;

    /**
     * Remove an item from a subscription.
     *
     * @param  Subscription  $subscription  The subscription
     * @param  string  $priceId  Provider price ID
     */
    public function removeSubscriptionItem(
        Subscription $subscription,
        string $priceId
    ): void;
}
