<?php

namespace App\Services\Central;

use App\Contracts\Payment\CheckoutGatewayInterface;
use App\Contracts\Payment\PaymentGatewayInterface;
use App\Enums\PaymentGateway;
use App\Exceptions\Central\AddonException;
use App\Models\Central\Customer;
use App\Models\Central\PaymentSetting;
use App\Models\Central\PendingSignup;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SignupService
 *
 * Customer-First Signup Flow:
 * 1. Create Customer + PendingSignup (customer logged in immediately)
 * 2. Update with workspace data and plan selection
 * 3. Process payment (Card/PIX/Boleto)
 * 4. Complete signup after payment confirmation (via webhook) - creates only Tenant
 *
 * Key principles:
 * - Customer is created IMMEDIATELY in Step 1 (before payment)
 * - Tenant is created ONLY after payment is confirmed
 * - Customer is logged in during the entire signup process
 */
class SignupService
{
    protected ?PaymentGatewayInterface $gateway = null;

    public function __construct(
        protected CustomerService $customerService,
        protected PaymentGatewayManager $gatewayManager,
        protected PaymentSettingsService $paymentSettingsService
    ) {
        $this->gateway = $this->gatewayManager->driver();
    }

    /**
     * Check if payment gateway is configured.
     */
    public function isGatewayConfigured(): bool
    {
        return $this->gateway !== null && $this->gateway->isAvailable();
    }

    /**
     * Check if gateway supports checkout operations.
     */
    protected function supportsCheckout(): bool
    {
        return $this->gateway instanceof CheckoutGatewayInterface;
    }

    /**
     * Get the current provider identifier.
     */
    public function getProvider(): string
    {
        return $this->gateway?->getIdentifier() ?? 'unknown';
    }

    // =========================================================================
    // Step 1: Create Customer + PendingSignup (Customer-First Flow)
    // =========================================================================

