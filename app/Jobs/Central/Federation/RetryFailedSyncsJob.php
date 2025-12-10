<?php

namespace App\Jobs\Central\Federation;

use App\Models\Central\FederatedUser;
use App\Models\Central\FederationGroup;
use App\Services\Central\FederationSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Retries failed sync operations.
 *
 * This job can be:
 * - Scheduled to run periodically
 * - Dispatched manually from admin panel
 */
class RetryFailedSyncsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The maximum number of seconds the job should run.
     */
    public int $timeout = 300;

    /**
     * Create a new job instance.
     *
     * @param  FederationGroup|null  $group  Specific group to retry (null = all groups)
     * @param  FederatedUser|null  $federatedUser  Specific user to retry (null = all users)
     */
    public function __construct(
        public ?FederationGroup $group = null,
        public ?FederatedUser $federatedUser = null
    ) {
        $this->onQueue('federation');
    }

    /**
     * Execute the job.
     */
    public function handle(FederationSyncService $syncService): void
    {
        if ($this->federatedUser) {
            // Retry for specific user
            $this->retryForUser($syncService, $this->federatedUser);

            return;
        }

        if ($this->group) {
            // Retry for all users in group
            $this->retryForGroup($syncService, $this->group);

            return;
        }

        // Retry for all groups
        $this->retryAll($syncService);
    }

    /**
     * Retry failed syncs for a specific user.
     */
    protected function retryForUser(FederationSyncService $syncService, FederatedUser $user): void
    {
        Log::info('RetryFailedSyncsJob: Retrying for user', [
            'federated_user_id' => $user->id,
            'email' => $user->global_email,
        ]);

        $results = $syncService->retryFailedSyncs($user);

        Log::info('RetryFailedSyncsJob: User retry completed', [
            'federated_user_id' => $user->id,
            'retried' => $results['retried'],
            'success' => $results['success'],
            'still_failed' => $results['still_failed'],
        ]);
    }

    /**
     * Retry failed syncs for all users in a group.
     */
    protected function retryForGroup(FederationSyncService $syncService, FederationGroup $group): void
    {
        Log::info('RetryFailedSyncsJob: Retrying for group', [
            'group_id' => $group->id,
            'group_name' => $group->name,
        ]);

        $failedLinks = $syncService->getFailedSyncsForGroup($group);
        $userIds = $failedLinks->pluck('federated_user_id')->unique();

        $totalRetried = 0;
        $totalSuccess = 0;
        $totalStillFailed = 0;

        foreach ($userIds as $userId) {
            $user = FederatedUser::find($userId);
            if (! $user) {
                continue;
            }

            $results = $syncService->retryFailedSyncs($user);
            $totalRetried += $results['retried'];
            $totalSuccess += $results['success'];
            $totalStillFailed += $results['still_failed'];
        }

        Log::info('RetryFailedSyncsJob: Group retry completed', [
            'group_id' => $group->id,
            'users_processed' => $userIds->count(),
            'total_retried' => $totalRetried,
            'total_success' => $totalSuccess,
            'total_still_failed' => $totalStillFailed,
        ]);
    }

    /**
     * Retry failed syncs for all groups.
     */
    protected function retryAll(FederationSyncService $syncService): void
    {
        Log::info('RetryFailedSyncsJob: Retrying all failed syncs');

        $groups = FederationGroup::active()->get();

        foreach ($groups as $group) {
            $this->retryForGroup($syncService, $group);
        }

        Log::info('RetryFailedSyncsJob: All groups processed', [
            'groups_count' => $groups->count(),
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('RetryFailedSyncsJob: Job failed', [
            'group_id' => $this->group?->id,
            'federated_user_id' => $this->federatedUser?->id,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        $tags = ['federation', 'retry'];

        if ($this->group) {
            $tags[] = 'group:'.$this->group->id;
        }

        if ($this->federatedUser) {
            $tags[] = 'federated_user:'.$this->federatedUser->id;
        }

        return $tags;
    }
}
