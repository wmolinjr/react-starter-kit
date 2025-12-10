<?php

declare(strict_types=1);

namespace Tests\Unit\Payment;

use App\Events\Payment\WebhookReceived;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WebhookReceivedEventTest extends TestCase
{
    #[Test]
    public function it_stores_provider_and_payload(): void
    {
        $event = new WebhookReceived(
            provider: 'stripe',
            payload: ['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test']]],
            headers: ['Stripe-Signature' => 'test_sig']
        );

        $this->assertEquals('stripe', $event->provider);
        $this->assertEquals(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test']]], $event->payload);
        $this->assertEquals(['Stripe-Signature' => 'test_sig'], $event->headers);
    }

    #[Test]
    public function it_returns_event_type(): void
    {
        $event = new WebhookReceived(
            provider: 'stripe',
            payload: ['type' => 'customer.subscription.created']
        );

        $this->assertEquals('customer.subscription.created', $event->getType());
    }

    #[Test]
    public function it_returns_empty_type_when_not_present(): void
    {
        $event = new WebhookReceived(
            provider: 'stripe',
            payload: []
        );

        $this->assertEquals('', $event->getType());
    }

    #[Test]
    public function it_returns_data_from_payload(): void
    {
        $event = new WebhookReceived(
            provider: 'stripe',
            payload: [
                'type' => 'checkout.session.completed',
                'data' => [
                    'object' => ['id' => 'cs_test', 'customer' => 'cus_123'],
                ],
            ]
        );

        $this->assertEquals(['object' => ['id' => 'cs_test', 'customer' => 'cus_123']], $event->getData());
        $this->assertEquals('cs_test', $event->getData('object.id'));
        $this->assertEquals('cus_123', $event->getData('object.customer'));
        $this->assertEquals('default', $event->getData('nonexistent', 'default'));
    }

    #[Test]
    public function it_returns_object_from_stripe_payload(): void
    {
        $event = new WebhookReceived(
            provider: 'stripe',
            payload: [
                'type' => 'checkout.session.completed',
                'data' => [
                    'object' => ['id' => 'cs_test', 'amount_total' => 1000],
                ],
            ]
        );

        $object = $event->getObject();

        $this->assertEquals(['id' => 'cs_test', 'amount_total' => 1000], $object);
    }

    #[Test]
    public function it_checks_event_type(): void
    {
        $event = new WebhookReceived(
            provider: 'stripe',
            payload: ['type' => 'checkout.session.completed']
        );

        $this->assertTrue($event->isType('checkout.session.completed'));
        $this->assertFalse($event->isType('customer.subscription.created'));
    }

    #[Test]
    public function it_checks_provider(): void
    {
        $stripeEvent = new WebhookReceived(
            provider: 'stripe',
            payload: ['type' => 'checkout.session.completed']
        );

        $asaasEvent = new WebhookReceived(
            provider: 'asaas',
            payload: ['event' => 'PAYMENT_CONFIRMED']
        );

        $this->assertTrue($stripeEvent->isFromProvider('stripe'));
        $this->assertFalse($stripeEvent->isFromProvider('asaas'));

        $this->assertTrue($asaasEvent->isFromProvider('asaas'));
        $this->assertFalse($asaasEvent->isFromProvider('stripe'));
    }
}
