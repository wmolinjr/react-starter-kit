<?php

namespace App\Services\Central;

use App\Enums\PaymentGateway;
use App\Models\Central\PaymentSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

/**
 * PaymentSettingsService
 *
 * Handles payment gateway configuration management.
 * Supports multiple gateways with separate sandbox/production credentials.
 */
class PaymentSettingsService
{
    /**
     * Get all payment settings, including defaults for unconfigured gateways.
     *
     * @return Collection<int, PaymentSetting>
     */
    public function getAllSettings(): Collection
    {
        $settings = PaymentSetting::all()->keyBy('gateway');

        // Add default settings for any unconfigured gateways
        foreach (PaymentGateway::cases() as $gateway) {
            if (! $settings->has($gateway->value)) {
                $settings[$gateway->value] = $this->createDefaultSetting($gateway);
            }
        }

        return $settings->values();
    }

    /**
     * Get setting for a specific gateway.
     */
    public function getSetting(PaymentGateway $gateway): PaymentSetting
    {
        return PaymentSetting::where('gateway', $gateway->value)->first()
            ?? $this->createDefaultSetting($gateway);
    }

    /**
     * Create a default (unsaved) setting for a gateway.
     */
    protected function createDefaultSetting(PaymentGateway $gateway): PaymentSetting
    {
        return new PaymentSetting([
            'gateway' => $gateway->value,
            'display_name' => $gateway->displayName(),
            'is_enabled' => false,
            'is_sandbox' => true,
            'is_default' => false,
            'production_credentials' => [],
            'sandbox_credentials' => [],
            'enabled_payment_types' => $gateway->supportedPaymentTypes(),
            'available_countries' => $gateway->defaultCountries(),
            'webhook_urls' => $this->generateWebhookUrls($gateway),
        ]);
    }

    /**
     * Generate webhook URLs for a gateway.
     */
    protected function generateWebhookUrls(PaymentGateway $gateway): array
    {
        $baseUrl = config('app.url');

        return [
            'production' => $baseUrl.$gateway->webhookPath(),
            'sandbox' => $baseUrl.$gateway->webhookPath(),
        ];
    }

    /**
     * Update or create a payment setting.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSetting(PaymentGateway $gateway, array $data): PaymentSetting
    {
        $setting = PaymentSetting::firstOrNew(['gateway' => $gateway->value]);

        // Set display name if not set
        if (empty($setting->display_name)) {
            $setting->display_name = $gateway->displayName();
        }

        // Update basic settings
        if (isset($data['is_enabled'])) {
            $setting->is_enabled = $data['is_enabled'];
        }

        if (isset($data['is_sandbox'])) {
            $setting->is_sandbox = $data['is_sandbox'];
        }

        if (isset($data['enabled_payment_types'])) {
            $setting->enabled_payment_types = $data['enabled_payment_types'];
        }

        if (isset($data['available_countries'])) {
            $setting->available_countries = $data['available_countries'];
        }

        // Update credentials (only provided fields, preserve others)
        if (isset($data['production_credentials'])) {
            $current = $setting->production_credentials ?? [];
            $setting->production_credentials = array_merge($current, array_filter($data['production_credentials']));
        }

        if (isset($data['sandbox_credentials'])) {
            $current = $setting->sandbox_credentials ?? [];
            $setting->sandbox_credentials = array_merge($current, array_filter($data['sandbox_credentials']));
        }

        // Generate webhook URLs if not set
        if (empty($setting->webhook_urls)) {
            $setting->webhook_urls = $this->generateWebhookUrls($gateway);
        }

        $setting->save();

        // If setting as default, unset other defaults
        if (! empty($data['is_default']) && $data['is_default']) {
            $this->setAsDefault($setting);
        }

        Log::info('Payment setting updated', [
            'gateway' => $gateway->value,
            'is_enabled' => $setting->is_enabled,
            'is_sandbox' => $setting->is_sandbox,
        ]);

        return $setting->fresh();
    }

    /**
     * Toggle sandbox mode for a gateway.
     */
    public function toggleSandbox(PaymentGateway $gateway): PaymentSetting
    {
        $setting = $this->getSetting($gateway);

        if (! $setting->exists) {
            $setting->save();
        }

        $setting->is_sandbox = ! $setting->is_sandbox;
        $setting->save();

        Log::info('Payment gateway sandbox toggled', [
            'gateway' => $gateway->value,
            'is_sandbox' => $setting->is_sandbox,
        ]);

        return $setting;
    }

    /**
     * Set a gateway as the default.
     */
    public function setAsDefault(PaymentSetting $setting): void
    {
        // Unset all other defaults
        PaymentSetting::where('id', '!=', $setting->id)
            ->update(['is_default' => false]);

        $setting->is_default = true;
        $setting->save();
    }

