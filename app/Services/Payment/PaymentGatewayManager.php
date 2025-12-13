<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payment\CheckoutGatewayInterface;
use App\Contracts\Payment\InvoiceGatewayInterface;
use App\Contracts\Payment\MeteredBillingGatewayInterface;
use App\Contracts\Payment\PaymentGatewayInterface;
use App\Contracts\Payment\PaymentMethodGatewayInterface;
use App\Contracts\Payment\ProductPriceGatewayInterface;
use App\Contracts\Payment\SubscriptionGatewayInterface;
use App\Enums\PaymentGateway;
use App\Services\Central\PaymentSettingsService;
use Illuminate\Support\Manager;
use InvalidArgumentException;

/**
 * PaymentGatewayManager - Multi-Provider Gateway Factory
 *
 * Manages payment gateway instances using Laravel's Manager pattern.
 * Supports multiple providers (Stripe, Asaas, PagSeguro, MercadoPago).
 *
 * Usage:
 *   $gateway = app('payment')->driver('stripe');
 *   $gateway->charge($customer, 1000, 'BRL', ['description' => 'Order #123']);
 *
 *   // Or use default driver:
 *   $gateway = app('payment')->driver();
 *
 * Configuration in config/payment.php:
 *   'default' => env('PAYMENT_DRIVER', 'stripe'),
 *   'drivers' => [
 *       'stripe' => [...],
 *       'asaas' => [...],
 *   ]
 */
