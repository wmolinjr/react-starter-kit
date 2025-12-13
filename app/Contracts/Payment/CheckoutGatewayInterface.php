<?php

declare(strict_types=1);

namespace App\Contracts\Payment;

use App\Models\Central\Customer;

/**
 * Interface for gateways that support checkout sessions.
 */
interface CheckoutGatewayInterface
{
    /**
     * Create a checkout session for subscription.
     *
     * @param  array{
     *     success_url: string,
     *     cancel_url: string,
     *     trial_days?: int,
     *     metadata?: array,
     *     locale?: string,
     *     allow_promotion_codes?: bool
     * }  $options
     * @return array{id: string, url: string}
     */
    public function createSubscriptionCheckout(
        Customer $customer,
        string $priceId,
        array $options = []
    ): array;

    /**
     * Create a checkout session for one-time payment.
     *
     * @param  array{
     *     name: string,
     *     description?: string,
     *     price: int,
     *     quantity?: int
     * }  $lineItem
     * @param  array{
     *     success_url: string,
     *     cancel_url: string,
     *     metadata?: array,
     *     locale?: string
     * }  $options
     * @return array{id: string, url: string}
     */
    public function createOneTimeCheckout(
        Customer $customer,
        array $lineItem,
        array $options = []
    ): array;

    /**
     * Create a checkout session with multiple line items.
     *
     * @param  array<array{price?: string, price_data?: array, quantity: int}>  $lineItems
     * @param  array{
     *     mode: 'payment'|'subscription',
     *     success_url: string,
     *     cancel_url: string,
     *     metadata?: array,
     *     locale?: string
     * }  $options
     * @return array{id: string, url: string}
     */
    public function createCheckoutWithItems(
        Customer $customer,
        array $lineItems,
        array $options = []
    ): array;

    /**
     * Retrieve a checkout session.
     *
     * @return array{id: string, status: string, payment_status: string, ...}
     */
    public function retrieveCheckoutSession(string $sessionId): array;

    /**
     * Create a billing portal session.
     *
     * @return array{id: string, url: string}
     */
    public function createPortalSession(Customer $customer, string $returnUrl): array;
}
