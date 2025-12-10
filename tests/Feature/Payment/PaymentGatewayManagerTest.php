<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Contracts\Payment\PaymentMethodGatewayInterface;
use App\Contracts\Payment\SubscriptionGatewayInterface;
use App\Services\Payment\Gateways\AsaasGateway;
use App\Services\Payment\Gateways\MercadoPagoGateway;
use App\Services\Payment\Gateways\PagSeguroGateway;
use App\Services\Payment\Gateways\StripeGateway;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentGatewayManagerTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentGatewayManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app('payment');
    }

    #[Test]
    public function it_returns_default_driver(): void
    {
        $driver = $this->manager->getDefaultDriver();

        $this->assertEquals('stripe', $driver);
    }

    #[Test]
    public function it_creates_stripe_gateway(): void
    {
        $gateway = $this->manager->driver('stripe');

        $this->assertInstanceOf(StripeGateway::class, $gateway);
        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
        $this->assertInstanceOf(PaymentMethodGatewayInterface::class, $gateway);
        $this->assertInstanceOf(SubscriptionGatewayInterface::class, $gateway);
    }

    #[Test]
    public function it_creates_asaas_gateway(): void
    {
        $gateway = $this->manager->driver('asaas');

        $this->assertInstanceOf(AsaasGateway::class, $gateway);
        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
        $this->assertInstanceOf(PaymentMethodGatewayInterface::class, $gateway);
        $this->assertInstanceOf(SubscriptionGatewayInterface::class, $gateway);
    }

    #[Test]
    public function it_creates_pagseguro_gateway(): void
    {
        $gateway = $this->manager->driver('pagseguro');

        $this->assertInstanceOf(PagSeguroGateway::class, $gateway);
        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
        $this->assertInstanceOf(PaymentMethodGatewayInterface::class, $gateway);
        $this->assertInstanceOf(SubscriptionGatewayInterface::class, $gateway);
    }

    #[Test]
    public function it_creates_mercadopago_gateway(): void
    {
        $gateway = $this->manager->driver('mercadopago');

        $this->assertInstanceOf(MercadoPagoGateway::class, $gateway);
        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
        $this->assertInstanceOf(PaymentMethodGatewayInterface::class, $gateway);
        $this->assertInstanceOf(SubscriptionGatewayInterface::class, $gateway);
    }

    #[Test]
    public function it_provides_type_safe_gateway_access(): void
    {
        $gateway = $this->manager->gateway();

        $this->assertInstanceOf(PaymentGatewayInterface::class, $gateway);
    }

    #[Test]
    public function it_provides_subscription_gateway_access(): void
    {
        $gateway = $this->manager->subscriptionGateway();

        $this->assertInstanceOf(SubscriptionGatewayInterface::class, $gateway);
    }

    #[Test]
    public function it_provides_payment_method_gateway_access(): void
    {
        $gateway = $this->manager->paymentMethodGateway();

        $this->assertInstanceOf(PaymentMethodGatewayInterface::class, $gateway);
    }

    #[Test]
    public function it_checks_subscription_support(): void
    {
        $this->assertTrue($this->manager->supportsSubscriptions('stripe'));
        $this->assertTrue($this->manager->supportsSubscriptions('asaas'));
        $this->assertTrue($this->manager->supportsSubscriptions('pagseguro'));
        $this->assertTrue($this->manager->supportsSubscriptions('mercadopago'));
    }

    #[Test]
    public function it_checks_payment_method_support(): void
    {
        $this->assertTrue($this->manager->supportsPaymentMethods('stripe'));
        $this->assertTrue($this->manager->supportsPaymentMethods('asaas'));
        $this->assertTrue($this->manager->supportsPaymentMethods('pagseguro'));
        $this->assertTrue($this->manager->supportsPaymentMethods('mercadopago'));
    }

    #[Test]
    public function it_returns_available_drivers(): void
    {
        $drivers = $this->manager->getAvailableDrivers();

        $this->assertContains('stripe', $drivers);
        $this->assertContains('asaas', $drivers);
        $this->assertContains('pagseguro', $drivers);
        $this->assertContains('mercadopago', $drivers);
    }

    #[Test]
    public function it_checks_if_driver_exists(): void
    {
        $this->assertTrue($this->manager->hasDriver('stripe'));
        $this->assertTrue($this->manager->hasDriver('asaas'));
        $this->assertTrue($this->manager->hasDriver('pagseguro'));
        $this->assertTrue($this->manager->hasDriver('mercadopago'));
        $this->assertFalse($this->manager->hasDriver('nonexistent'));
    }

    #[Test]
    public function it_caches_driver_instances(): void
    {
        $gateway1 = $this->manager->driver('stripe');
        $gateway2 = $this->manager->driver('stripe');

        $this->assertSame($gateway1, $gateway2);
    }

    #[Test]
    public function gateways_return_correct_identifiers(): void
    {
        $this->assertEquals('stripe', $this->manager->driver('stripe')->getIdentifier());
        $this->assertEquals('asaas', $this->manager->driver('asaas')->getIdentifier());
        $this->assertEquals('pagseguro', $this->manager->driver('pagseguro')->getIdentifier());
        $this->assertEquals('mercadopago', $this->manager->driver('mercadopago')->getIdentifier());
    }
}
