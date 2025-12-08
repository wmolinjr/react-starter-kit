<?php

namespace App\Services\Central;

use App\Exceptions\Central\FederationException;
use App\Models\Central\FederatedUser;
use App\Models\Central\FederatedUserLink;
use App\Models\Central\FederationGroup;
use App\Models\Central\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * FederationSyncService
 *
 * Handles the actual synchronization of user data across tenants.
 * This service coordinates:
 * - Propagating changes from master to other tenants
 * - Syncing new users to federated tenants
 * - Handling sync failures and retries
 */
class FederationSyncService
{
    public function __construct(
        protected FederationService $federationService,
        protected FederationAuditService $auditService,
        protected FederationCacheService $cacheService
    ) {}

    // =========================================================================
    // Sync Operations
    // =========================================================================

    /**
     * Sync a federated user's data to all linked tenants.
     *
     * @param FederatedUser $federatedUser The user to sync
     * @param array|null $fields Specific fields to sync (null = all)
     * @param string|null $excludeTenantId Tenant to exclude from sync (usually source)
     */
    public function syncUserToAllTenants(
        FederatedUser $federatedUser,
        ?array $fields = null,
        ?string $excludeTenantId = null
    ): array {
        $results = [
            'success' => [],
            'failed' => [],
            'skipped' => [],
        ];

        $group = $federatedUser->federationGroup;

        // Get all active links except excluded tenant
        $links = $federatedUser->activeLinks()
            ->when($excludeTenantId, fn($q) => $q->where('tenant_id', '!=', $excludeTenantId))
            ->get();

        foreach ($links as $link) {
            $tenant = Tenant::find($link->tenant_id);

            if (!$tenant) {
                $results['skipped'][] = [
                    'tenant_id' => $link->tenant_id,
                    'reason' => 'Tenant not found',
                ];
                continue;
            }

            // Check if tenant has sync enabled
            $membership = $group->tenants()
                ->wherePivot('tenant_id', $tenant->id)
                ->first();

            if (!$membership || !$membership->pivot->sync_enabled) {
                $results['skipped'][] = [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'reason' => 'Sync disabled',
                ];
                continue;
            }

            try {
                $this->syncUserToTenant($federatedUser, $tenant, $fields);

                $link->markAsSynced();

                $results['success'][] = [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                ];

            } catch (\Exception $e) {
                $link->markAsFailed($e->getMessage());

                $results['failed'][] = [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'error' => $e->getMessage(),
                ];

                // Log the failure
                $this->auditService->logSyncFailed(
                    $group,
                    $federatedUser,
                    $federatedUser->masterTenant,
                    $tenant,
                    $e->getMessage()
                );
            }
        }

        return $results;
    }