    /**
     * Create Customer + PendingSignup in a single transaction.
     *
     * Customer-First Flow:
     * 1. Create Customer (billing entity)
     * 2. Create PendingSignup linked to Customer
     * 3. Fire Registered event (email verification - non-blocking)
     *
     * @param  array{name: string, email: string, password: string, locale?: string}  $data
     * @return array{customer: Customer, signup: PendingSignup}
     */
    public function createPendingSignupWithCustomer(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // 1. Create Customer first
            $customer = $this->customerService->registerCustomerOnly([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'locale' => $data['locale'] ?? config('app.locale', 'pt_BR'),
            ]);

            // 2. Create PendingSignup linked to Customer
            $signup = PendingSignup::create([
                'customer_id' => $customer->id,
                'status' => PendingSignup::STATUS_PENDING,
                'expires_at' => now()->addHours(24),
            ]);

            // 3. Fire Registered event (email verification - non-blocking)
            event(new \Illuminate\Auth\Events\Registered($customer));

            Log::info('Customer-first signup created', [
                'customer_id' => $customer->id,
                'signup_id' => $signup->id,
                'email' => $customer->email,
            ]);

            return [
                'customer' => $customer,
                'signup' => $signup,
            ];
        });
    }

    /**
     * Create PendingSignup for an existing Customer.
     *
     * Used when a logged-in customer wants to create an additional workspace.
     */
    public function createPendingSignupForCustomer(Customer $customer): PendingSignup
    {
        $signup = PendingSignup::create([
            'customer_id' => $customer->id,
            'status' => PendingSignup::STATUS_PENDING,
            'expires_at' => now()->addHours(24),
        ]);

        Log::info('PendingSignup created for existing customer', [
            'customer_id' => $customer->id,
            'signup_id' => $signup->id,
        ]);

        return $signup;
    }

    // =========================================================================
    // Step 2: Update Workspace Data
    // =========================================================================

    /**
     * Update pending signup with workspace information.
     *
     * @param  array{workspace_name: string, workspace_slug: string, business_sector: string, plan_id: string, billing_period: string}  $data
     */
    public function updateWorkspace(PendingSignup $signup, array $data): PendingSignup
    {
        if ($signup->isExpired()) {
            throw new AddonException(__('signup.errors.signup_expired'));
        }

        if (! $signup->isPending()) {
            throw new AddonException(__('signup.errors.signup_already_processed'));
        }

        $signup->update([
            'workspace_name' => $data['workspace_name'],
            'workspace_slug' => $data['workspace_slug'],
            'business_sector' => $data['business_sector'],
            'plan_id' => $data['plan_id'],
            'billing_period' => $data['billing_period'],
        ]);

        return $signup->fresh();
    }

    // =========================================================================
    // Step 3: Process Payment
    // =========================================================================

    /**
     * Process payment for the signup.
     *
     * Routes to appropriate gateway based on payment method.
     *
     * @return array{type: string, ...}
     */
    public function processPayment(PendingSignup $signup, string $paymentMethod): array
    {
        if ($signup->isExpired()) {
            throw new AddonException(__('signup.errors.signup_expired'));
        }

        if (! $signup->hasWorkspaceData()) {
            throw new AddonException(__('signup.errors.workspace_data_required'));
        }

        // Resolve gateway from PaymentSettings
        $paymentSetting = $this->resolveGatewayForPaymentMethod($paymentMethod);
        $gateway = $paymentSetting->gateway;

        // Update signup with payment method
        $signup->update([
            'payment_method' => $paymentMethod,
            'payment_provider' => $gateway->value,
        ]);

        // Route to appropriate payment processor
        return match ($paymentMethod) {
            'card' => $this->processCardPayment($signup, $gateway),
            'pix' => $this->processPixPayment($signup, $gateway),
            'boleto' => $this->processBoletoPayment($signup, $gateway),
            default => throw new AddonException(__('signup.errors.invalid_payment_method')),
        };
    }

    /**
     * Process card payment via Stripe Checkout.
     */
    protected function processCardPayment(PendingSignup $signup, PaymentGateway $gateway): array
    {
        return match ($gateway) {
            PaymentGateway::STRIPE => $this->processStripeCardPayment($signup),
            PaymentGateway::ASAAS => $this->processAsaasCardPayment($signup),
            default => throw new AddonException(
                __('billing.errors.gateway_not_supported_for_method', [
                    'gateway' => $gateway->displayName(),
                    'method' => 'card',
                ])
            ),
        };
    }

    /**
     * Process card payment via Stripe Checkout Sessions.
     *
     * Creates a hosted checkout session and returns redirect URL.
     */
    protected function processStripeCardPayment(PendingSignup $signup): array
    {
        // Get Stripe gateway specifically for Stripe card payment
        $stripeGateway = $this->gatewayManager->stripe();
        if (! $stripeGateway || ! $stripeGateway->isAvailable()) {
            throw new AddonException(__('billing.errors.stripe_not_configured'));
        }

        $plan = $signup->plan;
        if (! $plan) {
            throw new AddonException(__('signup.errors.plan_not_found'));
        }

        // Get Stripe price ID based on billing period
        $stripePriceId = $signup->billing_period === 'yearly'
            ? $plan->getProviderPriceId('stripe_yearly')
            : $plan->getProviderPriceId('stripe');

        if (! $stripePriceId) {
            throw new AddonException(__('signup.errors.plan_not_synced_to_stripe'));
        }

        // Build success/cancel URLs
        $baseUrl = config('app.url');
        $successUrl = $baseUrl.'/signup/success?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $baseUrl.'/signup?signup_id='.$signup->id;

        // Create Stripe Checkout session using gateway
        $lineItems = [
            [
                'price' => $stripePriceId,
                'quantity' => 1,
            ],
        ];

        $session = $stripeGateway->createCheckoutSessionRaw([
            'mode' => 'subscription',
            'locale' => stripe_locale(),
            'line_items' => $lineItems,
            'customer_email' => $signup->email,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'signup_id' => $signup->id,
                'signup_type' => 'new_customer',
                'customer_id' => $signup->customer_id,
            ],
            'subscription_data' => [
                'metadata' => [
                    'signup_id' => $signup->id,
                ],
            ],
        ]);

        // Update signup with session info
        $signup->setPaymentSession($session['id'], 'stripe');

        Log::info('Signup Stripe checkout session created', [
            'signup_id' => $signup->id,
            'session_id' => $session['id'],
            'plan_id' => $plan->id,
            'billing_period' => $signup->billing_period,
        ]);

        return [
            'type' => 'redirect',
            'session_id' => $session['id'],
            'url' => $session['url'],
        ];
    }

    /**
     * Process card payment via Asaas.
     *
     * Returns signup ID for frontend to collect card data.
     */
    protected function processAsaasCardPayment(PendingSignup $signup): array
    {
        $plan = $signup->plan;
        if (! $plan) {
            throw new AddonException(__('signup.errors.plan_not_found'));
        }

        $amount = $signup->billing_period === 'yearly'
            ? ($plan->price * 12 * 0.8) // 20% discount for yearly
            : $plan->price;

        // NOTE: Don't mark as processing here - the frontend will show a card form
        // and the status will be updated when card data is submitted via completeAsaasCardPayment

        Log::info('Signup Asaas card checkout initiated', [
            'signup_id' => $signup->id,
            'amount' => $amount,
            'plan_id' => $plan->id,
        ]);

        return [
            'type' => 'asaas_card',
            'signup_id' => $signup->id,
            'amount' => (int) $amount,
            'gateway' => 'asaas',
            'requires_card_data' => true,
            'required_fields' => [
                'card' => ['holder_name', 'number', 'exp_month', 'exp_year', 'cvv'],
                'holder' => ['name', 'email', 'cpf_cnpj', 'postal_code', 'address_number'],
            ],
        ];
    }

    /**
     * Process PIX payment.
     */
    protected function processPixPayment(PendingSignup $signup, PaymentGateway $gateway): array
    {
        $plan = $signup->plan;
        if (! $plan) {
            throw new AddonException(__('signup.errors.plan_not_found'));
        }

        $customer = $signup->customer;
        if (! $customer) {
            throw new AddonException(__('signup.errors.customer_not_found'));
        }

        // PIX only for first payment, subscription will be card-based
        $amount = $signup->billing_period === 'yearly'
            ? ($plan->price * 12 * 0.8)
            : $plan->price;

        $gatewayInstance = $this->gatewayManager->gateway($gateway->value);

        $result = $gatewayInstance->createPixCharge(
            $customer,
            (int) $amount,
            [
                'description' => __('signup.payment.description', [
                    'plan' => $plan->name,
                    'workspace' => $signup->workspace_name,
                ]),
                'reference' => 'signup_'.$signup->id,
                'expires_in_seconds' => 3600, // 1 hour
            ]
        );

        if (! $result->success) {
            // Don't mark as failed - charge creation failed, not the payment itself
            // User should be able to retry (e.g., add CPF/CNPJ and try again)
            throw new AddonException($result->failureMessage ?? __('signup.errors.pix_creation_failed'));
        }

        $signup->setPaymentId($result->providerPaymentId, $gateway->value);

        Log::info('Signup PIX payment created', [
            'signup_id' => $signup->id,
            'payment_id' => $result->providerPaymentId,
            'amount' => $amount,
        ]);

        $pixData = $result->providerData['pix'] ?? [];

        return [
            'type' => 'pix',
            'signup_id' => $signup->id,
            'payment_id' => $result->providerPaymentId,
            'pix' => [
                'qr_code' => $pixData['qr_code'] ?? null,
                'qr_code_base64' => $pixData['qr_code_base64'] ?? null,
                'copy_paste' => $pixData['copy_paste'] ?? $pixData['payload'] ?? null,
                'expires_at' => $pixData['expiration'] ?? $pixData['expiration_date'] ?? now()->addHour()->toIso8601String(),
            ],
        ];
    }

    /**
     * Process Boleto payment.
     */
    protected function processBoletoPayment(PendingSignup $signup, PaymentGateway $gateway): array
    {
        $plan = $signup->plan;
        if (! $plan) {
            throw new AddonException(__('signup.errors.plan_not_found'));
        }

        $customer = $signup->customer;
        if (! $customer) {
            throw new AddonException(__('signup.errors.customer_not_found'));
        }

        $amount = $signup->billing_period === 'yearly'
            ? ($plan->price * 12 * 0.8)
            : $plan->price;

        $gatewayInstance = $this->gatewayManager->gateway($gateway->value);

        $result = $gatewayInstance->createBoletoCharge(
            $customer,
            (int) $amount,
            [
                'description' => __('signup.payment.description', [
                    'plan' => $plan->name,
                    'workspace' => $signup->workspace_name,
                ]),
                'reference' => 'signup_'.$signup->id,
                'due_date' => now()->addDays(3)->format('Y-m-d'),
            ]
        );

        if (! $result->success) {
            // Don't mark as failed - charge creation failed, not the payment itself
            // User should be able to retry (e.g., add CPF/CNPJ and try again)
            throw new AddonException($result->failureMessage ?? __('signup.errors.boleto_creation_failed'));
        }

        $signup->setPaymentId($result->providerPaymentId, $gateway->value);

        Log::info('Signup Boleto payment created', [
            'signup_id' => $signup->id,
            'payment_id' => $result->providerPaymentId,
            'amount' => $amount,
        ]);

        $boletoData = $result->providerData['boleto'] ?? [];

        return [
            'type' => 'boleto',
            'signup_id' => $signup->id,
            'payment_id' => $result->providerPaymentId,
            'boleto' => [
                'barcode' => $boletoData['barcode'] ?? $boletoData['bar_code'] ?? null,
                'digitable_line' => $boletoData['digitable_line'] ?? $boletoData['identification_field'] ?? null,
                'pdf_url' => $boletoData['url'] ?? $boletoData['bank_slip_url'] ?? null,
                'due_date' => $result->providerData['due_date'] ?? now()->addDays(3)->toDateString(),
            ],
        ];
    }

    // =========================================================================
    // Complete Signup (After Payment Confirmation)
    // =========================================================================

    /**
     * Complete the signup after payment is confirmed.
     *
     * Customer-First Flow: Customer already exists, only creates Tenant.
     * Called by webhook handler after payment confirmation.
     *
     * @return array{customer: Customer, tenant: Tenant}
     */
    public function completeSignup(PendingSignup $signup): array
    {
        if ($signup->isCompleted()) {
            return [
                'customer' => $signup->customer,
                'tenant' => $signup->tenant,
            ];
        }

        if ($signup->isFailed() || $signup->isExpired()) {
            throw new AddonException(__('signup.errors.cannot_complete_signup'));
        }

        // Customer-First: Customer must already exist
        $customer = $signup->customer;
        if (! $customer) {
            throw new AddonException(__('signup.errors.customer_not_found'));
        }

        return DB::transaction(function () use ($signup, $customer) {
            // 1. Create Tenant (Customer already exists)
            $tenant = Tenant::create([
                'name' => $signup->workspace_name,
                'slug' => $signup->workspace_slug,
                'business_sector' => $signup->business_sector,
                'customer_id' => $customer->id,
                'plan_id' => $signup->plan_id,
            ]);

            // 2. Attach Customer to Tenant (triggers Resource Syncing)
            $customer->tenants()->attach($tenant);

            // 3. Assign owner role to the synced user
            $tenant->run(function () use ($customer) {
                $user = \App\Models\Tenant\User::where('global_id', $customer->global_id)->first();
                if ($user) {
                    $user->assignRole('owner');
                }
            });

            // 4. Create Subscription record
            $this->createSubscriptionRecord($customer, $tenant, $signup);

            // 5. Mark signup as completed (only tenant_id, customer already set)
            $signup->markAsCompleted($tenant->id);

            Log::info('Customer-first signup completed', [
                'signup_id' => $signup->id,
                'customer_id' => $customer->id,
                'tenant_id' => $tenant->id,
                'plan_id' => $signup->plan_id,
            ]);

            return [
                'customer' => $customer,
                'tenant' => $tenant,
            ];
        });
    }

    /**
     * Create subscription record for the new customer.
     */
    protected function createSubscriptionRecord(Customer $customer, Tenant $tenant, PendingSignup $signup): Subscription
    {
        $plan = $signup->plan;

        // Calculate billing dates
        $currentPeriodStart = now();
        $currentPeriodEnd = $signup->billing_period === 'yearly'
            ? now()->addYear()
            : now()->addMonth();

        return Subscription::create([
            'customer_id' => $customer->id,
            'tenant_id' => $tenant->id,
            'type' => 'default',
            'provider' => $signup->payment_provider,
            'provider_subscription_id' => $signup->provider_session_id ?? $signup->provider_payment_id,
            'provider_customer_id' => null, // Will be updated by webhook if Stripe
            'status' => 'active',
            'billing_period' => $signup->billing_period,
            'price' => $plan->price,
            'currency' => 'brl',
            'current_period_start' => $currentPeriodStart,
            'current_period_end' => $currentPeriodEnd,
            'metadata' => [
                'plan_name' => $plan->name,
                'signup_id' => $signup->id,
            ],
        ]);
    }

    // =========================================================================
    // Status Checking (for Polling)
    // =========================================================================

    /**
     * Check payment status for a pending signup.
     *
     * Used by frontend to poll for payment confirmation.
     */
    public function checkPaymentStatus(PendingSignup $signup): array
    {
        return [
            'status' => $signup->status,
            'is_completed' => $signup->isCompleted(),
            'is_expired' => $signup->isExpired(),
            'tenant_url' => $signup->tenant?->url(),
            'tenant_id' => $signup->tenant_id,
            'failure_reason' => $signup->failure_reason,
        ];
    }

    /**
     * Refresh PIX QR code if expired.
     */
    public function refreshPixQrCode(PendingSignup $signup): array
    {
        if ($signup->payment_method !== 'pix') {
            throw new AddonException(__('signup.errors.not_pix_payment'));
        }

        // Re-process PIX payment
        $gateway = PaymentGateway::from($signup->payment_provider);

        return $this->processPixPayment($signup, $gateway);
    }

    /**
     * Complete Asaas card payment with card data.
     *
     * This is called after the frontend collects card data for Asaas gateway.
     *
     * @param  PendingSignup  $signup  The pending signup
     * @param  array  $cardData  Card details: holder_name, number, exp_month, exp_year, cvv
     * @param  array  $holderInfo  Holder details: name, email, cpf_cnpj, postal_code, address_number
     * @param  array  $options  Additional options (remote_ip, installments)
     * @return array{success: bool, message?: string, tenant_url?: string}
     */
    public function completeAsaasCardPayment(
        PendingSignup $signup,
        array $cardData,
        array $holderInfo,
        array $options = []
    ): array {
        // Validate signup is ready for card payment (pending or processing)
        if (! in_array($signup->status, ['pending', 'processing'])) {
            throw new AddonException(__('signup.errors.invalid_signup_status'));
        }

        if ($signup->payment_method !== 'card') {
            throw new AddonException(__('signup.errors.invalid_payment_method'));
        }

        // Mark as processing now that we're about to charge
        $signup->markAsProcessing();

        $customer = $signup->customer;
        if (! $customer) {
            throw new AddonException(__('signup.errors.customer_not_found'));
        }

        $plan = $signup->plan;
        if (! $plan) {
            throw new AddonException(__('signup.errors.plan_not_found'));
        }

        // Calculate amount
        $amount = $signup->billing_period === 'yearly'
            ? (int) ($plan->price * 12 * 0.8) // 20% discount for yearly
            : (int) $plan->price;

        // Get Asaas gateway instance
        $gateway = $this->gatewayManager->gateway('asaas');

        try {
            // Create card charge with Asaas
            $chargeResult = $gateway->createCardChargeWithData(
                $customer,
                $amount,
                $cardData,
                $holderInfo,
                [
                    'description' => __('signup.payment.description', [
                        'plan' => $plan->name,
                        'workspace' => $signup->workspace_name,
                    ]),
                    'reference' => 'signup_'.$signup->id,
                    'remote_ip' => $options['remote_ip'] ?? null,
                    'installments' => $options['installments'] ?? 1,
                ]
            );

            if (! $chargeResult->success) {
                $signup->markAsFailed($chargeResult->failureMessage ?? 'Card payment failed');

                return [
                    'success' => false,
                    'message' => $chargeResult->failureMessage ?? __('signup.errors.card_payment_failed'),
                ];
            }

            // Store payment ID
            $signup->setPaymentId($chargeResult->providerPaymentId, 'asaas');

            Log::info('Signup Asaas card payment completed', [
                'signup_id' => $signup->id,
                'payment_id' => $chargeResult->providerPaymentId,
                'amount' => $amount,
            ]);

            // Create tenant immediately (card payment is synchronous)
            $result = $this->createTenantFromSignup($signup);

            return [
                'success' => true,
                'tenant_url' => $result['tenant']->url(),
                'card' => [
                    'last_four' => substr($cardData['number'], -4),
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Signup Asaas card payment failed', [
                'signup_id' => $signup->id,
                'error' => $e->getMessage(),
            ]);

            $signup->markAsFailed($e->getMessage());

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Resolve the enabled gateway for a payment method.
     */
    protected function resolveGatewayForPaymentMethod(string $paymentMethod): PaymentSetting
    {
        $setting = $this->paymentSettingsService->getEnabledGatewayForMethod($paymentMethod);

        if (! $setting) {
            throw new AddonException(
                __('billing.errors.payment_method_unavailable', ['method' => $paymentMethod])
            );
        }

        return $setting;
    }

    /**
     * Find pending signup by provider session ID.
     */
    public function findByProviderSession(string $sessionId, string $provider = 'stripe'): ?PendingSignup
    {
        return PendingSignup::bySession($sessionId, $provider)->first();
    }

    /**
     * Find pending signup by Stripe session ID.
     *
     * @deprecated Use findByProviderSession() instead
     */
    public function findByStripeSession(string $sessionId): ?PendingSignup
    {
        return $this->findByProviderSession($sessionId, 'stripe');
    }

    /**
     * Find pending signup by payment ID.
     */
    public function findByPaymentId(string $paymentId, string $provider): ?PendingSignup
    {
        return PendingSignup::byPaymentId($paymentId, $provider)->first();
    }
}
