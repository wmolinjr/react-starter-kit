<?php

declare(strict_types=1);

namespace App\Services\Payment\Gateways;

use App\Contracts\Payment\CheckoutGatewayInterface;
use App\Contracts\Payment\InvoiceGatewayInterface;
use App\Contracts\Payment\MeteredBillingGatewayInterface;
use App\Contracts\Payment\PaymentGatewayInterface;
use App\Contracts\Payment\PaymentMethodGatewayInterface;
use App\Contracts\Payment\ProductPriceGatewayInterface;
use App\Contracts\Payment\SubscriptionGatewayInterface;
use App\DTOs\Payment\ChargeResult;
use App\DTOs\Payment\PaymentMethodResult;
use App\DTOs\Payment\RefundResult;
use App\DTOs\Payment\SetupIntentResult;
use App\DTOs\Payment\SubscriptionResult;
use App\Models\Central\Customer;
use App\Models\Central\Payment;
use App\Models\Central\PaymentMethod;
use App\Models\Central\Subscription;
use Carbon\Carbon;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\Customer as StripeCustomer;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod as StripePaymentMethod;
use Stripe\Refund;
use Stripe\SetupIntent;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;
use Stripe\Webhook;

/**
 * Stripe Payment Gateway Implementation
 *
 * Implements full Stripe integration including:
 * - One-time charges
 * - Subscriptions
 * - Payment methods management
 * - Webhook processing
 */
