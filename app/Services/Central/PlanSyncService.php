<?php

namespace App\Services\Central;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Contracts\Payment\ProductPriceGatewayInterface;
use App\Models\Central\Plan;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Support\Facades\Log;

class PlanSyncService
{
    protected ?PaymentGatewayInterface $gateway = null;

    /**
     * Default locale for product names (universal display)
     */
    protected string $defaultLocale = 'en';

    public function __construct(
        protected PaymentGatewayManager $gatewayManager
    ) {
        $this->gateway = $this->gatewayManager->driver();
    }

    /**
     * Check if payment gateway is configured.
     */
    public function isConfigured(): bool
    {
        return $this->gateway !== null && $this->gateway->isAvailable();
    }

    /**
     * Check if gateway supports product/price operations.
     */
    protected function supportsProducts(): bool
    {
        return $this->gateway instanceof ProductPriceGatewayInterface;
    }

    /**
     * Get the current provider identifier.
     */
    public function getProvider(): string
    {
        return $this->gateway?->getIdentifier() ?? 'unknown';
    }

    /**
     * Set the default locale for syncing
     */
    public function setLocale(string $locale): self
    {
        $this->defaultLocale = $locale;

        return $this;
    }

    /**
     * Get the current locale
     */
    public function getLocale(): string
    {
        return $this->defaultLocale;
    }

