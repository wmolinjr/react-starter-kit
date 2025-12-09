<?php

namespace App\Jobs\Central;

use App\Models\Central\FederatedUser;
use App\Models\Central\FederatedUserLink;
use App\Models\Central\FederationGroup;
use App\Models\Central\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sync federated users from the new master tenant after a master change.
 *
 * This job runs after changing the master tenant and ensures:
 * 1. Users that exist in the new master get updated references
 * 2. Users that don't exist in the new master get created there
 */
class SyncFromNewMaster implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    public function __construct(
        public FederationGroup $group,
        public Tenant $newMaster
    ) {}

    public function handle(): void
    {
        Log::info('Starting sync from new master', [
            'group_id' => $this->group->id,
            'group_name' => $this->group->name,
            'new_master_id' => $this->newMaster->id,
            'new_master_name' => $this->newMaster->name,
        ]);

        // Get users pending master sync
        $pendingUsers = FederatedUser::where('federation_group_id', $this->group->id)
            ->where('status', FederatedUser::STATUS_PENDING_MASTER_SYNC)
            ->get();

        if ($pendingUsers->isEmpty()) {
            Log::info('No users pending master sync', [
                'group_id' => $this->group->id,
            ]);
            return;
        }

        Log::info('Found users pending master sync', [
            'group_id' => $this->group->id,
            'count' => $pendingUsers->count(),
        ]);

        // Run in tenant context
        $this->newMaster->run(function () use ($pendingUsers) {
            foreach ($pendingUsers as $federatedUser) {
                try {
                    $this->syncUserFromNewMaster($federatedUser);
                } catch (\Exception $e) {
                    Log::error('Failed to sync user from new master', [
                        'federated_user_id' => $federatedUser->id,
                        'email' => $federatedUser->global_email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        Log::info('Completed sync from new master', [
            'group_id' => $this->group->id,
            'users_processed' => $pendingUsers->count(),
        ]);
    }

    /**
     * Sync a single user from the new master.
     */
    protected function syncUserFromNewMaster(FederatedUser $federatedUser): void
    {
        // Find local user in new master by email
        $localUser = \App\Models\Tenant\User::where('email', $federatedUser->global_email)->first();

        if ($localUser) {
            // User exists in new master - update federated user with new master's data
            $federatedUser->update([
                'master_tenant_user_id' => $localUser->id,
                'synced_data' => $localUser->toFederationSyncData(),
                'last_synced_at' => now(),
                'last_sync_source' => $this->newMaster->id,
                'status' => FederatedUser::STATUS_ACTIVE,
                'sync_version' => $federatedUser->sync_version + 1,
            ]);

            // Ensure link exists
            $link = FederatedUserLink::where('federated_user_id', $federatedUser->id)
                ->where('tenant_id', $this->newMaster->id)
                ->first();

            if (!$link) {
                FederatedUserLink::create([
                    'federated_user_id' => $federatedUser->id,
                    'tenant_id' => $this->newMaster->id,
                    'tenant_user_id' => $localUser->id,
                    'sync_status' => FederatedUserLink::STATUS_SYNCED,
                    'last_synced_at' => now(),
                    'metadata' => [
                        'created_via' => 'master_change_sync',
                        'is_master' => true,
                    ],
                ]);
            }

            // Update local user's federated_user_id if not set
            if (!$localUser->federated_user_id) {
                $localUser->update(['federated_user_id' => $federatedUser->id]);
            }

            Log::info('Synced federated user from new master (existing)', [
                'federated_user_id' => $federatedUser->id,
                'local_user_id' => $localUser->id,
                'email' => $federatedUser->global_email,
            ]);
        } else {
            // User doesn't exist in new master - create them
            $this->createUserInNewMaster($federatedUser);
        }
    }

    /**
     * Create a user in the new master tenant from federation data.
     */
    protected function createUserInNewMaster(FederatedUser $federatedUser): void
    {
        $syncedData = $federatedUser->synced_data;

        // Create user in new master
        $localUser = \App\Models\Tenant\User::create([
            'name' => $syncedData['name'] ?? 'User',
            'email' => $federatedUser->global_email,
            'password' => $syncedData['password_hash'] ?? Hash::make(Str::random(32)),
            'locale' => $syncedData['locale'] ?? config('app.locale', 'en'),
            'email_verified_at' => now(),
            'federated_user_id' => $federatedUser->id,
        ]);

        // Apply 2FA if present
        if (!empty($syncedData['two_factor_secret'])) {
            $localUser->two_factor_secret = $syncedData['two_factor_secret'];
            $localUser->two_factor_recovery_codes = $syncedData['two_factor_recovery_codes'] ?? null;
            $localUser->two_factor_confirmed_at = isset($syncedData['two_factor_confirmed_at'])
                ? \Carbon\Carbon::parse($syncedData['two_factor_confirmed_at'])
                : null;
            $localUser->save();
        }

        // Assign default role
        $localUser->assignRole('member');

        // Create link
        FederatedUserLink::create([
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $this->newMaster->id,
            'tenant_user_id' => $localUser->id,
            'sync_status' => FederatedUserLink::STATUS_SYNCED,
            'last_synced_at' => now(),
            'metadata' => [
                'created_via' => 'master_change_creation',
                'is_master' => true,
            ],
        ]);

        // Update federated user
        $federatedUser->update([
            'master_tenant_user_id' => $localUser->id,
            'status' => FederatedUser::STATUS_ACTIVE,
        ]);

        Log::info('Created user in new master during master change', [
            'federated_user_id' => $federatedUser->id,
            'new_local_user_id' => $localUser->id,
            'email' => $federatedUser->global_email,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SyncFromNewMaster job failed', [
            'group_id' => $this->group->id,
            'new_master_id' => $this->newMaster->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
