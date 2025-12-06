<?php

namespace App\Services\Central;

use App\Enums\AddonStatus;
use App\Enums\AddonType;
use App\Enums\BillingPeriod;
use App\Exceptions\Central\AddonException;
use App\Exceptions\Central\AddonLimitExceededException;
use App\Models\Central\Addon;
use App\Models\Central\AddonBundle;
use App\Models\Central\AddonSubscription;
use App\Models\Central\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AddonService
{
    /**
     * Get addon from database by slug
     */
    public function getAddon(string $addonSlug): ?Addon
    {
        return Addon::where('slug', $addonSlug)->first();
    }

    /**
     * Get all available addons for a tenant's plan
     */
    public function getAvailableAddons(Tenant $tenant): array
    {
        if (! $tenant->plan) {
            return [];
        }

        return Addon::availableFor($tenant->plan)
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn ($addon) => [$addon->slug => $addon])
            ->toArray();
    }

    /**
     * Purchase an addon for a tenant
     */
    public function purchase(
        Tenant $tenant,
        string $addonSlug,
        int $quantity = 1,
        BillingPeriod|string $billingPeriod = BillingPeriod::MONTHLY
    ): AddonSubscription {
        $addon = $this->getAddon($addonSlug);

        if (! $addon) {
            throw new AddonException("Addon not found: {$addonSlug}");
        }

        $billingPeriod = $billingPeriod instanceof BillingPeriod
            ? $billingPeriod
            : BillingPeriod::from($billingPeriod);

        $this->validatePurchase($tenant, $addon, $quantity, $billingPeriod);

        return DB::transaction(function () use ($tenant, $addon, $quantity, $billingPeriod) {
            // Create addon record
            $tenantAddon = $this->createAddonSubscription($tenant, $addon, $quantity, $billingPeriod);

            // Add to Stripe if subscription exists
            if ($this->shouldAddToStripe($tenant, $billingPeriod)) {
                $this->addToStripeSubscription($tenant, $tenantAddon, $addon, $billingPeriod);
            }

            // Sync limits
            $this->syncTenantLimits($tenant);

            // Log activity if spatie/laravel-activitylog is available
            if (class_exists(\Spatie\Activitylog\Facades\Activity::class)) {
                activity()
                    ->performedOn($tenantAddon)
                    ->causedBy(auth()->user())
                    ->log("Purchased addon: {$tenantAddon->name} × {$quantity}");
            }

            return $tenantAddon->fresh();
        });
    }

    /**
     * Update addon quantity
     */
    public function updateQuantity(AddonSubscription $tenantAddon, int $newQuantity): AddonSubscription
    {
        $addon = $this->getAddon($tenantAddon->addon_slug);

        if (! $addon) {
            throw new AddonException("Addon not found: {$tenantAddon->addon_slug}");
        }

        // Validate quantity
        if ($newQuantity < $addon->min_quantity) {
            throw new AddonException("Minimum quantity is {$addon->min_quantity}");
        }

        if ($addon->max_quantity && $newQuantity > $addon->max_quantity) {
            throw new AddonLimitExceededException("Maximum quantity is {$addon->max_quantity}");
        }

        return DB::transaction(function () use ($tenantAddon, $newQuantity) {
            $oldQuantity = $tenantAddon->quantity;

            // Update Stripe if linked
            if ($tenantAddon->stripe_subscription_item_id) {
                $this->updateStripeQuantity($tenantAddon, $newQuantity);
            }

            // Update local record
            $tenantAddon->update(['quantity' => $newQuantity]);

            // Resync limits
            $this->syncTenantLimits($tenantAddon->tenant);

            if (class_exists(\Spatie\Activitylog\Facades\Activity::class)) {
                activity()
                    ->performedOn($tenantAddon)
                    ->causedBy(auth()->user())
                    ->log("Updated addon quantity: {$tenantAddon->name} from {$oldQuantity} to {$newQuantity}");
            }

            return $tenantAddon->fresh();
        });
    }

    /**
     * Cancel an addon
     */
    public function cancel(AddonSubscription $tenantAddon, ?string $reason = null): void
    {
        DB::transaction(function () use ($tenantAddon, $reason) {
            // Remove from Stripe if linked
            if ($tenantAddon->stripe_subscription_item_id) {
                $this->removeFromStripeSubscription($tenantAddon);
            }

            // Mark as canceled
            $tenantAddon->cancel($reason);

            // Resync limits
            $this->syncTenantLimits($tenantAddon->tenant);

            if (class_exists(\Spatie\Activitylog\Facades\Activity::class)) {
                activity()
                    ->performedOn($tenantAddon)
                    ->causedBy(auth()->user())
                    ->log("Canceled addon: {$tenantAddon->name}");
            }
        });
    }

    /**
     * Reactivate a canceled addon
     */
    public function reactivate(AddonSubscription $tenantAddon): AddonSubscription
    {
        if (! $tenantAddon->isCanceled()) {
            throw new AddonException('Addon is not canceled');
        }

        $addon = $this->getAddon($tenantAddon->addon_slug);

        return DB::transaction(function () use ($tenantAddon, $addon) {
            // Reactivate locally
            $tenantAddon->activate();

            // Re-add to Stripe if needed
            if ($addon && $this->shouldAddToStripe($tenantAddon->tenant, $tenantAddon->billing_period)) {
                $this->addToStripeSubscription($tenantAddon->tenant, $tenantAddon, $addon, $tenantAddon->billing_period);
            }

            // Resync limits
            $this->syncTenantLimits($tenantAddon->tenant);

            return $tenantAddon->fresh();
        });
    }

    /**
     * Sync tenant limits from active addons
     */
    public function syncTenantLimits(Tenant $tenant): void
    {
        $effectiveLimits = $tenant->getEffectiveLimits();

        $tenant->update([
            'plan_limits_override' => $effectiveLimits,
        ]);
    }

    /**
     * Add addon to Stripe subscription
     */
    protected function addToStripeSubscription(
        Tenant $tenant,
        AddonSubscription $tenantAddon,
        Addon $addon,
        BillingPeriod $billingPeriod
    ): void {
        try {
            $stripePriceId = $addon->getStripePriceId($billingPeriod->value);

            if (! $stripePriceId) {
                Log::warning('No Stripe price ID configured for addon', [
                    'addon_slug' => $addon->slug,
                    'billing_period' => $billingPeriod->value,
                ]);

                return;
            }

            $subscription = $tenant->subscription('default');

            if (! $subscription) {
                Log::warning('Tenant has no default subscription', [
                    'tenant_id' => $tenant->id,
                ]);

                return;
            }

            $subscriptionItem = $subscription->addPrice($stripePriceId, $tenantAddon->quantity);

            $tenantAddon->update([
                'stripe_subscription_item_id' => $subscriptionItem->stripe_id,
                'stripe_price_id' => $stripePriceId,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to add addon to Stripe', [
                'tenant_id' => $tenant->id,
                'addon_id' => $tenantAddon->id,
                'error' => $e->getMessage(),
            ]);
            throw new AddonException('Failed to add addon to subscription: '.$e->getMessage());
        }
    }

    /**
     * Update quantity in Stripe
     */
    protected function updateStripeQuantity(AddonSubscription $tenantAddon, int $newQuantity): void
    {
        try {
            $subscription = $tenantAddon->tenant->subscription('default');

            if ($subscription) {
                $subscription->updateQuantity($newQuantity, $tenantAddon->stripe_price_id);
            }
        } catch (\Exception $e) {
            Log::error('Failed to update Stripe quantity', [
                'addon_id' => $tenantAddon->id,
                'error' => $e->getMessage(),
            ]);
            throw new AddonException('Failed to update quantity in Stripe: '.$e->getMessage());
        }
    }

    /**
     * Remove addon from Stripe subscription
     */
    protected function removeFromStripeSubscription(AddonSubscription $tenantAddon): void
    {
        try {
            $subscription = $tenantAddon->tenant->subscription('default');

            if ($subscription && $tenantAddon->stripe_price_id) {
                $subscription->removePrice($tenantAddon->stripe_price_id);
            }

            $tenantAddon->update([
                'stripe_subscription_item_id' => null,
                'stripe_price_id' => null,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to remove addon from Stripe', [
                'addon_id' => $tenantAddon->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Validate purchase
     */
    protected function validatePurchase(
        Tenant $tenant,
        Addon $addon,
        int $quantity,
        BillingPeriod $billingPeriod
    ): void {
        // Check if available for plan
        if (! $addon->isAvailableForPlan($tenant->plan)) {
            throw new AddonException('This addon is not available for your plan');
        }

        // Check if billing period is available
        if (! $addon->supportsBillingPeriod($billingPeriod->value)) {
            throw new AddonException("Billing period '{$billingPeriod->value}' not available for this addon");
        }

        // Check quantity limits
        if ($quantity < $addon->min_quantity) {
            throw new AddonException("Minimum quantity is {$addon->min_quantity}");
        }

        if ($addon->max_quantity && $quantity > $addon->max_quantity) {
            throw new AddonLimitExceededException("Maximum quantity is {$addon->max_quantity}");
        }

        // Check if tenant already has this addon (for features - single instance only)
        if ($addon->type === AddonType::FEATURE && $tenant->hasActiveAddon($addon->slug)) {
            throw new AddonException('You already have this addon active');
        }
    }

    /**
     * Create tenant addon record
     */
    protected function createAddonSubscription(
        Tenant $tenant,
        Addon $addon,
        int $quantity,
        BillingPeriod $billingPeriod
    ): AddonSubscription {
        $price = match ($billingPeriod) {
            BillingPeriod::MONTHLY => $addon->price_monthly,
            BillingPeriod::YEARLY => $addon->price_yearly,
            BillingPeriod::ONE_TIME => $addon->price_one_time,
            default => $addon->price_monthly,
        };

        // Calculate expires_at for one-time purchases with validity
        $expiresAt = null;
        if ($billingPeriod === BillingPeriod::ONE_TIME && $addon->validity_months) {
            $expiresAt = now()->addMonths($addon->validity_months);
        }

        return AddonSubscription::create([
            'tenant_id' => $tenant->id,
            'addon_slug' => $addon->slug,
            'addon_type' => $addon->type,
            'name' => $addon->trans('name'),
            'description' => $addon->trans('description'),
            'quantity' => $quantity,
            'price' => $price ?? 0,
            'billing_period' => $billingPeriod,
            'status' => AddonStatus::ACTIVE,
            'started_at' => now(),
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Check if should add to Stripe
     */
    protected function shouldAddToStripe(Tenant $tenant, BillingPeriod $billingPeriod): bool
    {
        return $tenant->subscribed('default')
            && in_array($billingPeriod, [BillingPeriod::MONTHLY, BillingPeriod::YEARLY]);
    }

    /**
     * Check if a tenant can purchase an addon
     */
    public function canPurchase(Tenant $tenant, string $addonSlug, int $quantity = 1): bool
    {
        try {
            $addon = $this->getAddon($addonSlug);

            if (! $addon) {
                return false;
            }

            $this->validatePurchase($tenant, $addon, $quantity, BillingPeriod::MONTHLY);

            return true;
        } catch (AddonException) {
            return false;
        }
    }

    /**
     * Get tenant's active addons grouped by type
     */
    public function getActiveAddonsByType(Tenant $tenant): array
    {
        return $tenant->activeAddons
            ->groupBy(fn ($addon) => $addon->addon_type->value)
            ->toArray();
    }

    /**
     * Calculate total addon cost for tenant
     */
    public function calculateTotalMonthlyCost(Tenant $tenant): int
    {
        return $tenant->activeAddons->sum(function ($addon) {
            if ($addon->billing_period === BillingPeriod::YEARLY) {
                return (int) round($addon->total_price / 12);
            }

            return $addon->total_price;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Bundle Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Get a bundle by slug
     */
    public function getBundle(string $bundleSlug): ?AddonBundle
    {
        return AddonBundle::with('addons')->where('slug', $bundleSlug)->first();
    }

    /**
     * Get all available bundles for a tenant's plan
     */
    public function getAvailableBundles(Tenant $tenant): Collection
    {
        if (! $tenant->plan) {
            return collect();
        }

        return AddonBundle::with('addons')
            ->active()
            ->forPlan($tenant->plan)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Purchase a bundle for a tenant
     *
     * This creates individual AddonSubscription records for each addon in the bundle,
     * but tracks them together via metadata.
     *
     * @return Collection<AddonSubscription> Collection of created addons
     */
    public function purchaseBundle(
        Tenant $tenant,
        string $bundleSlug,
        BillingPeriod|string $billingPeriod = BillingPeriod::MONTHLY
    ): Collection {
        $bundle = $this->getBundle($bundleSlug);

        if (! $bundle) {
            throw new AddonException("Bundle not found: {$bundleSlug}");
        }

        $billingPeriod = $billingPeriod instanceof BillingPeriod
            ? $billingPeriod
            : BillingPeriod::from($billingPeriod);

        $this->validateBundlePurchase($tenant, $bundle, $billingPeriod);

        return DB::transaction(function () use ($tenant, $bundle, $billingPeriod) {
            $purchaseId = (string) \Illuminate\Support\Str::uuid();
            $createdAddons = collect();

            foreach ($bundle->getItemsForPurchase() as $item) {
                $addon = $item['addon'];
                $quantity = $item['quantity'];

                // Create individual addon with bundle reference
                $tenantAddon = $this->createAddonSubscriptionFromBundle(
                    $tenant,
                    $addon,
                    $quantity,
                    $billingPeriod,
                    $bundle,
                    $purchaseId
                );

                // Add to Stripe if subscription exists
                if ($this->shouldAddToStripe($tenant, $billingPeriod)) {
                    $this->addToStripeSubscription($tenant, $tenantAddon, $addon, $billingPeriod);
                }

                $createdAddons->push($tenantAddon);
            }

            // Sync limits after all addons created
            $this->syncTenantLimits($tenant);

            // Log bundle purchase
            if (class_exists(\Spatie\Activitylog\Facades\Activity::class)) {
                $bundleName = $bundle->getTranslation('name', app()->getLocale());
                activity()
                    ->performedOn($tenant)
                    ->causedBy(auth()->user())
                    ->withProperties([
                        'bundle_slug' => $bundle->slug,
                        'bundle_name' => $bundleName,
                        'purchase_id' => $purchaseId,
                        'addon_count' => $createdAddons->count(),
                        'total_price' => $bundle->getEffectivePriceMonthly(),
                    ])
                    ->log("Purchased bundle: {$bundleName}");
            }

            return $createdAddons;
        });
    }

    /**
     * Cancel all addons from a bundle purchase
     */
    public function cancelBundle(Tenant $tenant, string $purchaseId, ?string $reason = null): int
    {
        $addons = $tenant->addons()
            ->whereJsonContains('metadata->bundle_purchase_id', $purchaseId)
            ->get();

        if ($addons->isEmpty()) {
            throw new AddonException("No addons found for bundle purchase: {$purchaseId}");
        }

        return DB::transaction(function () use ($addons, $reason) {
            $canceled = 0;

            foreach ($addons as $addon) {
                if (! $addon->isCanceled()) {
                    $this->cancel($addon, $reason ?? 'Bundle canceled');
                    $canceled++;
                }
            }

            return $canceled;
        });
    }

    /**
     * Get active bundles for a tenant (grouped by purchase_id)
     */
    public function getActiveBundles(Tenant $tenant): Collection
    {
        return $tenant->activeAddons()
            ->whereNotNull('metadata->bundle_slug')
            ->get()
            ->groupBy(fn ($addon) => $addon->metadata['bundle_purchase_id'] ?? 'unknown')
            ->map(function ($addons, $purchaseId) {
                $first = $addons->first();

                return [
                    'purchase_id' => $purchaseId,
                    'bundle_slug' => $first->metadata['bundle_slug'] ?? null,
                    'bundle_name' => $first->metadata['bundle_name'] ?? null,
                    'addons' => $addons,
                    'addon_count' => $addons->count(),
                    'total_monthly' => $addons->sum('price'),
                    'started_at' => $first->started_at,
                ];
            })
            ->values();
    }

    /**
     * Check if tenant can purchase a bundle
     */
    public function canPurchaseBundle(Tenant $tenant, string $bundleSlug): bool
    {
        try {
            $bundle = $this->getBundle($bundleSlug);

            if (! $bundle) {
                return false;
            }

            $this->validateBundlePurchase($tenant, $bundle, BillingPeriod::MONTHLY);

            return true;
        } catch (AddonException) {
            return false;
        }
    }

    /**
     * Validate bundle purchase
     */
    protected function validateBundlePurchase(
        Tenant $tenant,
        AddonBundle $bundle,
        BillingPeriod $billingPeriod
    ): void {
        // Check if bundle is available for plan
        if (! $bundle->isAvailableForPlan($tenant->plan)) {
            throw new AddonException('This bundle is not available for your plan');
        }

        // Check if bundle is active
        if (! $bundle->active) {
            throw new AddonException('This bundle is no longer available');
        }

        // Check each addon in the bundle
        foreach ($bundle->addons as $addon) {
            $quantity = $addon->pivot->quantity ?? 1;

            // Skip availability check (bundle-level availability is enough)
            // But check quantity limits
            if ($addon->max_quantity && $quantity > $addon->max_quantity) {
                throw new AddonLimitExceededException(
                    "Bundle contains {$addon->trans('name')} with quantity {$quantity}, ".
                    "but maximum is {$addon->max_quantity}"
                );
            }

            // Check for duplicate FEATURE addons
            if ($addon->type === AddonType::FEATURE && $tenant->hasActiveAddon($addon->slug)) {
                throw new AddonException(
                    "You already have '{$addon->trans('name')}' active. ".
                    'Cannot purchase bundle with duplicate feature.'
                );
            }
        }
    }

    /**
     * Create tenant addon from bundle
     */
    protected function createAddonSubscriptionFromBundle(
        Tenant $tenant,
        Addon $addon,
        int $quantity,
        BillingPeriod $billingPeriod,
        AddonBundle $bundle,
        string $purchaseId
    ): AddonSubscription {
        // Calculate discounted price
        $basePrice = match ($billingPeriod) {
            BillingPeriod::MONTHLY => $addon->price_monthly,
            BillingPeriod::YEARLY => $addon->price_yearly,
            default => $addon->price_monthly,
        };

        // Apply bundle discount
        $discountedPrice = (int) round(
            ($basePrice ?? 0) * (1 - $bundle->discount_percent / 100)
        );

        return AddonSubscription::create([
            'tenant_id' => $tenant->id,
            'addon_slug' => $addon->slug,
            'addon_type' => $addon->type,
            'name' => $addon->trans('name'),
            'description' => $addon->trans('description'),
            'quantity' => $quantity,
            'price' => $discountedPrice,
            'billing_period' => $billingPeriod,
            'status' => AddonStatus::ACTIVE,
            'started_at' => now(),
            'metadata' => [
                'bundle_slug' => $bundle->slug,
                'bundle_name' => $bundle->getTranslation('name', app()->getLocale()),
                'bundle_purchase_id' => $purchaseId,
                'discount_percent' => $bundle->discount_percent,
                'original_price' => $basePrice,
            ],
        ]);
    }
}
