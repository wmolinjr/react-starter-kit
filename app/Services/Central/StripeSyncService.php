<?php

namespace App\Services\Central;

use App\Models\Central\Addon;
use App\Models\Central\AddonBundle;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeSyncService
{
    protected ?StripeClient $stripe = null;

    /**
     * Default locale for Stripe product names (universal display)
     */
    protected string $defaultLocale = 'en';

    public function __construct()
    {
        $secret = config('payment.drivers.stripe.secret');
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
     * Sync a single addon to Stripe
     */
    public function syncAddon(Addon $addon, ?string $locale = null): array
    {
        $locale = $locale ?? $this->defaultLocale;

        $result = [
            'addon_id' => $addon->id,
            'slug' => $addon->slug,
            'locale' => $locale,
            'product_synced' => false,
            'prices_synced' => [],
            'errors' => [],
        ];

        try {
            // Sync Product
            $productId = $this->syncProduct($addon, $locale);
            $result['product_synced'] = true;
            $result['stripe_product_id'] = $productId;

            // Sync Prices
            $result['prices_synced'] = $this->syncPrices($addon, $locale);

        } catch (ApiErrorException $e) {
            $result['errors'][] = $e->getMessage();
            Log::error('Stripe sync failed', [
                'addon' => $addon->slug,
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Sync all active addons to Stripe
     */
    public function syncAll(?string $locale = null): array
    {
        $locale = $locale ?? $this->defaultLocale;
        $results = [];
        $addons = Addon::where('active', true)->get();

        foreach ($addons as $addon) {
            $results[] = $this->syncAddon($addon, $locale);
        }

        return $results;
    }

    /**
     * Create or update Stripe Product with i18n support
     */
    protected function syncProduct(Addon $addon, string $locale): string
    {
        // Get translated name and description
        $name = $this->getTranslatedValue($addon, 'name', $locale);
        $description = $this->getTranslatedValue($addon, 'description', $locale);

        // Collect all translations for metadata
        $translations = $this->collectTranslations($addon, ['name', 'description', 'unit_label']);

        $productData = [
            'name' => $name,
            'description' => $description ?: null,
            'metadata' => [
                'addon_slug' => $addon->slug,
                'addon_type' => $addon->type->value ?? $addon->type,
                'source' => 'addon_catalog',
                'locale' => $locale,
                'translations' => json_encode($translations, JSON_UNESCAPED_UNICODE),
            ],
        ];

        // Remove null description (Stripe doesn't accept null)
        if ($productData['description'] === null) {
            unset($productData['description']);
        }

        if ($addon->stripe_product_id) {
            // Update existing product
            $this->stripe->products->update($addon->stripe_product_id, $productData);

            Log::info('Updated Stripe product', [
                'addon' => $addon->slug,
                'product_id' => $addon->stripe_product_id,
                'locale' => $locale,
            ]);

            return $addon->stripe_product_id;
        }

        // Create new product
        $product = $this->stripe->products->create($productData);
        $addon->update(['stripe_product_id' => $product->id]);

        Log::info('Created Stripe product', [
            'addon' => $addon->slug,
            'product_id' => $product->id,
            'locale' => $locale,
        ]);

        return $product->id;
    }

    /**
     * Get translated value from model using getTranslation() method
     */
    protected function getTranslatedValue(Addon $addon, string $field, string $locale): ?string
    {
        // Use the model's getTranslation() method
        $value = $addon->getTranslation($field, $locale, false);

        return $value ?: null;
    }

    /**
     * Collect all translations for specified fields
     */
    protected function collectTranslations(Addon $addon, array $fields): array
    {
        $translations = [];

        foreach ($fields as $field) {
            // Get the raw translations array from the model
            $fieldTranslations = $addon->getTranslations($field);

            if (! empty($fieldTranslations)) {
                $translations[$field] = $fieldTranslations;
            }
        }

        return $translations;
    }

    /**
     * Sync prices for an addon
     */
    protected function syncPrices(Addon $addon, string $locale): array
    {
        $synced = [];

        // Monthly price
        if ($addon->price_monthly && ! $addon->stripe_price_monthly_id) {
            $priceId = $this->createPrice($addon, 'monthly', $addon->price_monthly, $locale);
            $addon->update(['stripe_price_monthly_id' => $priceId]);
            $synced['monthly'] = $priceId;
        }

        // Yearly price
        if ($addon->price_yearly && ! $addon->stripe_price_yearly_id) {
            $priceId = $this->createPrice($addon, 'yearly', $addon->price_yearly, $locale);
            $addon->update(['stripe_price_yearly_id' => $priceId]);
            $synced['yearly'] = $priceId;
        }

        // One-time price
        if ($addon->price_one_time && ! $addon->stripe_price_one_time_id) {
            $priceId = $this->createPrice($addon, 'one_time', $addon->price_one_time, $locale);
            $addon->update(['stripe_price_one_time_id' => $priceId]);
            $synced['one_time'] = $priceId;
        }

        return $synced;
    }

    /**
     * Create a Stripe Price
     */
    protected function createPrice(Addon $addon, string $billingPeriod, int $amount, string $locale): string
    {
        $priceData = [
            'product' => $addon->stripe_product_id,
            'currency' => config('payment.currency', 'BRL'),
            'unit_amount' => $amount,
            'metadata' => [
                'addon_slug' => $addon->slug,
                'billing_period' => $billingPeriod,
                'locale' => $locale,
            ],
        ];

        // Add recurring for subscription prices
        if ($billingPeriod === 'monthly') {
            $priceData['recurring'] = ['interval' => 'month'];
        } elseif ($billingPeriod === 'yearly') {
            $priceData['recurring'] = ['interval' => 'year'];
        }

        $price = $this->stripe->prices->create($priceData);

        Log::info('Created Stripe price', [
            'addon' => $addon->slug,
            'billing_period' => $billingPeriod,
            'price_id' => $price->id,
            'locale' => $locale,
        ]);

        return $price->id;
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
            Log::error('Failed to archive price', [
                'price_id' => $priceId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Import product from Stripe (preserves existing translations)
     */
    public function importFromStripe(string $productId): ?Addon
    {
        try {
            $product = $this->stripe->products->retrieve($productId);
            $prices = $this->stripe->prices->all(['product' => $productId, 'active' => true]);

            // Check if addon already exists
            $existingAddon = Addon::where('stripe_product_id', $productId)->first();

            // If addon exists, only update non-translatable fields
            if ($existingAddon) {
                $existingAddon->update([
                    'active' => $product->active,
                ]);

                // Import prices without overwriting translations
                $this->importPrices($existingAddon, $prices->data);

                Log::info('Updated addon from Stripe (preserved translations)', [
                    'addon' => $existingAddon->slug,
                    'product_id' => $productId,
                ]);

                return $existingAddon;
            }

            // New addon - try to restore translations from metadata
            $translations = [];
            if (isset($product->metadata['translations'])) {
                $translations = json_decode($product->metadata['translations'], true) ?: [];
            }

            // Build addon data
            $addonData = [
                'slug' => $product->metadata['addon_slug'] ?? \Str::slug($product->name),
                'type' => $product->metadata['addon_type'] ?? 'feature',
                'active' => $product->active,
            ];

            // Set translated fields - use stored translations or fallback to Stripe name
            $addonData['name'] = $translations['name'] ?? ['en' => $product->name];
            $addonData['description'] = $translations['description'] ?? ($product->description ? ['en' => $product->description] : null);
            if (isset($translations['unit_label'])) {
                $addonData['unit_label'] = $translations['unit_label'];
            }

            $addon = Addon::create(array_merge($addonData, [
                'stripe_product_id' => $productId,
            ]));

            // Import prices
            $this->importPrices($addon, $prices->data);

            Log::info('Imported addon from Stripe', [
                'addon' => $addon->slug,
                'product_id' => $productId,
            ]);

            return $addon;

        } catch (ApiErrorException $e) {
            Log::error('Failed to import from Stripe', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Import prices for an addon
     */
    protected function importPrices(Addon $addon, array $prices): void
    {
        foreach ($prices as $price) {
            $period = $price->metadata['billing_period'] ?? $this->detectBillingPeriod($price);
            $priceIdField = "stripe_price_{$period}_id";
            $priceAmountField = "price_{$period}";

            // Only update if not already set
            if (! $addon->{$priceIdField}) {
                $addon->update([
                    $priceIdField => $price->id,
                    $priceAmountField => $price->unit_amount,
                ]);
            }
        }
    }

    /**
     * Detect billing period from Stripe price
     */
    protected function detectBillingPeriod($price): string
    {
        if (! $price->recurring) {
            return 'one_time';
        }

        return match ($price->recurring->interval) {
            'month' => 'monthly',
            'year' => 'yearly',
            default => 'monthly',
        };
    }

    /**
     * Dry run - show what would be synced
     */
    public function dryRun(?string $addonSlug = null, ?string $locale = null): array
    {
        $locale = $locale ?? $this->defaultLocale;
        $query = Addon::where('active', true);

        if ($addonSlug) {
            $query->where('slug', $addonSlug);
        }

        $addons = $query->get();
        $preview = [];

        foreach ($addons as $addon) {
            $name = $this->getTranslatedValue($addon, 'name', $locale) ?? $addon->slug;

            $item = [
                'slug' => $addon->slug,
                'name' => $name,
                'locale' => $locale,
                'actions' => [],
            ];

            if (! $addon->stripe_product_id) {
                $item['actions'][] = "Create Product: \"{$name}\"";
            } else {
                $item['actions'][] = "Update Product: \"{$name}\"";
            }

            if ($addon->price_monthly && ! $addon->stripe_price_monthly_id) {
                $item['actions'][] = 'Create Monthly Price ($'.number_format($addon->price_monthly / 100, 2).')';
            }

            if ($addon->price_yearly && ! $addon->stripe_price_yearly_id) {
                $item['actions'][] = 'Create Yearly Price ($'.number_format($addon->price_yearly / 100, 2).')';
            }

            if ($addon->price_one_time && ! $addon->stripe_price_one_time_id) {
                $item['actions'][] = 'Create One-Time Price ($'.number_format($addon->price_one_time / 100, 2).')';
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

    /*
    |--------------------------------------------------------------------------
    | Bundle Sync Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Sync a single bundle to Stripe
     */
    public function syncBundle(AddonBundle $bundle, ?string $locale = null): array
    {
        $locale = $locale ?? $this->defaultLocale;

        $result = [
            'bundle_id' => $bundle->id,
            'slug' => $bundle->slug,
            'locale' => $locale,
            'product_synced' => false,
            'prices_synced' => [],
            'errors' => [],
        ];

        try {
            // Sync Product
            $productId = $this->syncBundleProduct($bundle, $locale);
            $result['product_synced'] = true;
            $result['stripe_product_id'] = $productId;

            // Sync Prices
            $result['prices_synced'] = $this->syncBundlePrices($bundle, $locale);

        } catch (ApiErrorException $e) {
            $result['errors'][] = $e->getMessage();
            Log::error('Stripe bundle sync failed', [
                'bundle' => $bundle->slug,
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Sync all active bundles to Stripe
     */
    public function syncAllBundles(?string $locale = null): array
    {
        $locale = $locale ?? $this->defaultLocale;
        $results = [];
        $bundles = AddonBundle::where('active', true)->with('addons')->get();

        foreach ($bundles as $bundle) {
            $results[] = $this->syncBundle($bundle, $locale);
        }

        return $results;
    }

    /**
     * Create or update Stripe Product for Bundle
     */
    protected function syncBundleProduct(AddonBundle $bundle, string $locale): string
    {
        // Get translated name and description
        $name = $bundle->getTranslation('name', $locale) ?? $bundle->getTranslation('name', 'en') ?? $bundle->slug;
        $description = $bundle->getTranslation('description', $locale) ?? $bundle->getTranslation('description', 'en');

        // Collect addon slugs for metadata
        $addonSlugs = $bundle->addons->pluck('slug')->toArray();

        // Collect translations for metadata
        $translations = [
            'name' => $bundle->getTranslations('name'),
            'description' => $bundle->getTranslations('description'),
        ];

        $productData = [
            'name' => $name,
            'description' => $description ?: null,
            'metadata' => [
                'bundle_slug' => $bundle->slug,
                'discount_percent' => $bundle->discount_percent,
                'addon_count' => count($addonSlugs),
                'addon_slugs' => implode(',', $addonSlugs),
                'source' => 'bundle_catalog',
                'locale' => $locale,
                'translations' => json_encode($translations, JSON_UNESCAPED_UNICODE),
            ],
        ];

        // Remove null description
        if ($productData['description'] === null) {
            unset($productData['description']);
        }

        if ($bundle->stripe_product_id) {
            // Update existing product
            $this->stripe->products->update($bundle->stripe_product_id, $productData);

            Log::info('Updated Stripe bundle product', [
                'bundle' => $bundle->slug,
                'product_id' => $bundle->stripe_product_id,
                'locale' => $locale,
            ]);

            return $bundle->stripe_product_id;
        }

        // Create new product
        $product = $this->stripe->products->create($productData);
        $bundle->update(['stripe_product_id' => $product->id]);

        Log::info('Created Stripe bundle product', [
            'bundle' => $bundle->slug,
            'product_id' => $product->id,
            'locale' => $locale,
        ]);

        return $product->id;
    }

    /**
     * Sync prices for a bundle (uses effective price with discount)
     */
    protected function syncBundlePrices(AddonBundle $bundle, string $locale): array
    {
        $synced = [];

        // Monthly price (effective = with discount applied)
        $monthlyPrice = $bundle->getEffectivePriceMonthly();
        if ($monthlyPrice > 0 && ! $bundle->stripe_price_monthly_id) {
            $priceId = $this->createBundlePrice($bundle, 'monthly', $monthlyPrice, $locale);
            $bundle->update(['stripe_price_monthly_id' => $priceId]);
            $synced['monthly'] = $priceId;
        }

        // Yearly price (effective = with discount applied)
        $yearlyPrice = $bundle->getEffectivePriceYearly();
        if ($yearlyPrice > 0 && ! $bundle->stripe_price_yearly_id) {
            $priceId = $this->createBundlePrice($bundle, 'yearly', $yearlyPrice, $locale);
            $bundle->update(['stripe_price_yearly_id' => $priceId]);
            $synced['yearly'] = $priceId;
        }

        return $synced;
    }

    /**
     * Create a Stripe Price for Bundle
     */
    protected function createBundlePrice(AddonBundle $bundle, string $billingPeriod, int $amount, string $locale): string
    {
        $priceData = [
            'product' => $bundle->stripe_product_id,
            'currency' => $bundle->currency ?? stripe_currency(),
            'unit_amount' => $amount,
            'metadata' => [
                'bundle_slug' => $bundle->slug,
                'billing_period' => $billingPeriod,
                'discount_percent' => $bundle->discount_percent,
                'locale' => $locale,
            ],
        ];

        // Add recurring
        if ($billingPeriod === 'monthly') {
            $priceData['recurring'] = ['interval' => 'month'];
        } elseif ($billingPeriod === 'yearly') {
            $priceData['recurring'] = ['interval' => 'year'];
        }

        $price = $this->stripe->prices->create($priceData);

        Log::info('Created Stripe bundle price', [
            'bundle' => $bundle->slug,
            'billing_period' => $billingPeriod,
            'price_id' => $price->id,
            'amount' => $amount,
            'locale' => $locale,
        ]);

        return $price->id;
    }

    /**
     * Dry run for bundles - show what would be synced
     */
    public function dryRunBundles(?string $bundleSlug = null, ?string $locale = null): array
    {
        $locale = $locale ?? $this->defaultLocale;
        $query = AddonBundle::where('active', true)->with('addons');

        if ($bundleSlug) {
            $query->where('slug', $bundleSlug);
        }

        $bundles = $query->get();
        $preview = [];

        foreach ($bundles as $bundle) {
            $name = $bundle->getTranslation('name', $locale) ?? $bundle->slug;

            $item = [
                'slug' => $bundle->slug,
                'name' => $name,
                'locale' => $locale,
                'addon_count' => $bundle->addons->count(),
                'discount_percent' => $bundle->discount_percent,
                'actions' => [],
            ];

            if (! $bundle->stripe_product_id) {
                $item['actions'][] = "Create Product: \"{$name}\"";
            } else {
                $item['actions'][] = "Update Product: \"{$name}\"";
            }

            $monthlyPrice = $bundle->getEffectivePriceMonthly();
            if ($monthlyPrice > 0 && ! $bundle->stripe_price_monthly_id) {
                $item['actions'][] = 'Create Monthly Price ('.format_stripe_price($monthlyPrice, $bundle->currency).')';
            }

            $yearlyPrice = $bundle->getEffectivePriceYearly();
            if ($yearlyPrice > 0 && ! $bundle->stripe_price_yearly_id) {
                $item['actions'][] = 'Create Yearly Price ('.format_stripe_price($yearlyPrice, $bundle->currency).')';
            }

            $preview[] = $item;
        }

        return $preview;
    }
}
