<?php

namespace App\Services\Central;

use App\Models\Central\Activity;
use App\Services\Shared\AuditLogService as SharedAuditLogService;

/**
 * Central AuditLogService
 *
 * Extends shared service for central admin context.
 * Logs admin actions on tenants, plans, addons, system config, etc.
 *
 * MULTI-DATABASE TENANCY:
 * - Uses App\Models\Central\Activity (with CentralConnection)
 * - ALWAYS writes to central database, even when tenant context is active
 * - Critical for logging federation activities in queue jobs
 * - No retention limits (configurable via config only)
 */
class AuditLogService extends SharedAuditLogService
{
    /**
     * Get the Activity model class for central context.
     * Uses Central\Activity with CentralConnection to ensure
     * logs ALWAYS go to central database.
     *
     * @return class-string<Activity>
     */
    protected function getActivityModel(): string
    {
        return Activity::class;
    }

    /**
     * Get the causer type for central context.
     */
    protected function getCauserType(): string
    {
        return 'admin'; // Morph map key for Central\User
    }

    /**
     * Get the retention days from config.
     * Central uses a fixed config value, not plan-based.
     */
    public function getRetentionDays(): int
    {
        return config('activitylog.delete_records_older_than_days', 90);
    }

    /**
     * Prune old records based on config retention limit.
     */
    public function pruneOldRecords(?int $days = null): int
    {
        $days = $days ?? $this->getRetentionDays();

        return $this->query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }

    /**
     * Get statistics for central admin dashboard.
     *
     * @return array<string, mixed>
     */
    public function getDashboardStatistics(): array
    {
        $stats = $this->getStatistics(
            now()->subDays(30)->toDateString(),
            now()->toDateString()
        );

        $stats['recent_entries'] = $this->query()
            ->with(['causer:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return $stats;
    }
}
