<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Contracts\Payment\PaymentMethodGatewayInterface;
use App\Contracts\Payment\SubscriptionGatewayInterface;
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
     * Create Stripe gateway driver.
     */
    protected function createStripeDriver(): PaymentGatewayInterface
    {
        $config = $this->config->get('payment.drivers.stripe', []);

        return new Gateways\StripeGateway($config);
    }

    /**
     * Create Asaas gateway driver.
     */
    protected function createAsaasDriver(): PaymentGatewayInterface
    {
        $config = $this->config->get('payment.drivers.asaas', []);

        return new Gateways\AsaasGateway($config);
    }

    /**
     * Create PagSeguro gateway driver.
     */
    protected function createPagseguroDriver(): PaymentGatewayInterface
    {
        $config = $this->config->get('payment.drivers.pagseguro', []);

        return new Gateways\PagSeguroGateway($config);
    }

    /**
     * Create MercadoPago gateway driver.
     */
    protected function createMercadopagoDriver(): PaymentGatewayInterface
    {
        $config = $this->config->get('payment.drivers.mercadopago', []);

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