    /**
     * Sync user data to a specific tenant.
     *
     * @throws \Exception
     */
    public function syncUserToTenant(
        FederatedUser $federatedUser,
        Tenant $tenant,
        ?array $fields = null
    ): void {
        $syncedData = $federatedUser->synced_data;

        // Filter fields if specified
        if ($fields !== null) {
            $syncedData = array_intersect_key($syncedData, array_flip($fields));
        }

        // Run in tenant context
        $tenant->run(function () use ($federatedUser, $syncedData, $tenant) {
            $localUser = \App\Models\Tenant\User::where('federated_user_id', $federatedUser->id)->first();

            if (!$localUser) {
                // User doesn't exist locally - should we create?
                $group = $federatedUser->federationGroup;
                if ($group->shouldAutoCreateOnLogin()) {
                    // Create will happen on next login
                    return;
                }
                throw new \Exception("Local user not found for federated user {$federatedUser->id}");
            }

            // Apply synced data to local user
            $localUser->applyFederationSyncData($syncedData);
            $localUser->save();
        });

        // Update link status
        $link = FederatedUserLink::where('federated_user_id', $federatedUser->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($link) {
            $link->markAsSynced();
        }
    }

    /**
     * Sync password change to all linked tenants.
     */
    public function syncPasswordToAllTenants(
        FederatedUser $federatedUser,
        string $hashedPassword,
        string $sourceTenantId
    ): array {
        // Update the federated user's synced data
        $federatedUser->updateSyncedField('password_hash', $hashedPassword);
        $federatedUser->updateSyncedField('password_changed_at', now()->toIso8601String());

        // Sync to all tenants except source
        return $this->syncUserToAllTenants(
            $federatedUser,
            ['password_hash', 'password_changed_at'],
            $sourceTenantId
        );
    }

    /**
     * Sync 2FA changes to all linked tenants.
     */
    public function syncTwoFactorToAllTenants(
        FederatedUser $federatedUser,
        array $twoFactorData,
        string $sourceTenantId
    ): array {
        // Update the federated user's synced data
        $federatedUser->updateSyncedData($twoFactorData, $sourceTenantId);

        // Sync to all tenants except source
        return $this->syncUserToAllTenants(
            $federatedUser,
            array_keys($twoFactorData),
            $sourceTenantId
        );
    }

    // =========================================================================
    // Bulk Sync Operations
    // =========================================================================

    /**
     * Sync all federated users in a group to a newly added tenant.
     */
    public function syncAllUsersToNewTenant(
        FederationGroup $group,
        Tenant $tenant
    ): array {
        $results = [
            'total' => 0,
            'created' => 0,
            'linked' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $federatedUsers = $group->activeFederatedUsers()->get();
        $results['total'] = $federatedUsers->count();

        foreach ($federatedUsers as $federatedUser) {
            try {
                $result = $this->ensureUserExistsInTenant($federatedUser, $tenant);

                if ($result === 'created') {
                    $results['created']++;
                } elseif ($result === 'linked') {
                    $results['linked']++;
                } else {
                    $results['skipped']++;
                }

            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'email' => $federatedUser->global_email,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Ensure a federated user exists in a tenant (create or link).
     *
     * @return string 'created', 'linked', or 'exists'
     */
    public function ensureUserExistsInTenant(
        FederatedUser $federatedUser,
        Tenant $tenant
    ): string {
        // Check if already linked
        if ($federatedUser->hasLinkToTenant($tenant)) {
            return 'exists';
        }

        $result = 'created';

        $tenant->run(function () use ($federatedUser, $tenant, &$result) {
            // Check if user exists locally with same email
            $localUser = \App\Models\Tenant\User::where('email', $federatedUser->global_email)->first();

            if ($localUser) {
                // User exists - just link
                $localUser->federated_user_id = $federatedUser->id;
                $localUser->save();
                $result = 'linked';
            } else {
                // Create new user from federation data
                $this->createLocalUserInTenant($federatedUser, $tenant);
                $result = 'created';
            }
        });

        // Create the link record (outside tenant context - central DB)
        if (!$federatedUser->hasLinkToTenant($tenant)) {
            FederatedUserLink::create([
                'federated_user_id' => $federatedUser->id,
                'tenant_id' => $tenant->id,
                'tenant_user_id' => $this->getTenantUserId($federatedUser, $tenant),
                'sync_status' => FederatedUserLink::STATUS_SYNCED,
                'last_synced_at' => now(),
                'metadata' => [
                    'created_via' => FederatedUserLink::CREATED_VIA_BULK_SYNC,
                ],
            ]);
        }

        return $result;
    }

    /**
     * Create a local user in a tenant from federation data.
     */
    protected function createLocalUserInTenant(
        FederatedUser $federatedUser,
        Tenant $tenant
    ): void {
        $group = $federatedUser->federationGroup;
        $membership = $group->tenants()
            ->wherePivot('tenant_id', $tenant->id)
            ->first();

        $defaultRole = $membership?->pivot->settings['default_role'] ?? 'member';
        $syncedData = $federatedUser->synced_data;

        $tenant->run(function () use ($federatedUser, $syncedData, $defaultRole) {
            $user = \App\Models\Tenant\User::create([
                'name' => $syncedData['name'] ?? 'User',
                'email' => $federatedUser->global_email,
                'password' => $syncedData['password_hash'] ?? \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)),
                'locale' => $syncedData['locale'] ?? 'en',
                'email_verified_at' => now(),
                'federated_user_id' => $federatedUser->id,
            ]);

            // Apply 2FA if enabled
            if (!empty($syncedData['two_factor_secret'])) {
                $user->two_factor_secret = $syncedData['two_factor_secret'];
                $user->two_factor_recovery_codes = $syncedData['two_factor_recovery_codes'] ?? null;
                $user->two_factor_confirmed_at = isset($syncedData['two_factor_confirmed_at'])
                    ? \Carbon\Carbon::parse($syncedData['two_factor_confirmed_at'])
                    : null;
                $user->save();
            }

            // Assign default role
            $user->assignRole($defaultRole);
        });
    }

    /**
     * Get the tenant user ID for a federated user in a specific tenant.
     */
    protected function getTenantUserId(FederatedUser $federatedUser, Tenant $tenant): ?string
    {
        $userId = null;

        $tenant->run(function () use ($federatedUser, &$userId) {
            $localUser = \App\Models\Tenant\User::where('federated_user_id', $federatedUser->id)->first();
            $userId = $localUser?->id;
        });

        return $userId;
    }

    // =========================================================================
    // Retry Failed Syncs
    // =========================================================================

    /**
     * Retry failed sync operations for a federated user.
     */
    public function retryFailedSyncs(FederatedUser $federatedUser): array
    {
        $results = [
            'retried' => 0,
            'success' => 0,
            'still_failed' => 0,
        ];

        $failedLinks = $federatedUser->links()
            ->where('sync_status', FederatedUserLink::STATUS_SYNC_FAILED)
            ->get();

        foreach ($failedLinks as $link) {
            $results['retried']++;

            $tenant = Tenant::find($link->tenant_id);
            if (!$tenant) {
                $results['still_failed']++;
                continue;
            }

            try {
                $this->syncUserToTenant($federatedUser, $tenant);
                $link->markAsSynced();
                $results['success']++;

                // Log retry success
                $this->auditService->logSyncRetry(
                    $federatedUser->federationGroup,
                    $federatedUser,
                    $tenant,
                    $link->sync_attempts ?? 1
                );

            } catch (\Exception $e) {
                $link->incrementSyncAttempts();
                $link->markAsFailed($e->getMessage());
                $results['still_failed']++;
            }
        }

        return $results;
    }

    /**
     * Get all links with failed sync status for a group.
     */
    public function getFailedSyncsForGroup(FederationGroup $group): Collection
    {
        return FederatedUserLink::where('sync_status', FederatedUserLink::STATUS_SYNC_FAILED)
            ->whereHas('federatedUser', fn($q) => $q->where('federation_group_id', $group->id))
            ->with(['federatedUser', 'tenant'])
            ->get();
    }

    // =========================================================================
    // Validation
    // =========================================================================

    /**
     * Check if sync is allowed between source tenant and federation group.
     *
     * @throws FederationException
     */
    public function validateSyncPermission(
        FederationGroup $group,
        Tenant $sourceTenant
    ): void {
        // Check if group is active
        if (!$group->is_active) {
            throw FederationException::groupNotActive($group);
        }

        // Check if tenant is member
        $membership = $group->tenants()
            ->wherePivot('tenant_id', $sourceTenant->id)
            ->whereNull('federation_group_tenants.left_at')
            ->first();

        if (!$membership) {
            throw FederationException::tenantNotInGroup($sourceTenant, $group);
        }

        // Check if sync is enabled for tenant
        if (!$membership->pivot->sync_enabled) {
            throw FederationException::syncDisabled($sourceTenant);
        }
    }

    /**
     * Check if a tenant can initiate sync (based on strategy).
     */
    public function canTenantInitiateSync(
        FederationGroup $group,
        Tenant $tenant
    ): bool {
        if ($group->sync_strategy === FederationGroup::STRATEGY_MASTER_WINS) {
            return $group->isMaster($tenant);
        }

        // For other strategies, any tenant can initiate
        return true;
    }
}