    /**
     * Test connection to a payment gateway.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(PaymentGateway $gateway): array
    {
        $setting = $this->getSetting($gateway);

        if (! $setting->exists || ! $setting->hasValidCredentials()) {
            return [
                'success' => false,
                'message' => __('payment_settings.errors.missing_credentials'),
            ];
        }

        try {
            $result = match ($gateway) {
                PaymentGateway::STRIPE => $this->testStripeConnection($setting),
                PaymentGateway::ASAAS => $this->testAsaasConnection($setting),
                PaymentGateway::PAGSEGURO => $this->testPagseguroConnection($setting),
                PaymentGateway::MERCADOPAGO => $this->testMercadoPagoConnection($setting),
            };

            if ($result['success']) {
                $setting->markTestSuccess();
            } else {
                $setting->markTestFailed($result['message']);
            }

            return $result;
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $setting->markTestFailed($message);

            Log::error('Payment gateway connection test failed', [
                'gateway' => $gateway->value,
                'error' => $message,
            ]);

            return [
                'success' => false,
                'message' => $message,
            ];
        }
    }

    /**
     * Test Stripe connection.
     *
     * @return array{success: bool, message: string}
     */
    protected function testStripeConnection(PaymentSetting $setting): array
    {
        $credentials = $setting->getActiveCredentials();
        $secretKey = $credentials['secret'] ?? null;

        if (empty($secretKey)) {
            return [
                'success' => false,
                'message' => __('payment_settings.errors.stripe_secret_required'),
            ];
        }

        $stripe = new StripeClient($secretKey);

        // Try to retrieve account to verify credentials
        $account = $stripe->accounts->retrieve();

        return [
            'success' => true,
            'message' => __('payment_settings.success.connection_verified', [
                'account' => $account->id,
            ]),
        ];
    }

