<?php

namespace App\Jobs\Central\Federation;

use App\Models\Central\FederationGroup;
use App\Models\Central\Tenant;
use App\Services\Central\FederationSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Syncs all federated users to a newly added tenant.
 *
 * This job is dispatched when:
 * - A new tenant joins a federation group
 * - Admin triggers a bulk sync for a tenant
 */
class SyncAllUsersToTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public array $backoff = [60, 120, 300];

    /**
     * The maximum number of seconds the job should run.
     */
    public int $timeout = 600; // 10 minutes for large syncs

    /**
     * Create a new job instance.
     *
     * @param  FederationGroup  $group  The federation group
     * @param  Tenant  $tenant  The tenant to sync users to
     */
    public function __construct(
        public FederationGroup $group,
        public Tenant $tenant
    ) {
        $this->onQueue('federation');
    }

    /**
     * Execute the job.
     */
    public function handle(FederationSyncService $syncService): void
    {
        Log::info('SyncAllUsersToTenantJob: Starting bulk sync', [
            'group_id' => $this->group->id,
            'group_name' => $this->group->name,
            'tenant_id' => $this->tenant->id,
            'tenant_name' => $this->tenant->name,
        ]);

        $results = $syncService->syncAllUsersToNewTenant(
            $this->group,
            $this->tenant
        );

        Log::info('SyncAllUsersToTenantJob: Bulk sync completed', [
            'group_id' => $this->group->id,
            'tenant_id' => $this->tenant->id,
            'total' => $results['total'],
            'created' => $results['created'],
            'linked' => $results['linked'],
            'skipped' => $results['skipped'],
            'failed' => $results['failed'],
        ]);

        if ($results['failed'] > 0) {
            Log::warning('SyncAllUsersToTenantJob: Some users failed to sync', [
                'group_id' => $this->group->id,
                'tenant_id' => $this->tenant->id,
                'errors' => $results['errors'],
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SyncAllUsersToTenantJob: Job failed', [
            'group_id' => $this->group->id,
            'tenant_id' => $this->tenant->id,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'federation',
            'bulk-sync',
            'group:'.$this->group->id,
            'tenant:'.$this->tenant->id,
        ];
    }
}
