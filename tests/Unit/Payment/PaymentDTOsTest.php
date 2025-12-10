<?php

declare(strict_types=1);

namespace Tests\Unit\Payment;

use App\DTOs\Payment\ChargeResult;
use App\DTOs\Payment\PaymentMethodResult;
use App\DTOs\Payment\RefundResult;
use App\DTOs\Payment\SetupIntentResult;
use App\DTOs\Payment\SubscriptionResult;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PaymentDTOsTest extends TestCase
{
    #[Test]
    public function charge_result_holds_successful_charge_data(): void
    {
        $result = new ChargeResult(
            success: true,
            status: 'succeeded',
            providerPaymentId: 'pi_test_123',
            providerData: ['receipt_url' => 'https://receipt.url']
        );

        $this->assertTrue($result->success);
        $this->assertEquals('pi_test_123', $result->providerPaymentId);
        $this->assertEquals('succeeded', $result->status);
        $this->assertEquals('https://receipt.url', $result->providerData['receipt_url']);
        $this->assertNull($result->failureMessage);
    }

    #[Test]
    public function charge_result_holds_failed_charge_data(): void
    {
        $result = new ChargeResult(
            success: false,
            status: 'failed',
            failureMessage: 'Card declined'
        );

        $this->assertFalse($result->success);
        $this->assertNull($result->providerPaymentId);
        $this->assertEquals('failed', $result->status);
        $this->assertEquals('Card declined', $result->failureMessage);
    }

    #[Test]
    public function charge_result_static_constructors_work(): void
    {
        $success = ChargeResult::success('pi_test', 'paid', ['data' => 'test']);
        $this->assertTrue($success->success);
        $this->assertEquals('pi_test', $success->providerPaymentId);

        $pending = ChargeResult::pending('pi_pending', ['qr_code' => 'data']);
        $this->assertTrue($pending->success);
        $this->assertEquals('pending', $pending->status);
        $this->assertTrue($pending->requiresAction());

        $failed = ChargeResult::failed('Payment declined', 'card_declined');
        $this->assertFalse($failed->success);
        $this->assertEquals('card_declined', $failed->failureCode);
    }

    #[Test]
    public function refund_result_holds_refund_data(): void
    {
        $result = new RefundResult(
            success: true,
            status: 'refunded',
            providerRefundId: 're_test_123',
            amountRefunded: 500
        );

        $this->assertTrue($result->success);
        $this->assertEquals('re_test_123', $result->providerRefundId);
        $this->assertEquals('refunded', $result->status);
        $this->assertEquals(500, $result->amountRefunded);
    }

    #[Test]
    public function refund_result_static_constructors_work(): void
    {
        $success = RefundResult::success('re_123', 1000);
        $this->assertTrue($success->success);
        $this->assertEquals('refunded', $success->status);

        $pending = RefundResult::pending('re_456', 500);
        $this->assertTrue($pending->success);
        $this->assertEquals('pending', $pending->status);

        $failed = RefundResult::failed('Insufficient funds');
        $this->assertFalse($failed->success);
        $this->assertEquals('Insufficient funds', $failed->failureMessage);
    }

    #[Test]
    public function subscription_result_holds_subscription_data(): void
    {
        $periodStart = Carbon::now();
        $periodEnd = Carbon::now()->addMonth();
        $trialEnd = Carbon::now()->addDays(14);

        $result = new SubscriptionResult(
            success: true,
            status: 'trialing',
            providerSubscriptionId: 'sub_test_123',
            currentPeriodStart: $periodStart,
            currentPeriodEnd: $periodEnd,
            trialEndsAt: $trialEnd,
            providerData: ['plan' => 'pro']
        );

        $this->assertTrue($result->success);
        $this->assertEquals('sub_test_123', $result->providerSubscriptionId);
        $this->assertEquals('trialing', $result->status);
        $this->assertEquals($periodStart, $result->currentPeriodStart);
        $this->assertEquals($periodEnd, $result->currentPeriodEnd);
        $this->assertEquals($trialEnd, $result->trialEndsAt);
        $this->assertEquals('pro', $result->providerData['plan']);
    }

    #[Test]
    public function subscription_result_checks_active_and_trial_status(): void
    {
        $active = new SubscriptionResult(success: true, status: 'active');
        $this->assertTrue($active->isActive());
        $this->assertFalse($active->onTrial());

        $trialing = new SubscriptionResult(
            success: true,
            status: 'trialing',
            trialEndsAt: Carbon::now()->addDays(7)
        );
        $this->assertTrue($trialing->isActive());
        $this->assertTrue($trialing->onTrial());

        $canceled = new SubscriptionResult(success: true, status: 'canceled');
        $this->assertFalse($canceled->isActive());
    }

    #[Test]
    public function payment_method_result_holds_payment_method_data(): void
    {
        $result = new PaymentMethodResult(
            success: true,
            providerMethodId: 'pm_test_123',
            type: 'card',
            brand: 'visa',
            last4: '4242',
            expMonth: 12,
            expYear: 2025,
            isDefault: true
        );

        $this->assertTrue($result->success);
        $this->assertEquals('pm_test_123', $result->providerMethodId);
        $this->assertEquals('card', $result->type);
        $this->assertTrue($result->isDefault);
        $this->assertEquals('visa', $result->brand);
        $this->assertEquals('4242', $result->last4);
        $this->assertEquals(12, $result->expMonth);
        $this->assertEquals(2025, $result->expYear);
    }

    #[Test]
    public function payment_method_result_static_constructors_work(): void
    {
        $card = PaymentMethodResult::card('pm_123', 'mastercard', '5555', 6, 2026, true);
        $this->assertEquals('card', $card->type);
        $this->assertEquals('mastercard', $card->brand);
        $this->assertEquals('mastercard •••• 5555', $card->getDisplayLabel());

        $pix = PaymentMethodResult::pix('pm_pix');
        $this->assertEquals('pix', $pix->type);
        $this->assertEquals('PIX', $pix->getDisplayLabel());

        $boleto = PaymentMethodResult::boleto('pm_boleto');
        $this->assertEquals('boleto', $boleto->type);
        $this->assertEquals('Boleto Bancário', $boleto->getDisplayLabel());

        $failed = PaymentMethodResult::failed('Invalid card');
        $this->assertFalse($failed->success);
    }

    #[Test]
    public function setup_intent_result_holds_setup_intent_data(): void
    {
        $result = new SetupIntentResult(
            success: true,
            clientSecret: 'seti_secret_123',
            providerIntentId: 'seti_test_123',
            publishableKey: 'pk_test_123',
            providerData: ['usage' => 'off_session']
        );

        $this->assertTrue($result->success);
        $this->assertEquals('seti_secret_123', $result->clientSecret);
        $this->assertEquals('seti_test_123', $result->providerIntentId);
        $this->assertEquals('pk_test_123', $result->publishableKey);
        $this->assertEquals('off_session', $result->providerData['usage']);
    }

    #[Test]
    public function setup_intent_result_provides_client_data(): void
    {
        $result = SetupIntentResult::success('secret_123', 'seti_123', 'pk_test');

        $clientData = $result->getClientData();

        $this->assertEquals('secret_123', $clientData['client_secret']);
        $this->assertEquals('pk_test', $clientData['publishable_key']);
    }
}