    /**
     * Test Asaas connection.
     *
     * @return array{success: bool, message: string}
     */
    protected function testAsaasConnection(PaymentSetting $setting): array
    {
        $credentials = $setting->getActiveCredentials();
        $apiKey = $credentials['api_key'] ?? null;

        if (empty($apiKey)) {
            return [
                'success' => false,
                'message' => __('payment_settings.errors.api_key_required'),
            ];
        }

        $baseUrl = $setting->is_sandbox
            ? 'https://sandbox.asaas.com/api/v3'
            : 'https://www.asaas.com/api/v3';

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'access_token' => $apiKey,
        ])->get($baseUrl.'/myAccount');

        if ($response->successful()) {
            return [
                'success' => true,
                'message' => __('payment_settings.success.connection_verified', [
                    'account' => $response->json('name', 'Asaas'),
                ]),
            ];
        }

        return [
            'success' => false,
            'message' => $response->json('errors.0.description', __('payment_settings.errors.connection_failed')),
        ];
    }

    /**
     * Test PagSeguro connection.
     *
     * @return array{success: bool, message: string}
     */
    protected function testPagseguroConnection(PaymentSetting $setting): array
    {
        $credentials = $setting->getActiveCredentials();
        $apiKey = $credentials['api_key'] ?? null;

        if (empty($apiKey)) {
            return [
                'success' => false,
                'message' => __('payment_settings.errors.api_key_required'),
            ];
        }

        $baseUrl = $setting->is_sandbox
            ? 'https://sandbox.api.pagseguro.com'
            : 'https://api.pagseguro.com';

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
        ])->get($baseUrl.'/orders');

        if ($response->successful() || $response->status() === 401) {
            // 401 means credentials are formatted correctly but might be expired
            return [
                'success' => $response->successful(),
                'message' => $response->successful()
                    ? __('payment_settings.success.connection_verified', ['account' => 'PagSeguro'])
                    : __('payment_settings.errors.invalid_credentials'),
            ];
        }

        return [
            'success' => false,
            'message' => __('payment_settings.errors.connection_failed'),
        ];
    }

    /**
     * Test MercadoPago connection.
     *
     * @return array{success: bool, message: string}
     */
    protected function testMercadoPagoConnection(PaymentSetting $setting): array
    {
        $credentials = $setting->getActiveCredentials();
        $accessToken = $credentials['access_token'] ?? null;

        if (empty($accessToken)) {
            return [
                'success' => false,
                'message' => __('payment_settings.errors.access_token_required'),
            ];
        }

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Bearer '.$accessToken,
        ])->get('https://api.mercadopago.com/users/me');

        if ($response->successful()) {
            return [
                'success' => true,
                'message' => __('payment_settings.success.connection_verified', [
                    'account' => $response->json('email', 'MercadoPago'),
                ]),
            ];
        }

        return [
            'success' => false,
            'message' => $response->json('message', __('payment_settings.errors.connection_failed')),
        ];
    }

    /**
     * Get merged config for a gateway (DB settings + ENV fallback).
     *
     * This is used by PaymentGatewayManager to get the active configuration.
     *
     * @return array<string, mixed>
     */
    public function getMergedConfig(PaymentGateway $gateway): array
    {
        $setting = PaymentSetting::where('gateway', $gateway->value)->first();
        $envConfig = config("payment.gateways.{$gateway->value}", []);

        // If no DB setting, return ENV config
        if (! $setting) {
            return $envConfig;
        }

        // DB settings take precedence
        $credentials = $setting->getActiveCredentials();

        return array_merge($envConfig, [
            'enabled' => $setting->is_enabled,
            'sandbox' => $setting->is_sandbox,
            'payment_types' => $setting->enabled_payment_types,
            // Merge credentials, DB takes precedence if not empty
            ...$this->mergeCredentials($gateway, $credentials, $envConfig),
        ]);
    }

    /**
     * Merge credentials from DB and ENV, DB takes precedence.
     *
     * @param  array<string, mixed>  $dbCredentials
     * @param  array<string, mixed>  $envConfig
     * @return array<string, mixed>
     */
    protected function mergeCredentials(PaymentGateway $gateway, array $dbCredentials, array $envConfig): array
    {
        $merged = [];

        foreach ($gateway->credentialFields() as $field) {
            $key = $field['key'];
            $dbValue = $dbCredentials[$key] ?? null;
            $envValue = $envConfig[$key] ?? null;

            // DB value takes precedence if not empty
            $merged[$key] = ! empty($dbValue) ? $dbValue : $envValue;
        }

        return $merged;
    }

    /**
     * Get the default enabled gateway for a country.
     */
    public function getDefaultGatewayForCountry(string $countryCode): ?PaymentSetting
    {
        // First try to find an explicit default
        $default = PaymentSetting::enabled()
            ->default()
            ->availableIn($countryCode)
            ->first();

        if ($default) {
            return $default;
        }

        // Fall back to first enabled gateway for the country
        return PaymentSetting::enabled()
            ->availableIn($countryCode)
            ->first();
    }

    /**
     * Get available gateways for checkout.
     *
     * @return Collection<int, PaymentSetting>
     */
    public function getAvailableGatewaysForCheckout(string $countryCode, ?string $paymentType = null): Collection
    {
        $query = PaymentSetting::enabled()
            ->availableIn($countryCode);

        if ($paymentType) {
            $query->supporting($paymentType);
        }

        return $query->get();
    }

    /**
     * Get available payment methods configuration for checkout UI.
     *
     * Returns a configuration array with available payment methods,
     * their corresponding gateways, and the default method.
     *
     * @param  string  $countryCode  ISO country code (e.g., 'BR', 'US')
     * @return array{
     *     available_methods: array<string>,
     *     default_method: string,
     *     gateways: array<string, string>,
     *     has_recurring_support: bool
     * }
     */
    public function getAvailablePaymentMethods(string $countryCode = 'BR'): array
    {
        $enabledSettings = PaymentSetting::enabled()
            ->availableIn($countryCode)
            ->orderByDesc('is_default')
            ->get();

        $availableMethods = [];
        $gateways = [];
        $hasRecurringSupport = false;

        foreach ($enabledSettings as $setting) {
            foreach ($setting->enabled_payment_types as $paymentType) {
                // Only add if not already added (first gateway wins - by is_default priority)
                if (! isset($gateways[$paymentType])) {
                    $availableMethods[] = $paymentType;
                    $gateways[$paymentType] = $setting->gateway;

                    // Card payments support recurring
                    if ($paymentType === 'card') {
                        $hasRecurringSupport = true;
                    }
                }
            }
        }

        // Determine default method (card if available, otherwise first)
        $defaultMethod = in_array('card', $availableMethods) ? 'card' : ($availableMethods[0] ?? 'card');

        return [
            'available_methods' => $availableMethods,
            'default_method' => $defaultMethod,
            'gateways' => $gateways,
            'has_recurring_support' => $hasRecurringSupport,
        ];
    }

    /**
     * Get the enabled gateway for a specific payment method.
     *
     * Used by CartCheckoutService to route payments to the correct gateway.
     *
     * @param  string  $paymentMethod  The payment method (card, pix, boleto)
     * @param  string  $countryCode  ISO country code
     */
    public function getEnabledGatewayForMethod(string $paymentMethod, string $countryCode = 'BR'): ?PaymentSetting
    {
        return PaymentSetting::enabled()
            ->availableIn($countryCode)
            ->supporting($paymentMethod)
            ->orderByDesc('is_default')
            ->first();
    }

    /**
     * Check if a payment method is available for checkout.
     *
     * @param  string  $paymentMethod  The payment method (card, pix, boleto)
     * @param  string  $countryCode  ISO country code
     */
    public function isPaymentMethodAvailable(string $paymentMethod, string $countryCode = 'BR'): bool
    {
        return $this->getEnabledGatewayForMethod($paymentMethod, $countryCode) !== null;
    }

    /**
     * Get payment configuration for checkout UI.
     *
     * Returns a structured array suitable for frontend consumption.
     * Alias for getAvailablePaymentMethods with PaymentConfigResource compatibility.
     *
     * @param  string  $countryCode  ISO country code (e.g., 'BR', 'US')
     * @return \App\Http\Resources\Central\PaymentConfigResource
     */
    public function getPaymentConfig(string $countryCode = 'BR'): \App\Http\Resources\Central\PaymentConfigResource
    {
        $methods = $this->getAvailablePaymentMethods($countryCode);

        return new \App\Http\Resources\Central\PaymentConfigResource($methods);
    }
}
