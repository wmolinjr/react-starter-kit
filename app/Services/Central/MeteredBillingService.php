<?php

namespace App\Services\Central;

use App\Models\Central\Addon;
use App\Models\Central\AddonSubscription;
use App\Models\Central\Tenant;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class MeteredBillingService
{
    protected ?StripeClient $stripe = null;

    public function __construct()
    {
        $secret = config('cashier.secret');
        if ($secret) {
            $this->stripe = new StripeClient($secret);
        }
    }

    /**
     * Get storage overage addon from database
     */
    protected function getStorageOverageAddon(): ?Addon
    {
        return Addon::where('slug', 'storage_overage')->first();
    }

    /**
     * Get bandwidth overage addon from database
     */
    protected function getBandwidthOverageAddon(): ?Addon
    {
        return Addon::where('slug', 'bandwidth_overage')->first();
    }

    /**
     * Report storage usage to Stripe
     */
    public function reportStorageUsage(Tenant $tenant): bool
    {
        if (! $tenant->stripe_id || ! $this->stripe) {
            return false;
        }

        $addon = $this->getStorageOverageAddon();
        if (! $addon || ! $addon->stripe_meter_id) {
            Log::warning('Storage overage addon not configured in database');

            return false;
        }

        $storageUsedMB = $tenant->getCurrentUsage('storage') ?? 0;
        $planLimitMB = $tenant->plan?->getLimit('storage') ?? 0;

        // Convert to GB for billing
        $storageUsedGB = $storageUsedMB / 1024;
        $planLimitGB = $planLimitMB / 1024;

        // Only report overage
        $overageGB = max(0, $storageUsedGB - $planLimitGB);

        if ($overageGB <= 0) {
            return false;
        }

        try {
            $this->stripe->billing->meterEvents->create([
                'event_name' => $addon->stripe_meter_id,
                'payload' => [
                    'stripe_customer_id' => $tenant->stripe_id,
                    'value' => (string) round($overageGB, 2),
                ],
            ]);

            // Update metered addon record if exists
            $this->updateMeteredAddon($tenant, 'storage_overage', $overageGB);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to report storage usage to Stripe', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Report bandwidth usage to Stripe
     */
    public function reportBandwidthUsage(Tenant $tenant): bool
    {
        if (! $tenant->stripe_id || ! $this->stripe) {
            return false;
        }

        $addon = $this->getBandwidthOverageAddon();
        if (! $addon || ! $addon->stripe_meter_id) {
            Log::warning('Bandwidth overage addon not configured in database');

            return false;
        }

        $bandwidthUsedMB = $tenant->getCurrentUsage('bandwidth') ?? 0;
        $freeTierMB = $addon->free_tier ?? 100000; // Default 100GB if not set

        // Convert to GB
        $bandwidthUsedGB = $bandwidthUsedMB / 1024;
        $freeTierGB = $freeTierMB / 1024;

        // Only report overage past free tier
        $overageGB = max(0, $bandwidthUsedGB - $freeTierGB);

        if ($overageGB <= 0) {
            return false;
        }

        try {
            $this->stripe->billing->meterEvents->create([
                'event_name' => $addon->stripe_meter_id,
                'payload' => [
                    'stripe_customer_id' => $tenant->stripe_id,
                    'value' => (string) round($overageGB, 2),
                ],
            ]);

            $this->updateMeteredAddon($tenant, 'bandwidth_overage', $overageGB);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to report bandwidth usage to Stripe', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Update or create metered addon record
     */
    protected function updateMeteredAddon(Tenant $tenant, string $addonSlug, float $usage): void
    {
        $addon = AddonSubscription::where('tenant_id', $tenant->id)
            ->where('addon_slug', $addonSlug)
            ->first();

        if ($addon) {
            $addon->incrementMeteredUsage((int) round($usage * 1024)); // Store as MB
        }
    }

    /**
     * Report all usage for a single tenant
     */
    public function reportTenantUsage(Tenant $tenant): array
    {
        $results = [
            'storage' => $this->reportStorageUsage($tenant),
            'bandwidth' => $this->reportBandwidthUsage($tenant),
        ];

        return $results;
    }

    /**
     * Report usage for all tenants
     */
    public function reportAllTenants(): int
    {
        $count = 0;

        Tenant::query()
            ->whereNotNull('stripe_id')
            ->chunk(100, function ($tenants) use (&$count) {
                foreach ($tenants as $tenant) {
                    try {
                        $results = $this->reportTenantUsage($tenant);
                        if ($results['storage'] || $results['bandwidth']) {
                            $count++;
                        }
                    } catch (\Exception $e) {
                        Log::error("Failed to report usage for tenant {$tenant->id}", [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        return $count;
    }

    /**
     * Reset metered usage for billing period
     */
    public function resetMeteredUsage(Tenant $tenant): void
    {
        AddonSubscription::where('tenant_id', $tenant->id)
            ->where('billing_period', 'metered')
            ->each(fn ($addon) => $addon->resetMeteredUsage());
    }

    /**
     * Get current metered usage summary for tenant
     */
    public function getUsageSummary(Tenant $tenant): array
    {
        $storageAddon = $this->getStorageOverageAddon();
        $bandwidthAddon = $this->getBandwidthOverageAddon();

        $storageUsed = $tenant->getCurrentUsage('storage') ?? 0;
        $bandwidthUsed = $tenant->getCurrentUsage('bandwidth') ?? 0;
        $planStorageLimit = $tenant->plan?->getLimit('storage') ?? 0;
        $bandwidthFreeTier = $bandwidthAddon?->free_tier ?? 100000;

        return [
            'storage' => [
                'used_mb' => $storageUsed,
                'limit_mb' => $planStorageLimit,
                'overage_mb' => max(0, $storageUsed - $planStorageLimit),
                'overage_cost' => $this->calculateOverageCost($storageAddon, max(0, $storageUsed - $planStorageLimit)),
            ],
            'bandwidth' => [
                'used_mb' => $bandwidthUsed,
                'free_tier_mb' => $bandwidthFreeTier,
                'overage_mb' => max(0, $bandwidthUsed - $bandwidthFreeTier),
                'overage_cost' => $this->calculateOverageCost($bandwidthAddon, max(0, $bandwidthUsed - $bandwidthFreeTier)),
            ],
        ];
    }

    /**
     * Calculate overage cost
     */
    protected function calculateOverageCost(?Addon $addon, int $overageMB): int
    {
        if (! $addon) {
            return 0;
        }

        $overageGB = $overageMB / 1024;
        $pricePerGB = $addon->price_metered ?? 0;

        return (int) round($overageGB * $pricePerGB);
    }
}