class PaymentGatewayManager extends Manager
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('payment.default', 'stripe');
    }

    /**
     * Get merged config for a gateway (DB settings + ENV fallback).
     *
     * Uses PaymentSettingsService to get config from database first,
     * falling back to config file if not configured in DB.
     */
    protected function getMergedConfig(PaymentGateway $gateway): array
    {
        try {
            $service = app(PaymentSettingsService::class);

            return $service->getMergedConfig($gateway);
        } catch (\Exception $e) {
            // Fallback to config file if service fails
            return $this->config->get("payment.drivers.{$gateway->value}", []);
        }
    }

    /**
     * Create Stripe gateway driver.
     */
    protected function createStripeDriver(): PaymentGatewayInterface
    {
        $config = $this->getMergedConfig(PaymentGateway::STRIPE);

        return new Gateways\StripeGateway($config);
    }

    /**
     * Create Asaas gateway driver.
     */
    protected function createAsaasDriver(): PaymentGatewayInterface
    {
        $config = $this->getMergedConfig(PaymentGateway::ASAAS);

        return new Gateways\AsaasGateway($config);
    }

    /**
     * Create PagSeguro gateway driver.
     */
    protected function createPagseguroDriver(): PaymentGatewayInterface
    {
        $config = $this->getMergedConfig(PaymentGateway::PAGSEGURO);

        return new Gateways\PagSeguroGateway($config);
    }

    /**
     * Create MercadoPago gateway driver.
     */
    protected function createMercadopagoDriver(): PaymentGatewayInterface
    {
        $config = $this->getMergedConfig(PaymentGateway::MERCADOPAGO);

        return new Gateways\MercadoPagoGateway($config);
    }

    // =========================================================================
    // Type-Safe Gateway Access
    // =========================================================================

    /**
     * Get gateway as PaymentGatewayInterface.
     */
    public function gateway(?string $driver = null): PaymentGatewayInterface
    {
        return $this->driver($driver);
    }

    /**
     * Get gateway with subscription capabilities.
     *
     * @throws InvalidArgumentException if driver doesn't support subscriptions
     */
    public function subscriptionGateway(?string $driver = null): SubscriptionGatewayInterface
    {
        $gateway = $this->driver($driver);

        if (! $gateway instanceof SubscriptionGatewayInterface) {
            throw new InvalidArgumentException(
                "Driver [{$driver}] does not support subscriptions."
            );
        }

        return $gateway;
    }

    /**
     * Get gateway with payment method capabilities.
     *
     * @throws InvalidArgumentException if driver doesn't support payment methods
     */
    public function paymentMethodGateway(?string $driver = null): PaymentMethodGatewayInterface
    {
        $gateway = $this->driver($driver);

        if (! $gateway instanceof PaymentMethodGatewayInterface) {
            throw new InvalidArgumentException(
                "Driver [{$driver}] does not support payment methods."
            );
        }

        return $gateway;
    }

    /**
     * Get gateway with product/price management capabilities.
     *
     * @throws InvalidArgumentException if driver doesn't support product management
     */
    public function productPriceGateway(?string $driver = null): ProductPriceGatewayInterface
    {
        $gateway = $this->driver($driver);

        if (! $gateway instanceof ProductPriceGatewayInterface) {
            throw new InvalidArgumentException(
                "Driver [{$driver}] does not support product/price management."
            );
        }

        return $gateway;
    }

    /**
     * Get gateway with checkout capabilities.
     *
     * @throws InvalidArgumentException if driver doesn't support checkout
     */
    public function checkoutGateway(?string $driver = null): CheckoutGatewayInterface
    {
        $gateway = $this->driver($driver);

        if (! $gateway instanceof CheckoutGatewayInterface) {
            throw new InvalidArgumentException(
                "Driver [{$driver}] does not support checkout."
            );
        }

        return $gateway;
    }

    /**
     * Get gateway with invoice capabilities.
     *
     * @throws InvalidArgumentException if driver doesn't support invoices
     */
    public function invoiceGateway(?string $driver = null): InvoiceGatewayInterface
    {
        $gateway = $this->driver($driver);

        if (! $gateway instanceof InvoiceGatewayInterface) {
            throw new InvalidArgumentException(
                "Driver [{$driver}] does not support invoices."
            );
        }

        return $gateway;
    }

    /**
     * Get gateway with metered billing capabilities.
     *
     * @throws InvalidArgumentException if driver doesn't support metered billing
     */
    public function meteredBillingGateway(?string $driver = null): MeteredBillingGatewayInterface
    {
        $gateway = $this->driver($driver);

        if (! $gateway instanceof MeteredBillingGatewayInterface) {
            throw new InvalidArgumentException(
                "Driver [{$driver}] does not support metered billing."
            );
        }

        return $gateway;
    }

    /**
     * Get the Stripe gateway directly (type-safe).
     *
     * @return Gateways\StripeGateway
     */
    public function stripe(): Gateways\StripeGateway
    {
        return $this->driver('stripe');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Check if a driver supports subscriptions.
     */
    public function supportsSubscriptions(?string $driver = null): bool
    {
        return $this->driver($driver) instanceof SubscriptionGatewayInterface;
    }

    /**
     * Check if a driver supports payment methods.
     */
    public function supportsPaymentMethods(?string $driver = null): bool
    {
        return $this->driver($driver) instanceof PaymentMethodGatewayInterface;
    }

    /**
     * Get all available drivers.
     */
    public function getAvailableDrivers(): array
    {
        return array_keys($this->config->get('payment.drivers', []));
    }

    /**
     * Check if a driver is available.
     */
    public function hasDriver(string $driver): bool
    {
        return in_array($driver, $this->getAvailableDrivers());
    }

    /**
     * Get driver configuration.
     */
    public function getDriverConfig(string $driver): array
    {
        return $this->config->get("payment.drivers.{$driver}", []);
    }

    /**
     * Check if driver is enabled (has required credentials).
     */
    public function isDriverEnabled(string $driver): bool
    {
        $config = $this->getDriverConfig($driver);

        return ! empty($config['enabled'] ?? false);
    }

    /**
     * Get enabled drivers.
     */
    public function getEnabledDrivers(): array
    {
        return array_filter(
            $this->getAvailableDrivers(),
            fn ($driver) => $this->isDriverEnabled($driver)
        );
    }
}
