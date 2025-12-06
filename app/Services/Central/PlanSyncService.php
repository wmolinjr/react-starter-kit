<?php

namespace App\Services\Central;

use App\Models\Central\Plan;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class PlanSyncService
{
    protected ?StripeClient $stripe = null;

    /**
     * Default locale for Stripe product names (universal display)
     */
    protected string $defaultLocale = 'en';

    public function __construct()
    {
        $secret = config('cashier.secret');
        if ($secret) {
            $this->stripe = new StripeClient($secret);
        }
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
     * Sync a single plan to Stripe
     */
    public function syncPlan(Plan $plan, ?string $locale = null): array
    {
        $locale = $locale ?? $this->defaultLocale;

        $result = [
            'plan_id' => $plan->id,
            'slug' => $plan->slug,
            'locale' => $locale,
            'product_synced' => false,
            'price_synced' => false,
            'errors' => [],
        ];

        try {
            // Sync Product
            $productId = $this->syncProduct($plan, $locale);
            $result['product_synced'] = true;
            $result['stripe_product_id'] = $productId;

            // Sync Price
            $priceResult = $this->syncPrice($plan, $locale);
            if ($priceResult) {
                $result['price_synced'] = true;
                $result['stripe_price_id'] = $priceResult;
            }

        } catch (ApiErrorException $e) {
            $result['errors'][] = $e->getMessage();
            Log::error('Stripe plan sync failed', [
                'plan' => $plan->slug,
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Sync all active plans to Stripe
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
     * Create or update Stripe Product for plan with i18n support
     */
    protected function syncProduct(Plan $plan, string $locale): string
    {
        // Get translated name and description
        $name = $this->getTranslatedValue($plan, 'name', $locale);
        $description = $this->getTranslatedValue($plan, 'description', $locale);

        // Collect all translations for metadata
        $translations = $this->collectTranslations($plan, ['name', 'description']);

        // Build features list for Stripe
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

        // Add marketing features if Stripe supports it
        if (! empty($featuresList)) {
            $productData['marketing_features'] = array_slice(
                array_map(fn ($f) => ['name' => $f], $featuresList),
                0,
                15 // Stripe limit
            );
        }

        // Remove null description (Stripe doesn't accept null)
        if ($productData['description'] === null) {
            unset($productData['description']);
        }

        if ($plan->stripe_product_id) {
            // Update existing product
            $this->stripe->products->update($plan->stripe_product_id, $productData);

            Log::info('Updated Stripe plan product', [
                'plan' => $plan->slug,
                'product_id' => $plan->stripe_product_id,
                'locale' => $locale,
            ]);

            return $plan->stripe_product_id;
        }

        // Create new product
        $product = $this->stripe->products->create($productData);
        $plan->update(['stripe_product_id' => $product->id]);

        Log::info('Created Stripe plan product', [
            'plan' => $plan->slug,
            'product_id' => $product->id,
            'locale' => $locale,
        ]);

        return $product->id;
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
        // Skip if no price or already has Stripe price
        if (! $plan->price || $plan->stripe_price_id) {
            return $plan->stripe_price_id;
        }

        $priceData = [
            'product' => $plan->stripe_product_id,
            'currency' => $plan->currency ?? config('cashier.currency', 'usd'),
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

        $price = $this->stripe->prices->create($priceData);
        $plan->update(['stripe_price_id' => $price->id]);

        Log::info('Created Stripe plan price', [
            'plan' => $plan->slug,
            'price_id' => $price->id,
            'locale' => $locale,
        ]);

        return $price->id;
    }

    /**
     * Convert billing period to Stripe interval
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
     * Get translated value from model using trans() method
     */
    protected function getTranslatedValue(Plan $plan, string $field, string $locale): ?string
    {
        $value = $plan->trans($field, $locale);

        return $value !== $field ? $value : null;
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
     * Archive a Stripe Price (prices are immutable, so we archive)
     */
    public function archivePrice(string $priceId): bool
    {
        try {
            $this->stripe->prices->update($priceId, ['active' => false]);

            return true;
        } catch (ApiErrorException $e) {
            Log::error('Failed to archive plan price', [
                'price_id' => $priceId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Import plan from Stripe (preserves existing translations)
     */
    public function importFromStripe(string $productId): ?Plan
    {
        try {
            $product = $this->stripe->products->retrieve($productId);
            $prices = $this->stripe->prices->all(['product' => $productId, 'active' => true]);

            // Check if plan already exists
            $existingPlan = Plan::where('stripe_product_id', $productId)->first();

            if ($existingPlan) {
                $existingPlan->update([
                    'is_active' => $product->active,
                ]);

                // Import price if not set
                if (! $existingPlan->stripe_price_id && ! empty($prices->data)) {
                    $price = $prices->data[0];
                    $existingPlan->update([
                        'stripe_price_id' => $price->id,
                        'price' => $price->unit_amount,
                    ]);
                }

                Log::info('Updated plan from Stripe (preserved translations)', [
                    'plan' => $existingPlan->slug,
                    'product_id' => $productId,
                ]);

                return $existingPlan;
            }

            // New plan - try to restore translations from metadata
            $translations = [];
            if (isset($product->metadata['translations'])) {
                $translations = json_decode($product->metadata['translations'], true) ?: [];
            }

            // Build plan data
            $planData = [
                'slug' => $product->metadata['plan_slug'] ?? \Str::slug($product->name),
                'billing_period' => $product->metadata['billing_period'] ?? 'monthly',
                'is_active' => $product->active,
                'stripe_product_id' => $productId,
            ];

            // Set translated fields
            $planData['name'] = $translations['name'] ?? ['en' => $product->name];
            $planData['description'] = $translations['description'] ?? ($product->description ? ['en' => $product->description] : null);

            // Set price from first active price
            if (! empty($prices->data)) {
                $price = $prices->data[0];
                $planData['stripe_price_id'] = $price->id;
                $planData['price'] = $price->unit_amount;
                $planData['currency'] = $price->currency;
            }

            $plan = Plan::create($planData);

            Log::info('Imported plan from Stripe', [
                'plan' => $plan->slug,
                'product_id' => $productId,
            ]);

            return $plan;

        } catch (ApiErrorException $e) {
            Log::error('Failed to import plan from Stripe', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Dry run - show what would be synced
     */
    public function dryRun(?string $planSlug = null, ?string $locale = null): array
    {
        $locale = $locale ?? $this->defaultLocale;
        $query = Plan::where('is_active', true);

        if ($planSlug) {
            $query->where('slug', $planSlug);
        }

        $plans = $query->orderBy('sort_order')->get();
        $preview = [];

        foreach ($plans as $plan) {
            $name = $this->getTranslatedValue($plan, 'name', $locale) ?? $plan->slug;

            $item = [
                'slug' => $plan->slug,
                'name' => $name,
                'locale' => $locale,
                'actions' => [],
            ];

            if (! $plan->stripe_product_id) {
                $item['actions'][] = "Create Product: \"{$name}\"";
            } else {
                $item['actions'][] = "Update Product: \"{$name}\"";
            }

            if ($plan->price && ! $plan->stripe_price_id) {
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
