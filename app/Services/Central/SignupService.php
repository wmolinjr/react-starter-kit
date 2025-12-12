<?php

namespace App\Services\Central;

use App\Enums\PaymentGateway;
use App\Exceptions\Central\AddonException;
use App\Models\Central\Customer;
use App\Models\Central\PaymentSetting;
use App\Models\Central\PendingSignup;
use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\StripeClient;

/**
 * SignupService
 *
 * Orchestrates the WIX-like signup flow:
 * 1. Create pending signup with account data
 * 2. Update with workspace data and plan selection
 * 3. Process payment (Card/PIX/Boleto)
 * 4. Complete signup after payment confirmation (via webhook)
 *
 * Key principle: Tenant is created ONLY after payment is confirmed.
 */
class SignupService
{
    protected ?StripeClient $stripe = null;

    public function __construct(
        protected CustomerService $customerService,
        protected PaymentGatewayManager $gatewayManager,
        protected PaymentSettingsService $paymentSettingsService
    ) {
        $secret = config('payment.drivers.stripe.secret');
        if ($secret) {
            $this->stripe = new StripeClient($secret);
        }
    }

    // =========================================================================
    // Step 1: Create Pending Signup with Account Data
    // =========================================================================

    /**
     * Create a new pending signup with account information.
     *
     * @param  array{name: string, email: string, password: string, locale?: string}  $data
     */
    public function createPendingSignup(array $data): PendingSignup
    {
        return PendingSignup::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'locale' => $data['locale'] ?? config('app.locale', 'pt_BR'),
            'status' => PendingSignup::STATUS_PENDING,
            'expires_at' => now()->addHours(24),
        ]);
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
        if (! $this->stripe) {
            throw new AddonException(__('billing.errors.stripe_not_configured'));
        }

        $plan = $signup->plan;
        if (! $plan) {
            throw new AddonException(__('signup.errors.plan_not_found'));
        }

        // Get Stripe price ID based on billing period
        $stripePriceId = $signup->billing_period === 'yearly'
            ? $plan->stripe_yearly_price_id
            : $plan->stripe_price_id;

        if (! $stripePriceId) {
            throw new AddonException(__('signup.errors.plan_not_synced_to_stripe'));
        }

        // Build success/cancel URLs
        $baseUrl = config('app.url');
        $successUrl = $baseUrl.'/signup/success?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = $baseUrl.'/signup?signup_id='.$signup->id;

        // Create Stripe Checkout session
        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'subscription',
            'locale' => stripe_locale(),
            'line_items' => [
                [
                    'price' => $stripePriceId,
                    'quantity' => 1,
                ],
            ],
            'customer_email' => $signup->email,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'signup_id' => $signup->id,
                'signup_type' => 'new_customer',
            ],
            'subscription_data' => [
                'metadata' => [
                    'signup_id' => $signup->id,
                ],
            ],
        ]);

        // Update signup with session info
        $signup->setPaymentSession($session->id, 'stripe');

        Log::info('Signup Stripe checkout session created', [
            'signup_id' => $signup->id,
            'session_id' => $session->id,
            'plan_id' => $plan->id,
            'billing_period' => $signup->billing_period,
        ]);

        return [
            'type' => 'redirect',
            'session_id' => $session->id,
            'url' => $session->url,
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

        $signup->markAsProcessing();

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

        // PIX only for first payment, subscription will be card-based
        $amount = $signup->billing_period === 'yearly'
            ? ($plan->price * 12 * 0.8)
            : $plan->price;

        $gatewayInstance = $this->gatewayManager->gateway($gateway->value);

        $result = $gatewayInstance->createPixCharge(
            null, // No customer yet
            (int) $amount,
            [
                'description' => __('signup.payment.description', [
                    'plan' => $plan->name,
                    'workspace' => $signup->workspace_name,
                ]),
                'customer_name' => $signup->name,
                'customer_email' => $signup->email,
                'reference' => 'signup_'.$signup->id,
                'expires_in_seconds' => 3600, // 1 hour
            ]
        );

        if (! $result->success) {
            $signup->markAsFailed($result->failureMessage ?? 'PIX creation failed');
            throw new AddonException($result->failureMessage ?? __('signup.errors.pix_creation_failed'));
        }

        $signup->setPaymentId($result->providerPaymentId, $gateway->value);

        Log::info('Signup PIX payment created', [
            'signup_id' => $signup->id,
            'payment_id' => $result->providerPaymentId,
            'amount' => $amount,
        ]);

        return [
            'type' => 'pix',
            'signup_id' => $signup->id,
            'payment_id' => $result->providerPaymentId,
            'pix' => [
                'qr_code' => $result->providerData['qr_code'] ?? null,
                'qr_code_base64' => $result->providerData['qr_code_base64'] ?? null,
                'copy_paste' => $result->providerData['copy_paste'] ?? null,
                'expires_at' => $result->providerData['expires_at'] ?? now()->addHour()->toIso8601String(),
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

        $amount = $signup->billing_period === 'yearly'
            ? ($plan->price * 12 * 0.8)
            : $plan->price;

        $gatewayInstance = $this->gatewayManager->gateway($gateway->value);

        $result = $gatewayInstance->createBoletoCharge(
            null, // No customer yet
            (int) $amount,
            [
                'description' => __('signup.payment.description', [
                    'plan' => $plan->name,
                    'workspace' => $signup->workspace_name,
                ]),
                'customer_name' => $signup->name,
                'customer_email' => $signup->email,
                'reference' => 'signup_'.$signup->id,
                'due_date' => now()->addDays(3)->format('Y-m-d'),
            ]
        );

        if (! $result->success) {
            $signup->markAsFailed($result->failureMessage ?? 'Boleto creation failed');
            throw new AddonException($result->failureMessage ?? __('signup.errors.boleto_creation_failed'));
        }

        $signup->setPaymentId($result->providerPaymentId, $gateway->value);

        Log::info('Signup Boleto payment created', [
            'signup_id' => $signup->id,
            'payment_id' => $result->providerPaymentId,
            'amount' => $amount,
        ]);

        return [
            'type' => 'boleto',
            'signup_id' => $signup->id,
            'payment_id' => $result->providerPaymentId,
            'boleto' => [
                'barcode' => $result->providerData['barcode'] ?? null,
                'barcode_image' => $result->providerData['barcode_image'] ?? null,
                'pdf_url' => $result->providerData['pdf_url'] ?? null,
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
     * Called by webhook handler after payment confirmation.
     * Creates Customer, Tenant, and Subscription.
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

        return DB::transaction(function () use ($signup) {
            // 1. Create Customer
            $customer = Customer::create([
                'global_id' => 'cust_'.Str::orderedUuid()->toString(),
                'name' => $signup->name,
                'email' => $signup->email,
                'password' => $signup->password, // Already hashed
                'locale' => $signup->locale,
                'currency' => 'brl',
            ]);

            // 2. Create Tenant
            $tenant = Tenant::create([
                'name' => $signup->workspace_name,
                'slug' => $signup->workspace_slug,
                'business_sector' => $signup->business_sector,
                'customer_id' => $customer->id,
                'plan_id' => $signup->plan_id,
            ]);

            // 3. Attach Customer to Tenant (triggers Resource Syncing)
            $customer->tenants()->attach($tenant);

            // 4. Assign owner role to the synced user
            $tenant->run(function () use ($customer) {
                $user = \App\Models\Tenant\User::where('global_id', $customer->global_id)->first();
                if ($user) {
                    $user->assignRole('owner');
                }
            });

            // 5. Create Subscription record
            $this->createSubscriptionRecord($customer, $tenant, $signup);

            // 6. Mark signup as completed
            $signup->markAsCompleted($customer->id, $tenant->id);

            Log::info('Signup completed successfully', [
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
     * Find pending signup by Stripe session ID.
     */
    public function findByStripeSession(string $sessionId): ?PendingSignup
    {
        return PendingSignup::bySession($sessionId, 'stripe')->first();
    }

    /**
     * Find pending signup by payment ID.
     */
    public function findByPaymentId(string $paymentId, string $provider): ?PendingSignup
    {
        return PendingSignup::byPaymentId($paymentId, $provider)->first();
    }
}
