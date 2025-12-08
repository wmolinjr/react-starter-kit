<?php

namespace App\Jobs\Central\Federation;

use App\Models\Central\FederatedUser;
use App\Services\Central\FederationSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Syncs a federated user's data to all linked tenants.
 *
 * This job is dispatched when:
 * - A federated user's profile is updated
 * - Manual sync is triggered from admin panel
 */
class SyncUserToFederatedTenantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public array $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     *
     * @param FederatedUser $federatedUser The user to sync
     * @param array|null $fields Specific fields to sync (null = all)
     * @param string|null $excludeTenantId Tenant to exclude (usually source)
     */
    public function __construct(
        public FederatedUser $federatedUser,
        public ?array $fields = null,
        public ?string $excludeTenantId = null
    ) {
        $this->onQueue('federation');
    }

    /**
     * Execute the job.
     */
    public function handle(FederationSyncService $syncService): void
    {
        Log::info('SyncUserToFederatedTenantsJob: Starting sync', [
            'federated_user_id' => $this->federatedUser->id,
            'email' => $this->federatedUser->global_email,
            'fields' => $this->fields,
            'exclude_tenant' => $this->excludeTenantId,
        ]);

        $results = $syncService->syncUserToAllTenants(
            $this->federatedUser,
            $this->fields,
            $this->excludeTenantId
        );

        Log::info('SyncUserToFederatedTenantsJob: Sync completed', [
            'federated_user_id' => $this->federatedUser->id,
            'success_count' => count($results['success']),
            'failed_count' => count($results['failed']),
            'skipped_count' => count($results['skipped']),
        ]);

        // If there were failures, we might want to retry specific tenants
        if (!empty($results['failed'])) {
            Log::warning('SyncUserToFederatedTenantsJob: Some syncs failed', [
                'federated_user_id' => $this->federatedUser->id,
                'failed' => $results['failed'],
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SyncUserToFederatedTenantsJob: Job failed', [
            'federated_user_id' => $this->federatedUser->id,
            'email' => $this->federatedUser->global_email,
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
            'sync',
            'federated_user:' . $this->federatedUser->id,
            'group:' . $this->federatedUser->federation_group_id,
        ];
    }
}
