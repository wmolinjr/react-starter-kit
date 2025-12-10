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
 * Propagates a password change to all linked tenants.
 *
 * This job is dispatched when:
 * - A federated user changes their password in any tenant
 * - Admin resets a federated user's password
 */
class PropagatePasswordChangeJob implements ShouldQueue
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
     * @param  FederatedUser  $federatedUser  The user whose password changed
     * @param  string  $hashedPassword  The new hashed password
     * @param  string  $sourceTenantId  The tenant where the change originated
     */
    public function __construct(
        public FederatedUser $federatedUser,
        public string $hashedPassword,
        public string $sourceTenantId
    ) {
        $this->onQueue('federation');
    }

    /**
     * Execute the job.
     */
    public function handle(FederationSyncService $syncService): void
    {
        Log::info('PropagatePasswordChangeJob: Starting password propagation', [
            'federated_user_id' => $this->federatedUser->id,
            'email' => $this->federatedUser->global_email,
            'source_tenant' => $this->sourceTenantId,
        ]);

        $results = $syncService->syncPasswordToAllTenants(
            $this->federatedUser,
            $this->hashedPassword,
            $this->sourceTenantId
        );

        Log::info('PropagatePasswordChangeJob: Password propagation completed', [
            'federated_user_id' => $this->federatedUser->id,
            'success_count' => count($results['success']),
            'failed_count' => count($results['failed']),
            'skipped_count' => count($results['skipped']),
        ]);

        if (! empty($results['failed'])) {
            Log::warning('PropagatePasswordChangeJob: Some password syncs failed', [
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
        Log::error('PropagatePasswordChangeJob: Job failed', [
            'federated_user_id' => $this->federatedUser->id,
            'email' => $this->federatedUser->global_email,
            'source_tenant' => $this->sourceTenantId,
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
            'password-sync',
            'federated_user:'.$this->federatedUser->id,
            'group:'.$this->federatedUser->federation_group_id,
            'source:'.$this->sourceTenantId,
        ];
    }
}