class StripeGateway implements CheckoutGatewayInterface, InvoiceGatewayInterface, MeteredBillingGatewayInterface, PaymentGatewayInterface, PaymentMethodGatewayInterface, ProductPriceGatewayInterface, SubscriptionGatewayInterface
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;

        // Configure Stripe
        Stripe::setApiKey($this->config['secret'] ?? '');

        if (! empty($this->config['options']['api_version'])) {
            Stripe::setApiVersion($this->config['options']['api_version']);
        }
    }

    // =========================================================================
    // PaymentGatewayInterface Implementation
    // =========================================================================

    public function getIdentifier(): string
    {
        return 'stripe';
    }

    public function getDisplayName(): string
    {
        return 'Stripe';
    }

    public function getSupportedTypes(): array
    {
        return $this->config['payment_types'] ?? ['card'];
    }

    public function getSupportedCurrencies(): array
    {
        return ['USD', 'EUR', 'BRL', 'GBP', 'CAD', 'AUD', 'JPY'];
    }

    public function isAvailable(): bool
    {
        return ! empty($this->config['enabled'])
            && ! empty($this->config['secret'])
            && ! empty($this->config['key']);
    }

    public function ensureCustomer(Customer $customer): string
    {
        // Check if customer already exists in Stripe
        $stripeId = $customer->getProviderCustomerId('stripe');

        if ($stripeId) {
            return $stripeId;
        }

        try {
            // Create customer in Stripe
            $stripeCustomer = StripeCustomer::create([
                'email' => $customer->email,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'metadata' => [
                    'customer_id' => $customer->id,
                ],
            ]);

            // Store Stripe customer ID
            $customer->setProviderCustomerId('stripe', $stripeCustomer->id);

            return $stripeCustomer->id;
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to create Stripe customer: {$e->getMessage()}", 0, $e);
        }
    }

    public function charge(Customer $customer, int $amount, array $options = []): ChargeResult
    {
        try {
            $stripeCustomerId = $this->ensureCustomer($customer);

            $params = [
                'amount' => $amount,
                'currency' => strtolower($options['currency'] ?? $this->config['currency'] ?? 'brl'),
                'customer' => $stripeCustomerId,
                'metadata' => array_merge(
                    ['customer_id' => $customer->id],
                    $options['metadata'] ?? []
                ),
            ];

            if (! empty($options['payment_method_id'])) {
                $paymentMethod = PaymentMethod::find($options['payment_method_id']);
                if ($paymentMethod) {
                    $params['payment_method'] = $paymentMethod->provider_method_id;
                    $params['confirm'] = true;
                    $params['off_session'] = true;
                }
            }

            if (! empty($options['description'])) {
                $params['description'] = $options['description'];
            }

            if (! empty($options['return_url'])) {
                $params['return_url'] = $options['return_url'];
            }

            $paymentIntent = PaymentIntent::create($params);

            $status = $this->mapPaymentIntentStatus($paymentIntent->status);

            return new ChargeResult(
                success: in_array($status, ['paid', 'pending', 'requires_action']),
                status: $status,
                providerPaymentId: $paymentIntent->id,
                providerData: [
                    'client_secret' => $paymentIntent->client_secret,
                    'status' => $paymentIntent->status,
                ],
            );
        } catch (ApiErrorException $e) {
            return ChargeResult::failed(
                $e->getMessage(),
                $e->getStripeCode(),
                ['error' => $e->getJsonBody()]
            );
        }
    }

    public function refund(Payment $payment, ?int $amount = null): RefundResult
    {
        try {
            $params = [
                'payment_intent' => $payment->provider_payment_id,
            ];

            if ($amount !== null) {
                $params['amount'] = $amount;
            }

            $refund = Refund::create($params);

            $amountRefunded = $refund->amount;

            if ($refund->status === 'succeeded') {
                return RefundResult::success(
                    $refund->id,
                    $amountRefunded,
                    ['refund' => $refund->toArray()]
                );
            }

            if ($refund->status === 'pending') {
                return RefundResult::pending($refund->id, $amountRefunded);
            }

            return RefundResult::failed("Refund failed with status: {$refund->status}");
        } catch (ApiErrorException $e) {
            return RefundResult::failed($e->getMessage());
        }
    }

    public function checkStatus(Payment $payment): string
    {
        try {
            $paymentIntent = PaymentIntent::retrieve($payment->provider_payment_id);

            return $this->mapPaymentIntentStatus($paymentIntent->status);
        } catch (ApiErrorException $e) {
            return 'failed';
        }
    }

    public function handleWebhook(array $payload, array $headers): void
    {
        $event = $payload;
        $type = $event['type'] ?? '';

        match ($type) {
            'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($event),
            'payment_intent.payment_failed' => $this->handlePaymentIntentFailed($event),
            'customer.subscription.created' => $this->handleSubscriptionCreated($event),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event),
            'invoice.paid' => $this->handleInvoicePaid($event),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event),
            default => null,
        };
    }

    public function validateWebhookSignature(string $payload, string $signature): bool
    {
        try {
            $webhookSecret = $this->config['webhook_secret'] ?? '';
            $tolerance = $this->config['webhook_tolerance'] ?? 300;

            Webhook::constructEvent($payload, $signature, $webhookSecret, $tolerance);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // =========================================================================
    // SubscriptionGatewayInterface Implementation
    // =========================================================================

    public function createSubscription(
        Customer $customer,
        string $priceId,
        array $options = []
    ): SubscriptionResult {
        try {
            $stripeCustomerId = $this->ensureCustomer($customer);

            $params = [
                'customer' => $stripeCustomerId,
                'items' => [
                    ['price' => $priceId],
                ],
                'metadata' => array_merge(
                    ['customer_id' => $customer->id],
                    $options['metadata'] ?? []
                ),
            ];

            // Handle trial
            if (! empty($options['trial_days'])) {
                $params['trial_period_days'] = $options['trial_days'];
            }

            // Handle default payment method
            if (! empty($options['payment_method_id'])) {
                $paymentMethod = PaymentMethod::find($options['payment_method_id']);
                if ($paymentMethod) {
                    $params['default_payment_method'] = $paymentMethod->provider_method_id;
                }
            }

            // Add tenant_id to metadata
            if (! empty($options['tenant_id'])) {
                $params['metadata']['tenant_id'] = $options['tenant_id'];
            }

            $stripeSubscription = StripeSubscription::create($params);

            return SubscriptionResult::success(
                $stripeSubscription->id,
                $this->mapSubscriptionStatus($stripeSubscription->status),
                $stripeCustomerId,
                $priceId,
                Carbon::createFromTimestamp($stripeSubscription->current_period_start),
                Carbon::createFromTimestamp($stripeSubscription->current_period_end),
                $stripeSubscription->trial_end
                    ? Carbon::createFromTimestamp($stripeSubscription->trial_end)
                    : null,
                ['subscription' => $stripeSubscription->toArray()]
            );
        } catch (ApiErrorException $e) {
            return SubscriptionResult::failed($e->getMessage(), ['error' => $e->getJsonBody()]);
        }
    }

    public function cancelSubscription(
        Subscription $subscription,
        bool $immediately = false
    ): SubscriptionResult {
        try {
            if ($immediately) {
                $stripeSubscription = StripeSubscription::retrieve($subscription->provider_subscription_id);
                $stripeSubscription->cancel();
            } else {
                $stripeSubscription = StripeSubscription::update($subscription->provider_subscription_id, [
                    'cancel_at_period_end' => true,
                ]);
            }

            $canceledAt = $stripeSubscription->canceled_at
                ? Carbon::createFromTimestamp($stripeSubscription->canceled_at)
                : now();

            $endsAt = $immediately
                ? now()
                : Carbon::createFromTimestamp($stripeSubscription->current_period_end);

            return SubscriptionResult::canceled(
                $stripeSubscription->id,
                $canceledAt,
                $endsAt
            );
        } catch (ApiErrorException $e) {
            return SubscriptionResult::failed($e->getMessage());
        }
    }

    public function updateSubscription(
        Subscription $subscription,
        string $newPriceId,
        array $options = []
    ): SubscriptionResult {
        try {
            // Get current subscription to find item ID
            $stripeSubscription = StripeSubscription::retrieve($subscription->provider_subscription_id);

            $params = [
                'items' => [
                    [
                        'id' => $stripeSubscription->items->data[0]->id,
                        'price' => $newPriceId,
                    ],
                ],
            ];

            // Handle proration
            if (isset($options['prorate'])) {
                $params['proration_behavior'] = $options['prorate'] ? 'create_prorations' : 'none';
            }

            $updated = StripeSubscription::update($subscription->provider_subscription_id, $params);

            return SubscriptionResult::success(
                $updated->id,
                $this->mapSubscriptionStatus($updated->status),
                $updated->customer,
                $newPriceId,
                Carbon::createFromTimestamp($updated->current_period_start),
                Carbon::createFromTimestamp($updated->current_period_end),
                providerData: ['subscription' => $updated->toArray()]
            );
        } catch (ApiErrorException $e) {
            return SubscriptionResult::failed($e->getMessage());
        }
    }

    public function pauseSubscription(Subscription $subscription): SubscriptionResult
    {
        try {
            $stripeSubscription = StripeSubscription::update($subscription->provider_subscription_id, [
                'pause_collection' => [
                    'behavior' => 'void',
                ],
            ]);

            return SubscriptionResult::success(
                $stripeSubscription->id,
                'paused',
                providerData: ['subscription' => $stripeSubscription->toArray()]
            );
        } catch (ApiErrorException $e) {
            return SubscriptionResult::failed($e->getMessage());
        }
    }

    public function resumeSubscription(Subscription $subscription): SubscriptionResult
    {
        try {
            $stripeSubscription = StripeSubscription::update($subscription->provider_subscription_id, [
                'pause_collection' => '',
            ]);

            return SubscriptionResult::success(
                $stripeSubscription->id,
                $this->mapSubscriptionStatus($stripeSubscription->status),
                providerData: ['subscription' => $stripeSubscription->toArray()]
            );
        } catch (ApiErrorException $e) {
            return SubscriptionResult::failed($e->getMessage());
        }
    }

    public function syncSubscription(Subscription $subscription): SubscriptionResult
    {
        try {
            $stripeSubscription = StripeSubscription::retrieve($subscription->provider_subscription_id);

            $status = $this->mapSubscriptionStatus($stripeSubscription->status);

            // Update local subscription
            $subscription->update([
                'status' => $status,
                'current_period_start' => Carbon::createFromTimestamp($stripeSubscription->current_period_start),
                'current_period_end' => Carbon::createFromTimestamp($stripeSubscription->current_period_end),
                'canceled_at' => $stripeSubscription->canceled_at
                    ? Carbon::createFromTimestamp($stripeSubscription->canceled_at)
                    : null,
                'ends_at' => $stripeSubscription->ended_at
                    ? Carbon::createFromTimestamp($stripeSubscription->ended_at)
                    : null,
            ]);

            return SubscriptionResult::success(
                $stripeSubscription->id,
                $status,
                $stripeSubscription->customer,
                $stripeSubscription->items->data[0]->price->id ?? null,
                Carbon::createFromTimestamp($stripeSubscription->current_period_start),
                Carbon::createFromTimestamp($stripeSubscription->current_period_end),
                $stripeSubscription->trial_end
                    ? Carbon::createFromTimestamp($stripeSubscription->trial_end)
                    : null
            );
        } catch (ApiErrorException $e) {
            return SubscriptionResult::failed($e->getMessage());
        }
    }

    public function createCheckoutSession(
        Customer $customer,
        string $priceId,
        array $options = []
    ): string {
        try {
            $stripeCustomerId = $this->ensureCustomer($customer);

            $params = [
                'customer' => $stripeCustomerId,
                'mode' => 'subscription',
                'line_items' => [
                    [
                        'price' => $priceId,
                        'quantity' => 1,
                    ],
                ],
                'success_url' => $options['success_url'],
                'cancel_url' => $options['cancel_url'],
                'metadata' => $options['metadata'] ?? [],
            ];

            if (! empty($options['trial_days'])) {
                $params['subscription_data'] = [
                    'trial_period_days' => $options['trial_days'],
                ];
            }

            $session = CheckoutSession::create($params);

            return $session->url;
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to create checkout session: {$e->getMessage()}", 0, $e);
        }
    }

    public function createBillingPortalSession(Customer $customer, string $returnUrl): string
    {
        try {
            $stripeCustomerId = $this->ensureCustomer($customer);

            $session = \Stripe\BillingPortal\Session::create([
                'customer' => $stripeCustomerId,
                'return_url' => $returnUrl,
            ]);

            return $session->url;
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to create billing portal session: {$e->getMessage()}", 0, $e);
        }
    }

    // =========================================================================
    // PaymentMethodGatewayInterface Implementation
    // =========================================================================

    public function createSetupIntent(Customer $customer): SetupIntentResult
    {
        try {
            $stripeCustomerId = $this->ensureCustomer($customer);

            $setupIntent = SetupIntent::create([
                'customer' => $stripeCustomerId,
                'payment_method_types' => ['card'],
                'metadata' => [
                    'customer_id' => $customer->id,
                ],
            ]);

            return SetupIntentResult::success(
                $setupIntent->client_secret,
                $setupIntent->id,
                $this->config['key'],
                ['setup_intent' => $setupIntent->toArray()]
            );
        } catch (ApiErrorException $e) {
            return SetupIntentResult::failed($e->getMessage());
        }
    }

    public function attachPaymentMethod(
        Customer $customer,
        string $providerMethodId,
        array $options = []
    ): PaymentMethodResult {
        try {
            $stripeCustomerId = $this->ensureCustomer($customer);

            // Attach to customer in Stripe
            $stripeMethod = StripePaymentMethod::retrieve($providerMethodId);
            $stripeMethod->attach(['customer' => $stripeCustomerId]);

            // Get card details
            $card = $stripeMethod->card;

            // Create local payment method
            $paymentMethod = PaymentMethod::create([
                'customer_id' => $customer->id,
                'provider' => 'stripe',
                'provider_method_id' => $providerMethodId,
                'type' => 'card',
                'brand' => $card->brand,
                'last4' => $card->last4,
                'exp_month' => $card->exp_month,
                'exp_year' => $card->exp_year,
                'is_default' => $options['set_as_default'] ?? false,
            ]);

            // Set as default if requested
            if ($options['set_as_default'] ?? false) {
                $this->setDefaultPaymentMethod($customer, $paymentMethod);
            }

            return PaymentMethodResult::card(
                $providerMethodId,
                $card->brand,
                $card->last4,
                $card->exp_month,
                $card->exp_year,
                $paymentMethod->is_default,
                ['payment_method' => $stripeMethod->toArray()]
            );
        } catch (ApiErrorException $e) {
            return PaymentMethodResult::failed($e->getMessage());
        }
    }

    public function detachPaymentMethod(PaymentMethod $paymentMethod): bool
    {
        try {
            $stripeMethod = StripePaymentMethod::retrieve($paymentMethod->provider_method_id);
            $stripeMethod->detach();

            // Soft delete local record
            $paymentMethod->delete();

            return true;
        } catch (ApiErrorException $e) {
            return false;
        }
    }

    public function setDefaultPaymentMethod(Customer $customer, PaymentMethod $paymentMethod): bool
    {
        try {
            $stripeCustomerId = $this->ensureCustomer($customer);

            // Update default in Stripe
            StripeCustomer::update($stripeCustomerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethod->provider_method_id,
                ],
            ]);

            // Update local default
            $paymentMethod->setAsDefault();

            return true;
        } catch (ApiErrorException $e) {
            return false;
        }
    }

    public function listPaymentMethods(Customer $customer): array
    {
        try {
            $stripeCustomerId = $customer->getProviderCustomerId('stripe');

            if (! $stripeCustomerId) {
                return [];
            }

            $methods = StripePaymentMethod::all([
                'customer' => $stripeCustomerId,
                'type' => 'card',
            ]);

            // Get default from customer
            $stripeCustomer = StripeCustomer::retrieve($stripeCustomerId);
            $defaultMethodId = $stripeCustomer->invoice_settings->default_payment_method ?? null;

            return array_map(function ($method) use ($defaultMethodId) {
                $card = $method->card;

                return PaymentMethodResult::card(
                    $method->id,
                    $card->brand,
                    $card->last4,
                    $card->exp_month,
                    $card->exp_year,
                    $method->id === $defaultMethodId
                );
            }, $methods->data);
        } catch (ApiErrorException $e) {
            return [];
        }
    }

    public function syncPaymentMethods(Customer $customer): void
    {
        $remoteMethods = $this->listPaymentMethods($customer);

        foreach ($remoteMethods as $remote) {
            if (! $remote->success) {
                continue;
            }

            // Find or create local payment method
            PaymentMethod::updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'provider' => 'stripe',
                    'provider_method_id' => $remote->providerMethodId,
                ],
                [
                    'type' => $remote->type,
                    'brand' => $remote->brand,
                    'last4' => $remote->last4,
                    'exp_month' => $remote->expMonth,
                    'exp_year' => $remote->expYear,
                    'is_default' => $remote->isDefault,
                ]
            );
        }
    }

    // =========================================================================
    // Subscription Item Methods (Multi-Item Subscriptions / Addons)
    // =========================================================================

    public function retrieveSubscription(string $subscriptionId): array
    {
        try {
            $subscription = StripeSubscription::retrieve($subscriptionId);

            return $subscription->toArray();
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to retrieve subscription: {$e->getMessage()}", 0, $e);
        }
    }

    public function addSubscriptionItem(
        Subscription $subscription,
        string $priceId,
        int $quantity = 1
    ): array {
        try {
            $item = \Stripe\SubscriptionItem::create([
                'subscription' => $subscription->provider_subscription_id,
                'price' => $priceId,
                'quantity' => $quantity,
            ]);

            return [
                'provider_item_id' => $item->id,
                'quantity' => $item->quantity,
            ];
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to add subscription item: {$e->getMessage()}", 0, $e);
        }
    }

    public function updateSubscriptionItem(
        Subscription $subscription,
        string $priceId,
        int $quantity
    ): void {
        try {
            // Find the subscription item by price ID
            $stripeSubscription = StripeSubscription::retrieve($subscription->provider_subscription_id);

            $itemId = null;
            foreach ($stripeSubscription->items->data as $item) {
                if ($item->price->id === $priceId) {
                    $itemId = $item->id;
                    break;
                }
            }

            if (! $itemId) {
                throw new \RuntimeException("Subscription item with price {$priceId} not found");
            }

            \Stripe\SubscriptionItem::update($itemId, [
                'quantity' => $quantity,
            ]);
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to update subscription item: {$e->getMessage()}", 0, $e);
        }
    }

    public function removeSubscriptionItem(
        Subscription $subscription,
        string $priceId
    ): void {
        try {
            // Find the subscription item by price ID
            $stripeSubscription = StripeSubscription::retrieve($subscription->provider_subscription_id);

            $itemId = null;
            foreach ($stripeSubscription->items->data as $item) {
                if ($item->price->id === $priceId) {
                    $itemId = $item->id;
                    break;
                }
            }

            if (! $itemId) {
                // Item not found, nothing to remove
                return;
            }

            \Stripe\SubscriptionItem::retrieve($itemId)->delete();
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to remove subscription item: {$e->getMessage()}", 0, $e);
        }
    }

    // =========================================================================
    // Webhook Handlers
    // =========================================================================

    protected function handlePaymentIntentSucceeded(array $event): void
    {
        $paymentIntent = $event['data']['object'];

        $payment = Payment::where('provider', 'stripe')
            ->where('provider_payment_id', $paymentIntent['id'])
            ->first();

        if ($payment) {
            $payment->markAsPaid(now());
        }
    }

    protected function handlePaymentIntentFailed(array $event): void
    {
        $paymentIntent = $event['data']['object'];

        $payment = Payment::where('provider', 'stripe')
            ->where('provider_payment_id', $paymentIntent['id'])
            ->first();

        if ($payment) {
            $error = $paymentIntent['last_payment_error'] ?? [];
            $payment->markAsFailed(
                $error['code'] ?? null,
                $error['message'] ?? 'Payment failed'
            );
        }
    }

    protected function handleSubscriptionCreated(array $event): void
    {
        // Handle subscription creation webhook if needed
    }

    protected function handleSubscriptionUpdated(array $event): void
    {
        $stripeSubscription = $event['data']['object'];

        $subscription = Subscription::where('provider', 'stripe')
            ->where('provider_subscription_id', $stripeSubscription['id'])
            ->first();

        if ($subscription) {
            $subscription->update([
                'status' => $this->mapSubscriptionStatus($stripeSubscription['status']),
                'current_period_start' => Carbon::createFromTimestamp($stripeSubscription['current_period_start']),
                'current_period_end' => Carbon::createFromTimestamp($stripeSubscription['current_period_end']),
            ]);
        }
    }

    protected function handleSubscriptionDeleted(array $event): void
    {
        $stripeSubscription = $event['data']['object'];

        $subscription = Subscription::where('provider', 'stripe')
            ->where('provider_subscription_id', $stripeSubscription['id'])
            ->first();

        if ($subscription) {
            $subscription->markAsCanceled();
        }
    }

    protected function handleInvoicePaid(array $event): void
    {
        // Handle invoice paid webhook if needed
    }

    protected function handleInvoicePaymentFailed(array $event): void
    {
        $invoice = $event['data']['object'];

        // Mark subscription as past_due
        if (! empty($invoice['subscription'])) {
            $subscription = Subscription::where('provider', 'stripe')
                ->where('provider_subscription_id', $invoice['subscription'])
                ->first();

            if ($subscription) {
                $subscription->update(['status' => 'past_due']);
            }
        }
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    protected function mapPaymentIntentStatus(string $status): string
    {
        return match ($status) {
            'succeeded' => 'paid',
            'processing' => 'processing',
            'requires_action', 'requires_confirmation' => 'requires_action',
            'requires_payment_method' => 'pending',
            'canceled' => 'canceled',
            default => 'failed',
        };
    }

    protected function mapSubscriptionStatus(string $status): string
    {
        return match ($status) {
            'active' => 'active',
            'trialing' => 'trialing',
            'past_due' => 'past_due',
            'canceled' => 'canceled',
            'unpaid' => 'past_due',
            'incomplete' => 'incomplete',
            'incomplete_expired' => 'canceled',
            'paused' => 'paused',
            default => $status,
        };
    }

    // =========================================================================
    // ProductPriceGatewayInterface Implementation
    // =========================================================================

    public function createProduct(array $data): array
    {
        try {
            $product = \Stripe\Product::create($data);

            return $product->toArray();
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to create product: {$e->getMessage()}", 0, $e);
        }
    }

    public function updateProduct(string $productId, array $data): array
    {
        try {
            $product = \Stripe\Product::update($productId, $data);

            return $product->toArray();
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to update product: {$e->getMessage()}", 0, $e);
        }
    }

    public function retrieveProduct(string $productId): array
    {
        try {
            $product = \Stripe\Product::retrieve($productId);

            return $product->toArray();
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to retrieve product: {$e->getMessage()}", 0, $e);
        }
    }

    public function archiveProduct(string $productId): bool
    {
        try {
            \Stripe\Product::update($productId, ['active' => false]);

            return true;
        } catch (ApiErrorException $e) {
            return false;
        }
    }

    public function createPrice(array $data): array
    {
        try {
            $price = \Stripe\Price::create($data);

            return $price->toArray();
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to create price: {$e->getMessage()}", 0, $e);
        }
    }

    public function updatePrice(string $priceId, array $data): array
    {
        try {
            $price = \Stripe\Price::update($priceId, $data);

            return $price->toArray();
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to update price: {$e->getMessage()}", 0, $e);
        }
    }

    public function listPrices(string $productId, bool $activeOnly = true): array
    {
        try {
            $params = ['product' => $productId];
            if ($activeOnly) {
                $params['active'] = true;
            }

            $prices = \Stripe\Price::all($params);

            return array_map(fn ($p) => $p->toArray(), $prices->data);
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to list prices: {$e->getMessage()}", 0, $e);
        }
    }

    public function archivePrice(string $priceId): bool
    {
        try {
            \Stripe\Price::update($priceId, ['active' => false]);

            return true;
        } catch (ApiErrorException $e) {
            return false;
        }
    }

    // =========================================================================
    // CheckoutGatewayInterface Implementation
    // =========================================================================

    public function createSubscriptionCheckout(
        Customer $customer,
        string $priceId,
        array $options = []
    ): array {
        try {
            $stripeCustomerId = $this->ensureCustomer($customer);

            $params = [
                'customer' => $stripeCustomerId,
                'mode' => 'subscription',
                'line_items' => [
                    [
                        'price' => $priceId,
                        'quantity' => 1,
                    ],
                ],
                'success_url' => $options['success_url'],
                'cancel_url' => $options['cancel_url'],
                'metadata' => $options['metadata'] ?? [],
            ];

            if (! empty($options['trial_days'])) {
                $params['subscription_data'] = [
                    'trial_period_days' => $options['trial_days'],
                ];
            }

            if (! empty($options['locale'])) {
                $params['locale'] = $options['locale'];
            }

            if (! empty($options['allow_promotion_codes'])) {
                $params['allow_promotion_codes'] = true;
            }

            $session = CheckoutSession::create($params);

            return [
                'id' => $session->id,
                'url' => $session->url,
            ];
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to create subscription checkout: {$e->getMessage()}", 0, $e);
        }
    }

    public function createOneTimeCheckout(
        Customer $customer,
        array $lineItem,
        array $options = []
    ): array {
        try {
            $stripeCustomerId = $this->ensureCustomer($customer);

            $params = [
                'customer' => $stripeCustomerId,
                'mode' => 'payment',
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => strtolower($options['currency'] ?? config('payment.currency', 'brl')),
                            'product_data' => [
                                'name' => $lineItem['name'],
                                'description' => $lineItem['description'] ?? null,
                            ],
                            'unit_amount' => $lineItem['price'],
                        ],
                        'quantity' => $lineItem['quantity'] ?? 1,
                    ],
                ],
                'success_url' => $options['success_url'],
                'cancel_url' => $options['cancel_url'],
                'metadata' => $options['metadata'] ?? [],
            ];

            if (! empty($options['locale'])) {
                $params['locale'] = $options['locale'];
            }

            $session = CheckoutSession::create($params);

            return [
                'id' => $session->id,
                'url' => $session->url,
            ];
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to create one-time checkout: {$e->getMessage()}", 0, $e);
        }
    }

    public function createCheckoutWithItems(
        Customer $customer,
        array $lineItems,
        array $options = []
    ): array {
        try {
            $stripeCustomerId = $this->ensureCustomer($customer);

            $params = [
                'customer' => $stripeCustomerId,
                'mode' => $options['mode'] ?? 'payment',
                'line_items' => $lineItems,
                'success_url' => $options['success_url'],
                'cancel_url' => $options['cancel_url'],
                'metadata' => $options['metadata'] ?? [],
            ];

            if (! empty($options['locale'])) {
                $params['locale'] = $options['locale'];
            }

            if (! empty($options['subscription_data'])) {
                $params['subscription_data'] = $options['subscription_data'];
            }

            $session = CheckoutSession::create($params);

            return [
                'id' => $session->id,
                'url' => $session->url,
            ];
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to create checkout: {$e->getMessage()}", 0, $e);
        }
    }

    public function retrieveCheckoutSession(string $sessionId): array
    {
        try {
            $session = CheckoutSession::retrieve($sessionId);

            return $session->toArray();
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to retrieve checkout session: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Create a checkout session with raw parameters.
     *
     * Used for signup flows where we don't have a Customer yet.
     */
    public function createCheckoutSessionRaw(array $params): array
    {
        try {
            $session = CheckoutSession::create($params);

            return [
                'id' => $session->id,
                'url' => $session->url,
            ];
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to create checkout session: {$e->getMessage()}", 0, $e);
        }
    }

    public function createPortalSession(Customer $customer, string $returnUrl): array
    {
        try {
            $stripeCustomerId = $this->ensureCustomer($customer);

            $session = \Stripe\BillingPortal\Session::create([
                'customer' => $stripeCustomerId,
                'return_url' => $returnUrl,
            ]);

            return [
                'id' => $session->id,
                'url' => $session->url,
            ];
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to create portal session: {$e->getMessage()}", 0, $e);
        }
    }

    // =========================================================================
    // InvoiceGatewayInterface Implementation
    // =========================================================================

    public function retrieveInvoice(string $invoiceId): array
    {
        try {
            $invoice = \Stripe\Invoice::retrieve($invoiceId);

            return $invoice->toArray();
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to retrieve invoice: {$e->getMessage()}", 0, $e);
        }
    }

    public function getUpcomingInvoice(Customer $customer, ?string $subscriptionId = null): ?array
    {
        try {
            $stripeCustomerId = $customer->getProviderCustomerId('stripe');
            if (! $stripeCustomerId) {
                return null;
            }

            $params = ['customer' => $stripeCustomerId];
            if ($subscriptionId) {
                $params['subscription'] = $subscriptionId;
            }

            $invoice = \Stripe\Invoice::upcoming($params);

            return $invoice->toArray();
        } catch (ApiErrorException $e) {
            // Upcoming invoice may not exist
            return null;
        }
    }

    public function listInvoices(Customer $customer, int $limit = 10): array
    {
        try {
            $stripeCustomerId = $customer->getProviderCustomerId('stripe');
            if (! $stripeCustomerId) {
                return [];
            }

            $invoices = \Stripe\Invoice::all([
                'customer' => $stripeCustomerId,
                'limit' => $limit,
            ]);

            return array_map(fn ($i) => $i->toArray(), $invoices->data);
        } catch (ApiErrorException $e) {
            return [];
        }
    }

    public function getInvoicePdfUrl(string $invoiceId): ?string
    {
        try {
            $invoice = \Stripe\Invoice::retrieve($invoiceId);

            return $invoice->invoice_pdf;
        } catch (ApiErrorException $e) {
            return null;
        }
    }

    // =========================================================================
    // MeteredBillingGatewayInterface Implementation
    // =========================================================================

    public function createMeterEvent(array $usage): array
    {
        try {
            $meterEvent = \Stripe\Billing\MeterEvent::create([
                'event_name' => $usage['event_name'],
                'payload' => [
                    'stripe_customer_id' => $usage['identifier'],
                    'value' => (string) $usage['value'],
                ],
                'timestamp' => $usage['timestamp'] ?? time(),
            ]);

            return $meterEvent->toArray();
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to create meter event: {$e->getMessage()}", 0, $e);
        }
    }

    public function getUsageSummary(string $subscriptionItemId): array
    {
        try {
            $summaries = \Stripe\SubscriptionItem::allUsageRecordSummaries($subscriptionItemId);

            $total = 0;
            foreach ($summaries->data as $summary) {
                $total += $summary->total_usage;
            }

            return [
                'total_usage' => $total,
                'summaries' => array_map(fn ($s) => $s->toArray(), $summaries->data),
            ];
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to get usage summary: {$e->getMessage()}", 0, $e);
        }
    }

    // =========================================================================
    // Additional Helper Methods
    // =========================================================================

    /**
     * Create a refund by payment intent ID.
     */
    public function createRefund(string $paymentIntentId, ?int $amount = null): array
    {
        try {
            $params = ['payment_intent' => $paymentIntentId];
            if ($amount !== null) {
                $params['amount'] = $amount;
            }

            $refund = Refund::create($params);

            return $refund->toArray();
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to create refund: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Create a customer directly with custom data.
     */
    public function createCustomer(array $data): array
    {
        try {
            $customer = StripeCustomer::create($data);

            return $customer->toArray();
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to create customer: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Update subscription directly with custom params.
     */
    public function updateSubscriptionRaw(string $subscriptionId, array $params): array
    {
        try {
            $subscription = StripeSubscription::update($subscriptionId, $params);

            return $subscription->toArray();
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to update subscription: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Cancel subscription directly.
     */
    public function cancelSubscriptionRaw(string $subscriptionId): array
    {
        try {
            $subscription = StripeSubscription::retrieve($subscriptionId);
            $subscription->cancel();

            return $subscription->toArray();
        } catch (ApiErrorException $e) {
            throw new \RuntimeException("Failed to cancel subscription: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get the Stripe publishable key.
     */
    public function getPublishableKey(): ?string
    {
        return $this->config['key'] ?? null;
    }

    /**
     * Get the Stripe secret key.
     * Used for admin operations that require direct API access.
     */
    public function getSecretKey(): ?string
    {
        return $this->config['secret'] ?? null;
    }

    /**
     * Check if we're using a test key.
     */
    public function isTestMode(): bool
    {
        $secretKey = $this->getSecretKey();

        return $secretKey && str_starts_with($secretKey, 'sk_test_');
    }
}
