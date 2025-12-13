<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Contracts\Payment\MeteredBillingGatewayInterface;
use App\Contracts\Payment\PaymentGatewayInterface;
use App\Models\Central\Addon;
use App\Models\Central\AddonSubscription;
use App\Models\Central\Tenant;
use App\Models\Central\UsageRecord;
use App\Services\Payment\PaymentGatewayManager;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MeteredBillingService
{
    protected ?PaymentGatewayInterface $gateway = null;

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
     * Check if gateway supports metered billing.
     */
    protected function supportsMeteredBilling(): bool
    {
        return $this->gateway instanceof MeteredBillingGatewayInterface;
    }

    /**
     * Get the current provider identifier.
     */
    public function getProvider(): string
    {
        return $this->gateway?->getIdentifier() ?? 'unknown';
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
     * Record usage for a tenant.
     * Creates a local UsageRecord for historical tracking.
     */
    public function recordUsage(
        Tenant $tenant,
        string $usageType,
        int $quantity,
        string $unit = 'units',
        ?Addon $addon = null,
        ?array $metadata = null
    ): UsageRecord {
        $billingPeriod = $this->getCurrentBillingPeriod($tenant);
        $planLimit = $this->getPlanLimitForUsageType($tenant, $usageType);
        $overage = max(0, $quantity - $planLimit);
        $unitPrice = $addon?->price_metered ?? 0;
        $totalCost = $this->calculateUsageCost($overage, $unitPrice, $unit);

        return UsageRecord::create([
            'tenant_id' => $tenant->id,
            'addon_id' => $addon?->id,
            'usage_type' => $usageType,
            'quantity' => $quantity,
            'unit' => $unit,
            'plan_limit' => $planLimit,
            'overage' => $overage,
            'unit_price' => $unitPrice,
            'total_cost' => $totalCost,
            'billing_period_start' => $billingPeriod['start'],
            'billing_period_end' => $billingPeriod['end'],
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get current billing period for tenant.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public function getCurrentBillingPeriod(Tenant $tenant): array
    {
        // Try to get from subscription
        $subscription = $tenant->subscription('default');

        if ($subscription && $subscription->billing_period_starts_at) {
            $start = Carbon::parse($subscription->billing_period_starts_at);
            $end = Carbon::parse($subscription->billing_period_ends_at ?? $start->copy()->addMonth());
        } else {
            // Default to calendar month
            $start = Carbon::now()->startOfMonth();
            $end = Carbon::now()->endOfMonth();
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Get plan limit for a specific usage type.
     */
    protected function getPlanLimitForUsageType(Tenant $tenant, string $usageType): int
    {
        if (! $tenant->plan) {
            return 0;
        }

        return match ($usageType) {
            'storage' => $tenant->plan->getLimit('storage') ?? 0,
            'bandwidth' => 100000, // Default 100GB free tier
            'api_calls' => $tenant->plan->getLimit('api_calls') ?? 0,
            default => 0,
        };
    }

    /**
     * Calculate usage cost.
     */
    protected function calculateUsageCost(int $overage, int $unitPrice, string $unit): int
    {
        if ($overage <= 0 || $unitPrice <= 0) {
            return 0;
        }

        if ($unit === 'MB') {
            // Convert MB to GB for pricing
            $overageGB = $overage / 1024;

            return (int) round($overageGB * $unitPrice);
        }

        return $overage * $unitPrice;
    }

    /**
     * Report storage usage to payment provider
     */
    public function reportStorageUsage(Tenant $tenant): bool
    {
        $provider = $this->getProvider();
        $providerCustomerId = $tenant->customer?->getProviderCustomerId($provider);

        if (! $providerCustomerId || ! $this->isConfigured() || ! $this->supportsMeteredBilling()) {
            return false;
        }

        /** @var MeteredBillingGatewayInterface $gateway */
        $gateway = $this->gateway;

        $addon = $this->getStorageOverageAddon();
        $meterId = $addon?->getProviderMeterId($provider);
        if (! $addon || ! $meterId) {
            Log::warning('Storage overage addon not configured for provider', ['provider' => $provider]);

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
            $meterEvent = $gateway->createMeterEvent([
                'event_name' => $meterId,
                'identifier' => $providerCustomerId,
                'value' => (int) round($overageGB, 2),
            ]);

            // Record locally
            $record = $this->recordUsage(
                tenant: $tenant,
                usageType: 'storage',
                quantity: $storageUsedMB,
                unit: 'MB',
                addon: $addon,
                metadata: ['provider_event' => $meterEvent['id'] ?? null, 'provider' => $provider]
            );

            // Mark as reported
            $record->markAsReported($meterEvent['id'] ?? null);

            // Update metered addon record if exists
            $this->updateMeteredAddon($tenant, 'storage_overage', $overageGB);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to report storage usage to payment provider', [
                'tenant_id' => $tenant->id,
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Report bandwidth usage to payment provider
     */
    public function reportBandwidthUsage(Tenant $tenant): bool
    {
        $provider = $this->getProvider();
        $providerCustomerId = $tenant->customer?->getProviderCustomerId($provider);

        if (! $providerCustomerId || ! $this->isConfigured() || ! $this->supportsMeteredBilling()) {
            return false;
        }

        /** @var MeteredBillingGatewayInterface $gateway */
        $gateway = $this->gateway;

        $addon = $this->getBandwidthOverageAddon();
        $meterId = $addon?->getProviderMeterId($provider);
        if (! $addon || ! $meterId) {
            Log::warning('Bandwidth overage addon not configured for provider', ['provider' => $provider]);

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
            $meterEvent = $gateway->createMeterEvent([
                'event_name' => $meterId,
                'identifier' => $providerCustomerId,
                'value' => (int) round($overageGB, 2),
            ]);

            // Record locally
            $record = $this->recordUsage(
                tenant: $tenant,
                usageType: 'bandwidth',
                quantity: $bandwidthUsedMB,
                unit: 'MB',
                addon: $addon,
                metadata: ['provider_event' => $meterEvent['id'] ?? null, 'provider' => $provider]
            );

            // Mark as reported
            $record->markAsReported($meterEvent['id'] ?? null);

            $this->updateMeteredAddon($tenant, 'bandwidth_overage', $overageGB);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to report bandwidth usage to payment provider', [
                'tenant_id' => $tenant->id,
                'provider' => $provider,
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
        $provider = $this->getProvider();

        // Get tenants that have a customer ID for the current provider
        Tenant::query()
            ->whereHas('customer', function ($query) use ($provider) {
                $field = match ($provider) {
                    'stripe' => 'stripe_id',
                    'paddle' => 'paddle_id',
                    'asaas' => 'asaas_id',
                    'pagseguro' => 'pagseguro_id',
                    'mercadopago' => 'mercadopago_id',
                    default => 'stripe_id',
                };
                $query->whereNotNull($field);
            })
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

    /**
     * Get usage history for a tenant.
     *
     * @return Collection<int, UsageRecord>
     */
    public function getUsageHistory(
        Tenant $tenant,
        ?string $usageType = null,
        ?Carbon $from = null,
        ?Carbon $to = null,
        int $limit = 100
    ): Collection {
        $query = UsageRecord::where('tenant_id', $tenant->id);

        if ($usageType) {
            $query->where('usage_type', $usageType);
        }

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query->latest()->limit($limit)->get();
    }

    /**
     * Get aggregated usage for a billing period.
     */
    public function getAggregatedUsage(Tenant $tenant, Carbon $periodStart, Carbon $periodEnd): array
    {
        $records = UsageRecord::where('tenant_id', $tenant->id)
            ->where('billing_period_start', '>=', $periodStart)
            ->where('billing_period_end', '<=', $periodEnd)
            ->get();

        $aggregated = [];

        foreach ($records as $record) {
            $type = $record->usage_type;
            if (! isset($aggregated[$type])) {
                $aggregated[$type] = [
                    'total_quantity' => 0,
                    'total_overage' => 0,
                    'total_cost' => 0,
                    'records_count' => 0,
                ];
            }

            $aggregated[$type]['total_quantity'] += $record->quantity;
            $aggregated[$type]['total_overage'] += $record->overage;
            $aggregated[$type]['total_cost'] += $record->total_cost;
            $aggregated[$type]['records_count']++;
        }

        return $aggregated;
    }

    /**
     * Get unreported usage records that need to be sent to billing provider.
     *
     * @return Collection<int, UsageRecord>
     */
    public function getUnreportedRecords(?Tenant $tenant = null): Collection
    {
        $query = UsageRecord::where('reported_to_provider', false)
            ->where('overage', '>', 0);

        if ($tenant) {
            $query->where('tenant_id', $tenant->id);
        }

        return $query->get();
    }

    /**
     * Process and report all unreported usage records to payment provider.
     */
    public function processUnreportedRecords(): int
    {
        if (! $this->isConfigured() || ! $this->supportsMeteredBilling()) {
            return 0;
        }

        /** @var MeteredBillingGatewayInterface $gateway */
        $gateway = $this->gateway;
        $provider = $this->getProvider();

        $processed = 0;
        $records = $this->getUnreportedRecords();

        foreach ($records as $record) {
            try {
                $tenant = $record->tenant;
                $providerCustomerId = $tenant?->getProviderCustomerId($provider);
                if (! $tenant || ! $providerCustomerId) {
                    continue;
                }

                $addon = $record->addon;
                $meterId = $addon?->getProviderMeterId($provider);
                if (! $addon || ! $meterId) {
                    continue;
                }

                // Convert overage to appropriate unit
                $value = $record->unit === 'MB' ? $record->overage / 1024 : $record->overage;

                $meterEvent = $gateway->createMeterEvent([
                    'event_name' => $meterId,
                    'identifier' => $providerCustomerId,
                    'value' => (int) round($value, 2),
                ]);

                $record->markAsReported($meterEvent['id'] ?? null);
                $processed++;
            } catch (\Exception $e) {
                Log::error('Failed to process usage record', [
                    'record_id' => $record->id,
                    'provider' => $provider,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processed;
    }
}