    /**
     * Sync a single plan to the payment provider
     */
    public function syncPlan(Plan $plan, ?string $locale = null): array
    {
        $locale = $locale ?? $this->defaultLocale;
        $provider = $this->getProvider();

        $result = [
            'plan_id' => $plan->id,
            'slug' => $plan->slug,
            'provider' => $provider,
            'locale' => $locale,
            'product_synced' => false,
            'price_synced' => false,
            'errors' => [],
        ];

        if (! $this->isConfigured()) {
            $result['errors'][] = 'Payment gateway not configured';

            return $result;
        }

        if (! $this->supportsProducts()) {
            $result['errors'][] = "Payment gateway '{$provider}' does not support product/price management";

            return $result;
        }

        try {
            // Sync Product
            $productId = $this->syncProduct($plan, $locale);
            $result['product_synced'] = true;
            $result['provider_product_id'] = $productId;

            // Sync Price
            $priceResult = $this->syncPrice($plan, $locale);
            if ($priceResult) {
                $result['price_synced'] = true;
                $result['provider_price_id'] = $priceResult;
            }

        } catch (\Exception $e) {
            $result['errors'][] = $e->getMessage();
            Log::error('Plan sync to payment provider failed', [
                'plan' => $plan->slug,
                'provider' => $provider,
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Sync all active plans to the payment provider
     */
    public function syncAll(?string $locale = null): array
    {
        $locale = $locale ?? $this->defaultLocale;
        $results = [];
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();

        foreach ($plans as $plan) {
            $results[] = $this->syncPlan($plan, $locale);
        }

        return $results;
    }

    /**
     * Create or update Product for plan with i18n support
     */
    protected function syncProduct(Plan $plan, string $locale): string
    {
        /** @var ProductPriceGatewayInterface $gateway */
        $gateway = $this->gateway;
        $provider = $this->getProvider();

        // Get translated name and description
        $name = $this->getTranslatedValue($plan, 'name', $locale);
        $description = $this->getTranslatedValue($plan, 'description', $locale);

        // Collect all translations for metadata
        $translations = $this->collectTranslations($plan, ['name', 'description']);

        // Build features list
        $featuresList = $this->buildFeaturesList($plan);

        $productData = [
            'name' => $name,
            'description' => $description ?: null,
            'metadata' => [
                'plan_slug' => $plan->slug,
                'billing_period' => $plan->billing_period,
                'source' => 'plan_catalog',
                'locale' => $locale,
                'translations' => json_encode($translations, JSON_UNESCAPED_UNICODE),
            ],
        ];

        // Add marketing features if provider supports it (Stripe-specific, may be ignored by other providers)
        if (! empty($featuresList)) {
            $productData['marketing_features'] = array_slice(
                array_map(fn ($f) => ['name' => $f], $featuresList),
                0,
                15
            );
        }

        // Remove null description (some providers don't accept null)
        if ($productData['description'] === null) {
            unset($productData['description']);
        }

        $existingProductId = $plan->getProviderProductId($provider);

        if ($existingProductId) {
            // Update existing product
            $gateway->updateProduct($existingProductId, $productData);

            Log::info('Updated plan product on payment provider', [
                'plan' => $plan->slug,
                'provider' => $provider,
                'product_id' => $existingProductId,
                'locale' => $locale,
            ]);

            return $existingProductId;
        }

        // Create new product
        $product = $gateway->createProduct($productData);
        $plan->setProviderProductId($provider, $product['id']);
        $plan->save();

        Log::info('Created plan product on payment provider', [
            'plan' => $plan->slug,
            'provider' => $provider,
            'product_id' => $product['id'],
            'locale' => $locale,
        ]);

        return $product['id'];
    }

    /**
     * Build features list from plan features array
     */
    protected function buildFeaturesList(Plan $plan): array
    {
        $features = [];

        if (is_array($plan->features)) {
            foreach ($plan->features as $feature => $enabled) {
                if ($enabled) {
                    // Convert feature key to human readable
                    $features[] = ucwords(str_replace(['_', '-'], ' ', $feature));
                }
            }
        }

        return $features;
    }

    /**
     * Sync price for a plan
     */
    protected function syncPrice(Plan $plan, string $locale): ?string
    {
        /** @var ProductPriceGatewayInterface $gateway */
        $gateway = $this->gateway;
        $provider = $this->getProvider();

        // Skip if no price or already has provider price
        $existingPriceId = $plan->getProviderPriceId($provider);
        if (! $plan->price || $existingPriceId) {
            return $existingPriceId;
        }

        $productId = $plan->getProviderProductId($provider);

        $priceData = [
            'product' => $productId,
            'currency' => $plan->currency ?? config('payment.currency', 'BRL'),
            'unit_amount' => $plan->price,
            'metadata' => [
                'plan_slug' => $plan->slug,
                'billing_period' => $plan->billing_period,
                'locale' => $locale,
            ],
        ];

        // Add recurring based on billing period
        $interval = $this->getBillingInterval($plan->billing_period);
        if ($interval) {
            $priceData['recurring'] = ['interval' => $interval];
        }

        $price = $gateway->createPrice($priceData);
        $plan->setProviderPriceId($provider, $price['id']);
        $plan->save();

        Log::info('Created plan price on payment provider', [
            'plan' => $plan->slug,
            'provider' => $provider,
            'price_id' => $price['id'],
            'locale' => $locale,
        ]);

        return $price['id'];
    }

    /**
     * Convert billing period to provider interval format
     */
    protected function getBillingInterval(string $billingPeriod): ?string
    {
        return match ($billingPeriod) {
            'monthly' => 'month',
            'yearly' => 'year',
            'weekly' => 'week',
            'daily' => 'day',
            default => null,
        };
    }

    /**
     * Get translated value from model using getTranslation() method
     */
    protected function getTranslatedValue(Plan $plan, string $field, string $locale): ?string
    {
        $value = $plan->getTranslation($field, $locale, false);

        return $value ?: null;
    }

    /**
     * Collect all translations for specified fields
     */
    protected function collectTranslations(Plan $plan, array $fields): array
    {
        $translations = [];

        foreach ($fields as $field) {
            $fieldTranslations = $plan->getTranslations($field);

            if (! empty($fieldTranslations)) {
                $translations[$field] = $fieldTranslations;
            }
        }

        return $translations;
    }

    /**
     * Archive a Price (prices are immutable, so we archive)
     */
    public function archivePrice(string $priceId): bool
    {
        if (! $this->isConfigured() || ! $this->supportsProducts()) {
            return false;
        }

        /** @var ProductPriceGatewayInterface $gateway */
        $gateway = $this->gateway;

        try {
            return $gateway->archivePrice($priceId);
        } catch (\Exception $e) {
            Log::error('Failed to archive plan price', [
                'price_id' => $priceId,
                'provider' => $this->getProvider(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Import plan from payment provider (preserves existing translations)
     */
    public function importFromProvider(string $productId): ?Plan
    {
        if (! $this->isConfigured() || ! $this->supportsProducts()) {
            return null;
        }

        /** @var ProductPriceGatewayInterface $gateway */
        $gateway = $this->gateway;
        $provider = $this->getProvider();

        try {
            $product = $gateway->retrieveProduct($productId);
            $prices = $gateway->listPrices($productId);

            // Check if plan already exists with this provider product ID
            $existingPlan = $this->findPlanByProviderProductId($provider, $productId);

            if ($existingPlan) {
                $existingPlan->update([
                    'is_active' => $product['active'] ?? true,
                ]);

                // Import price if not set
                $existingPriceId = $existingPlan->getProviderPriceId($provider);
                if (! $existingPriceId && ! empty($prices)) {
                    $price = $prices[0];
                    $existingPlan->setProviderPriceId($provider, $price['id']);
                    $existingPlan->price = $price['unit_amount'];
                    $existingPlan->save();
                }

                Log::info('Updated plan from payment provider (preserved translations)', [
                    'plan' => $existingPlan->slug,
                    'provider' => $provider,
                    'product_id' => $productId,
                ]);

                return $existingPlan;
            }

            // New plan - try to restore translations from metadata
            $translations = [];
            if (isset($product['metadata']['translations'])) {
                $translations = json_decode($product['metadata']['translations'], true) ?: [];
            }

            // Build plan data
            $planData = [
                'slug' => $product['metadata']['plan_slug'] ?? \Str::slug($product['name']),
                'billing_period' => $product['metadata']['billing_period'] ?? 'monthly',
                'is_active' => $product['active'] ?? true,
            ];

            // Set translated fields
            $planData['name'] = $translations['name'] ?? ['en' => $product['name']];
            $planData['description'] = $translations['description'] ?? (($product['description'] ?? null) ? ['en' => $product['description']] : null);

            // Set price from first active price
            if (! empty($prices)) {
                $price = $prices[0];
                $planData['price'] = $price['unit_amount'];
                $planData['currency'] = $price['currency'];
            }

            $plan = Plan::create($planData);

            // Set provider-specific IDs
            $plan->setProviderProductId($provider, $productId);
            if (! empty($prices)) {
                $plan->setProviderPriceId($provider, $prices[0]['id']);
            }
            $plan->save();

            Log::info('Imported plan from payment provider', [
                'plan' => $plan->slug,
                'provider' => $provider,
                'product_id' => $productId,
            ]);

            return $plan;

        } catch (\Exception $e) {
            Log::error('Failed to import plan from payment provider', [
                'product_id' => $productId,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Find a plan by provider product ID
     */
    protected function findPlanByProviderProductId(string $provider, string $productId): ?Plan
    {
        $field = match ($provider) {
            'stripe' => 'stripe_product_id',
            'paddle' => 'paddle_product_id',
            'asaas' => 'asaas_product_id',
            'pagseguro' => 'pagseguro_product_id',
            'mercadopago' => 'mercadopago_product_id',
            default => null,
        };

        if (! $field) {
            return null;
        }

        return Plan::where($field, $productId)->first();
    }

    /**
     * Dry run - show what would be synced
     */
    public function dryRun(?string $planSlug = null, ?string $locale = null): array
    {
        $locale = $locale ?? $this->defaultLocale;
        $provider = $this->getProvider();
        $query = Plan::where('is_active', true);

        if ($planSlug) {
            $query->where('slug', $planSlug);
        }

        $plans = $query->orderBy('sort_order')->get();
        $preview = [];

        foreach ($plans as $plan) {
            $name = $this->getTranslatedValue($plan, 'name', $locale) ?? $plan->slug;
            $productId = $plan->getProviderProductId($provider);
            $priceId = $plan->getProviderPriceId($provider);

            $item = [
                'slug' => $plan->slug,
                'name' => $name,
                'provider' => $provider,
                'locale' => $locale,
                'actions' => [],
            ];

            if (! $productId) {
                $item['actions'][] = "Create Product: \"{$name}\"";
            } else {
                $item['actions'][] = "Update Product: \"{$name}\"";
            }

            if ($plan->price && ! $priceId) {
                $item['actions'][] = 'Create Price ($'.number_format($plan->price / 100, 2).'/'.$plan->billing_period.')';
            }

            $preview[] = $item;
        }

        return $preview;
    }

    /**
     * Get list of supported locales from config
     */
    public function getSupportedLocales(): array
    {
        return config('app.locales', ['en', 'pt_BR']);
    }
}
