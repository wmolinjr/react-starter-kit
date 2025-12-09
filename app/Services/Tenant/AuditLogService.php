<?php

namespace App\Services\Tenant;

use App\Enums\PlanLimit;
use App\Models\Shared\Activity;
use App\Services\Shared\AuditLogService as SharedAuditLogService;

/**
 * Tenant AuditLogService
 *
 * Extends shared service with tenant-specific features:
 * - Log retention based on plan limits (PlanLimit::LOG_RETENTION)
 * - Retention info for UI display
 * - Storage limit warnings
 *
 * MULTI-DATABASE TENANCY:
 * - Uses App\Models\Shared\Activity (same model, different database)
 * - Activity log stored in tenant database (no tenant_id needed)
 * - Data isolation is at database level
 */
class AuditLogService extends SharedAuditLogService
{
    /**
     * Get the Activity model class for tenant context.
     *
     * @return class-string<Activity>
     */
    protected function getActivityModel(): string
    {
        return Activity::class;
    }

    /**
     * Get the causer type for tenant context.
     */
    protected function getCauserType(): string
    {
        return 'user'; // Morph map key for Tenant\User
    }

    /**
     * Get the log retention days based on tenant's plan.
     * Uses PlanLimit::LOG_RETENTION from the plan limits.
     */
    public function getRetentionDays(): int
    {
        $tenant = tenant();

        if (! $tenant) {
            return config('activitylog.delete_records_older_than_days', 90);
        }

        // Get retention from plan limits
        $limits = $tenant->effectiveLimits();
        $retention = $limits[PlanLimit::LOG_RETENTION->value] ?? 90;

        // -1 means unlimited, use a large number
        if ($retention === -1) {
            return 36500; // ~100 years
        }

        return max(7, $retention); // Minimum 7 days
    }

    /**
     * Prune old records based on plan retention limits.
     * Overrides base implementation to respect tenant plan limits.
     */
    public function pruneOldRecords(?int $days = null): int
    {
        $days = $days ?? $this->getRetentionDays();

        return $this->query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }

    /**
     * Get retention info for display in UI.
     *
     * @return array<string, mixed>
     */
    public function getRetentionInfo(): array
    {
        $retentionDays = $this->getRetentionDays();
        $oldestAllowed = now()->subDays($retentionDays);
        $totalRecords = $this->query()->count();
        $expiringSoon = $this->query()
            ->whereBetween('created_at', [
                $oldestAllowed,
                $oldestAllowed->copy()->addDays(7),
            ])
            ->count();

        return [
            'retention_days' => $retentionDays,
            'is_unlimited' => $retentionDays >= 36500,
            'oldest_allowed_date' => $oldestAllowed->toDateString(),
            'total_records' => $totalRecords,
            'expiring_soon' => $expiringSoon, // Will be deleted within 7 days
        ];
    }

    /**
     * Check if the current log count is approaching storage concerns.
     * Useful for showing warnings in the UI.
     */
    public function isApproachingLimit(int $threshold = 10000): bool
    {
        return $this->query()->count() > $threshold;
    }
}
