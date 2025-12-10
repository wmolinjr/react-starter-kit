<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Contracts\Payment\PaymentMethodGatewayInterface;
use App\Contracts\Payment\SubscriptionGatewayInterface;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Support\ServiceProvider;

/**
 * Payment Service Provider
 *
 * Registers the PaymentGatewayManager and binds payment interfaces.
 */
class PaymentServiceProvider extends ServiceProvider
{
    /**
     * Register payment services.
     */
    public function register(): void
    {
        // Register the PaymentGatewayManager as a singleton
        $this->app->singleton('payment', function ($app) {
            return new PaymentGatewayManager($app);
        });

        // Bind interface to default driver
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            return $app->make('payment')->driver();
        });

        // Bind subscription interface to default driver (if supported)
        $this->app->bind(SubscriptionGatewayInterface::class, function ($app) {
            return $app->make('payment')->subscriptionGateway();
        });

        // Bind payment method interface to default driver (if supported)
        $this->app->bind(PaymentMethodGatewayInterface::class, function ($app) {
            return $app->make('payment')->paymentMethodGateway();
        });
    }

    /**
     * Bootstrap payment services.
     */
    public function boot(): void
    {
        // Publish config if needed
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/payment.php' => config_path('payment.php'),
            ], 'payment-config');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            'payment',
            PaymentGatewayInterface::class,
            SubscriptionGatewayInterface::class,
            PaymentMethodGatewayInterface::class,
        ];
    }
}
