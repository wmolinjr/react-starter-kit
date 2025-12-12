<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\Central\PaymentMethodResource;
use App\Models\Central\PaymentMethod;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodController extends Controller
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager
    ) {}

    /**
     * Display a list of payment methods.
     */
    public function index(Request $request): Response
    {
        $customer = $request->user('customer');

        $paymentMethods = $customer->paymentMethods()
            ->whereNull('deleted_at')
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('customer/payment-methods/index', [
            'paymentMethods' => PaymentMethodResource::collection($paymentMethods),
            'status' => session('status'),
        ]);
    }

    /**
     * Show the form for adding a new payment method.
     */
    public function create(Request $request): Response
    {
        $customer = $request->user('customer');
        $gateway = $this->gatewayManager->driver();

        // Get provider-specific setup data (e.g., Stripe SetupIntent)
        $setupData = [];
        $providerName = $this->gatewayManager->getDefaultDriver();

        if ($providerName === 'stripe') {
            try {
                $setupIntent = $gateway->createSetupIntent($customer);
                $setupData = [
                    'client_secret' => $setupIntent->providerData['client_secret'] ?? null,
                    'publishable_key' => config('services.stripe.key'),
                ];
            } catch (\Exception $e) {
                report($e);
            }
        }

        return Inertia::render('customer/payment-methods/create', [
            'provider' => $providerName,
            'setupData' => $setupData,
            'supportedTypes' => $this->getSupportedPaymentTypes($providerName),
        ]);
    }

    /**
     * Store a newly created payment method.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method_id' => ['required_without:card_token', 'string'],
            'card_token' => ['required_without:payment_method_id', 'string'],
            'type' => ['required', 'string', 'in:card,pix,boleto'],
            'provider' => ['sometimes', 'string'],
        ]);

        $customer = $request->user('customer');
        $providerName = $validated['provider'] ?? $this->gatewayManager->getDefaultDriver();
        $gateway = $this->gatewayManager->driver($providerName);

        try {
            // For Stripe, we receive a PaymentMethod ID from Stripe.js
            if ($providerName === 'stripe' && isset($validated['payment_method_id'])) {
                $result = $gateway->attachPaymentMethod(
                    $customer,
                    $validated['payment_method_id']
                );
            }
            // For Asaas, we receive a card token
            elseif ($providerName === 'asaas' && isset($validated['card_token'])) {
                // Card already tokenized, just store the reference
                $result = [
                    'success' => true,
                    'token' => $validated['card_token'],
                ];
            } else {
                return back()->withErrors([
                    'payment_method' => __('customer.invalid_payment_method_data'),
                ]);
            }

            if (! ($result['success'] ?? false) && ! isset($result['providerMethodId'])) {
                return back()->withErrors([
                    'payment_method' => $result['error'] ?? __('customer.failed_to_add_payment_method'),
                ]);
            }

            // Create local payment method record
            $paymentMethod = PaymentMethod::create([
                'customer_id' => $customer->id,
                'provider' => $providerName,
                'provider_method_id' => $result['providerMethodId'] ?? $result['token'] ?? $validated['payment_method_id'],
                'type' => $validated['type'],
                'brand' => $result['brand'] ?? $result['providerData']['brand'] ?? null,
                'last4' => $result['last_four'] ?? $result['providerData']['last4'] ?? null,
                'exp_month' => $result['exp_month'] ?? $result['providerData']['exp_month'] ?? null,
                'exp_year' => $result['exp_year'] ?? $result['providerData']['exp_year'] ?? null,
                'is_default' => ! $customer->hasPaymentMethod(),
                'is_verified' => true,
                'verified_at' => now(),
            ]);

            // If this is the first payment method, set as default
            if ($paymentMethod->is_default) {
                $customer->update(['default_payment_method_id' => $paymentMethod->id]);
            }

            return redirect()->route('central.account.payment-methods.index')
                ->with('status', 'payment-method-added');

        } catch (\Exception $e) {
            report($e);

            return back()->withErrors([
                'payment_method' => __('customer.failed_to_add_payment_method'),
            ]);
        }
    }

    /**
     * Remove the specified payment method.
     */
    public function destroy(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $customer = $request->user('customer');

        // Verify ownership
        if ($paymentMethod->customer_id !== $customer->id) {
            abort(403);
        }

        // Check if this is the default and there are active subscriptions
        if ($paymentMethod->is_default) {
            $hasActiveSubscriptions = $customer->subscriptions()
                ->where('status', 'active')
                ->exists();

            if ($hasActiveSubscriptions) {
                return back()->withErrors([
                    'payment_method' => __('customer.cannot_remove_default_with_subscriptions'),
                ]);
            }
        }

        // Try to detach from provider
        try {
            $gateway = $this->gatewayManager->driver($paymentMethod->provider);

            if ($paymentMethod->provider_method_id && method_exists($gateway, 'detachPaymentMethod')) {
                $gateway->detachPaymentMethod($customer, $paymentMethod->provider_method_id);
            }
        } catch (\Exception $e) {
            report($e);
            // Continue with local deletion even if provider fails
        }

        // Soft delete the payment method
        $customer->removePaymentMethod($paymentMethod);

        return back()->with('status', 'payment-method-removed');
    }

    /**
     * Set the specified payment method as default.
     */
    public function setDefault(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $customer = $request->user('customer');

        // Verify ownership
        if ($paymentMethod->customer_id !== $customer->id) {
            abort(403);
        }

        // Set as default locally
        $customer->setDefaultPaymentMethod($paymentMethod);

        // Try to update on provider
        try {
            $gateway = $this->gatewayManager->driver($paymentMethod->provider);

            if ($paymentMethod->provider_method_id && method_exists($gateway, 'setDefaultPaymentMethod')) {
                $gateway->setDefaultPaymentMethod($customer, $paymentMethod->provider_method_id);
            }
        } catch (\Exception $e) {
            report($e);
            // Local update succeeded, provider update failed - acceptable
        }

        return back()->with('status', 'default-payment-method-updated');
    }

    /**
     * Get supported payment types for a provider.
     */
    protected function getSupportedPaymentTypes(string $provider): array
    {
        return match ($provider) {
            'stripe' => ['card'],
            'asaas' => ['card', 'pix', 'boleto'],
            'pagseguro' => ['card', 'pix', 'boleto'],
            'mercadopago' => ['card', 'pix'],
            default => ['card'],
        };
    }
}
