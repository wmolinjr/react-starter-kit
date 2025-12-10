<?php

declare(strict_types=1);

namespace App\Contracts\Payment;

use App\DTOs\Payment\PaymentMethodResult;
use App\DTOs\Payment\SetupIntentResult;
use App\Models\Central\Customer;
use App\Models\Central\PaymentMethod;

/**
 * Payment Method Gateway Interface
 *
 * Contract for managing customer payment methods.
 */
interface PaymentMethodGatewayInterface
{
    /**
     * Create a setup intent for adding a payment method.
     *
     * Used for securely collecting payment details on frontend.
     */
    public function createSetupIntent(Customer $customer): SetupIntentResult;

    /**
     * Attach a payment method from provider to customer.
     *
     * @param  Customer  $customer  The customer
     * @param  string  $providerMethodId  Payment method ID from provider
     * @param array{
     *   set_as_default?: bool,
     * } $options
     */
    public function attachPaymentMethod(
        Customer $customer,
        string $providerMethodId,
        array $options = []
    ): PaymentMethodResult;

    /**
     * Detach/remove a payment method.
     */
    public function detachPaymentMethod(PaymentMethod $paymentMethod): bool;

    /**
     * Set a payment method as the customer's default.
     */
    public function setDefaultPaymentMethod(
        Customer $customer,
        PaymentMethod $paymentMethod
    ): bool;

    /**
     * List all payment methods for a customer from provider.
     *
     * @return array<PaymentMethodResult>
     */
    public function listPaymentMethods(Customer $customer): array;

    /**
     * Sync payment methods from provider to local database.
     *
     * Creates/updates local PaymentMethod records.
     */
    public function syncPaymentMethods(Customer $customer): void;
}
