<?php

namespace App\Services\Central;

use App\Exceptions\Central\FederationException;
use App\Models\Central\FederatedUser;
use App\Models\Central\FederatedUserLink;
use App\Models\Central\FederationConflict;
use App\Models\Central\FederationGroup;
use App\Models\Central\FederationGroupTenant;
use App\Models\Central\FederationSyncLog;
use App\Models\Central\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * FederationService (Central)
 *
 * Manages federation groups and coordinates user synchronization
 * across multiple tenants from the central database perspective.
 *
 * This service handles:
 * - Creating and managing federation groups
 * - Adding/removing tenants from groups
 * - Managing federated users at the central level
 * - Resolving conflicts between tenants
 */
class FederationService
{
    public function __construct(
        protected FederationAuditService $auditService,
        protected FederationCacheService $cacheService
    ) {}

    // =========================================================================
    // Federation Group Management
    // =========================================================================

    /**
     * Create a new federation group.
     *
     * @throws FederationException
     */
    public function createGroup(
        string $name,
        Tenant $masterTenant,
        ?string $description = null,
        string $syncStrategy = FederationGroup::STRATEGY_MASTER_WINS,
        array $settings = []
    ): FederationGroup {
        // Check if master tenant is already in a group
        if ($this->getTenantGroup($masterTenant)) {
            throw FederationException::tenantAlreadyInGroup($masterTenant);
        }

        return DB::transaction(function () use ($name, $masterTenant, $description, $syncStrategy, $settings) {
            // Create the group
            $group = FederationGroup::create([
                'name' => $name,
                'description' => $description,
                'master_tenant_id' => $masterTenant->id,
                'sync_strategy' => $syncStrategy,
                'settings' => array_merge([
                    'sync_fields' => FederationGroup::DEFAULT_SYNC_FIELDS,
                    'auto_create_on_login' => true,
                    'require_email_verification' => false,
                ], $settings),
                'is_active' => true,
            ]);

            // Add master tenant to the group
            FederationGroupTenant::create([
                'federation_group_id' => $group->id,
                'tenant_id' => $masterTenant->id,
                'sync_enabled' => true,
                'joined_at' => now(),
                'settings' => [
                    'default_role' => 'member',
                    'auto_accept_users' => true,
                ],
            ]);

            // Log the operation
            $this->auditService->logGroupCreated($group, $masterTenant);

            // Clear cache
            $this->cacheService->invalidateTenant($masterTenant->id);

            return $group;
        });
    }

    /**
     * Update a federation group.
     */
    public function updateGroup(
        FederationGroup $group,
        array $data
    ): FederationGroup {
        $oldData = $group->only(['name', 'description', 'sync_strategy', 'settings']);

        $group->update($data);

        $this->auditService->logGroupUpdated($group, $oldData, $data);

        return $group->fresh();
    }

    /**
     * Delete a federation group.
     *
     * This will unlink all users but NOT delete them from tenant databases.
     *
     * @throws FederationException
     */
    public function deleteGroup(FederationGroup $group): void
    {
        DB::transaction(function () use ($group) {
            // Get all tenant IDs for cache invalidation
            $tenantIds = $group->tenants()->pluck('tenants.id')->toArray();

            // Log before deletion
            $this->auditService->logGroupDeleted($group);

            // Delete all related records (cascade will handle most)
            $group->delete();

            // Clear cache for all affected tenants
            foreach ($tenantIds as $tenantId) {
                $this->cacheService->invalidateTenant($tenantId);
            }
        });
    }

    /**
     * Get a federation group by ID.
     */
    public function getGroup(string $groupId): ?FederationGroup
    {
        return FederationGroup::with(['masterTenant', 'tenants'])->find($groupId);
    }

    /**
     * Get all federation groups with optional filters.
     */
    public function getGroups(array $filters = []): Collection
    {
        $query = FederationGroup::with(['masterTenant', 'tenants']);

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['master_tenant_id'])) {
            $query->where('master_tenant_id', $filters['master_tenant_id']);
        }

        return $query->orderBy('name')->get();
    }

    // =========================================================================
    // Tenant Membership
    // =========================================================================

    /**
     * Add a tenant to a federation group.
     *
     * @throws FederationException
     */
    public function addTenantToGroup(
        FederationGroup $group,
        Tenant $tenant,
        array $settings = []
    ): FederationGroupTenant {
        // Check if tenant is already in an active group
        $existingGroup = $this->getTenantGroup($tenant);
        if ($existingGroup) {
            throw FederationException::tenantAlreadyInGroup($tenant);
        }

        // Check if tenant previously left this group (can rejoin)
        $existingMembership = FederationGroupTenant::where('federation_group_id', $group->id)
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('left_at')
            ->first();

        if ($existingMembership) {
            // Rejoin existing membership
            return DB::transaction(function () use ($existingMembership, $group, $tenant, $settings) {
                $existingMembership->rejoin();

                // Update settings if provided
                if (!empty($settings)) {
                    $existingMembership->update([
                        'settings' => array_merge($existingMembership->settings ?? [], $settings),
                    ]);
                }

                // Log the operation
                $this->auditService->logTenantJoined($group, $tenant);

                // Clear cache
                $this->cacheService->invalidateTenant($tenant->id);
                $this->cacheService->invalidateGroup($group->id);

                return $existingMembership->fresh();
            });
        }

        // Create new membership
        return DB::transaction(function () use ($group, $tenant, $settings) {
            $membership = FederationGroupTenant::create([
                'federation_group_id' => $group->id,
                'tenant_id' => $tenant->id,
                'sync_enabled' => true,
                'joined_at' => now(),
                'settings' => array_merge([
                    'default_role' => 'member',
                    'auto_accept_users' => true,
                    'require_approval' => false,
                ], $settings),
            ]);

            // Log the operation
            $this->auditService->logTenantJoined($group, $tenant);

            // Clear cache
            $this->cacheService->invalidateTenant($tenant->id);
            $this->cacheService->invalidateGroup($group->id);

            return $membership;
        });
    }

    /**
     * Remove a tenant from a federation group.
     *
     * @throws FederationException
     */
    public function removeTenantFromGroup(
        FederationGroup $group,
        Tenant $tenant
    ): void {
        // Cannot remove master tenant
        if ($group->isMaster($tenant)) {
            throw FederationException::cannotRemoveMasterTenant();
        }

        $membership = FederationGroupTenant::where('federation_group_id', $group->id)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$membership) {
            throw FederationException::tenantNotInGroup($tenant, $group);
        }

        DB::transaction(function () use ($group, $tenant, $membership) {
            // Mark as left (soft removal)
            $membership->update([
                'left_at' => now(),
                'sync_enabled' => false,
            ]);

            // Remove all user links for this tenant
            FederatedUserLink::where('tenant_id', $tenant->id)
                ->whereHas('federatedUser', fn($q) => $q->where('federation_group_id', $group->id))
                ->delete();

            // Log the operation
            $this->auditService->logTenantLeft($group, $tenant);

            // Clear cache
            $this->cacheService->invalidateTenant($tenant->id);
            $this->cacheService->invalidateGroup($group->id);
        });
    }

    /**
     * Get the federation group a tenant belongs to.
     */
    public function getTenantGroup(Tenant $tenant): ?FederationGroup
    {
        return $this->cacheService->getTenantGroup($tenant->id);
    }

    /**
     * Check if a tenant is in a federation group.
     */
    public function isTenantInGroup(Tenant $tenant): bool
    {
        return $this->getTenantGroup($tenant) !== null;
    }

    /**
     * Get tenant's membership details in a group.
     */
    public function getTenantMembership(Tenant $tenant): ?FederationGroupTenant
    {
        return FederationGroupTenant::where('tenant_id', $tenant->id)
            ->whereNull('left_at')
            ->first();
    }

    // =========================================================================
    // Federated User Management
    // =========================================================================

    /**
     * Create a federated user record.
     */
    public function createFederatedUser(
        FederationGroup $group,
        string $email,
        array $syncedData,
        Tenant $masterTenant,
        string $masterTenantUserId
    ): FederatedUser {
        return DB::transaction(function () use ($group, $email, $syncedData, $masterTenant, $masterTenantUserId) {
            $federatedUser = FederatedUser::create([
                'federation_group_id' => $group->id,
                'global_email' => strtolower($email),
                'synced_data' => $syncedData,
                'master_tenant_id' => $masterTenant->id,
                'master_tenant_user_id' => $masterTenantUserId,
                'last_synced_at' => now(),
                'last_sync_source' => $masterTenant->id,
                'sync_version' => 1,
                'status' => FederatedUser::STATUS_ACTIVE,
            ]);

            // Create link for master tenant
            FederatedUserLink::create([
                'federated_user_id' => $federatedUser->id,
                'tenant_id' => $masterTenant->id,
                'tenant_user_id' => $masterTenantUserId,
                'sync_status' => FederatedUserLink::STATUS_SYNCED,
                'last_synced_at' => now(),
                'metadata' => [
                    'created_via' => FederatedUserLink::CREATED_VIA_AUTO_SYNC,
                    'is_master' => true,
                ],
            ]);

            $this->auditService->logUserCreated($group, $federatedUser, $masterTenant);

            return $federatedUser;
        });
    }

    /**
     * Find a federated user by email in a group.
     */
    public function findFederatedUserByEmail(FederationGroup $group, string $email): ?FederatedUser
    {
        return FederatedUser::findByEmailInGroup($email, $group->id);
    }

    /**
     * Find a federated user by tenant user ID.
     */
    public function findFederatedUserByTenantUser(string $tenantId, string $tenantUserId): ?FederatedUser
    {
        $link = FederatedUserLink::where('tenant_id', $tenantId)
            ->where('tenant_user_id', $tenantUserId)
            ->first();

        return $link?->federatedUser;
    }

    /**
     * Create a link between a federated user and a tenant user.
     */
    public function createUserLink(
        FederatedUser $federatedUser,
        Tenant $tenant,
        string $tenantUserId,
        string $createdVia = FederatedUserLink::CREATED_VIA_AUTO_SYNC
    ): FederatedUserLink {
        return FederatedUserLink::create([
            'federated_user_id' => $federatedUser->id,
            'tenant_id' => $tenant->id,
            'tenant_user_id' => $tenantUserId,
            'sync_status' => FederatedUserLink::STATUS_SYNCED,
            'last_synced_at' => now(),
            'metadata' => [
                'created_via' => $createdVia,
            ],
        ]);
    }

    /**
     * Update federated user's synced data.
     */
    public function updateFederatedUserData(
        FederatedUser $federatedUser,
        array $newData,
        Tenant $sourceTenant
    ): void {
        $group = $federatedUser->federationGroup;
        $oldData = $federatedUser->synced_data;

        // Check sync strategy
        if ($group->sync_strategy === FederationGroup::STRATEGY_MASTER_WINS) {
            // Only accept updates from master tenant
            if ($sourceTenant->id !== $group->master_tenant_id) {
                // Non-master update - check if we should create a conflict
                if ($group->sync_strategy === FederationGroup::STRATEGY_MANUAL_REVIEW) {
                    $this->createConflict($federatedUser, $newData, $sourceTenant);
                }
                return;
            }
        }

        // Update the synced data
        $federatedUser->updateSyncedData($newData, $sourceTenant->id);

        // Log the operation
        $this->auditService->logUserUpdated($group, $federatedUser, $sourceTenant, $oldData, $newData);
    }

    // =========================================================================
    // Conflict Management
    // =========================================================================

    /**
     * Create a conflict record for manual review.
     */
    public function createConflict(
        FederatedUser $federatedUser,
        array $conflictingData,
        Tenant $sourceTenant
    ): void {
        foreach ($conflictingData as $field => $value) {
            $conflict = FederationConflict::findOrCreatePending(
                $federatedUser->id,
                $field
            );

            $conflict->addConflictingValue($sourceTenant->id, $value);
        }

        $this->auditService->logConflictDetected(
            $federatedUser->federationGroup,
            $federatedUser,
            array_keys($conflictingData)
        );
    }

    /**
     * Get pending conflicts for a group.
     */
    public function getPendingConflicts(FederationGroup $group): Collection
    {
        return FederationConflict::pending()
            ->whereHas('federatedUser', fn($q) => $q->where('federation_group_id', $group->id))
            ->with('federatedUser')
            ->get();
    }

    /**
     * Resolve a conflict.
     */
    public function resolveConflict(
        FederationConflict $conflict,
        mixed $resolvedValue,
        string $resolverId,
        string $resolution = FederationConflict::RESOLUTION_MANUAL,
        ?string $notes = null
    ): void {
        DB::transaction(function () use ($conflict, $resolvedValue, $resolverId, $resolution, $notes) {
            // Update the federated user with resolved value
            $conflict->federatedUser->updateSyncedField($conflict->field, $resolvedValue);

            // Mark conflict as resolved
            $conflict->resolve($resolvedValue, $resolverId, $resolution, $notes);

            // Log
            $this->auditService->logConflictResolved(
                $conflict->federatedUser->federationGroup,
                $conflict,
                $resolverId
            );
        });
    }

    // =========================================================================
    // Statistics
    // =========================================================================

    /**
     * Get statistics for a federation group.
     */
    public function getGroupStats(FederationGroup $group): array
    {
        return [
            'total_tenants' => $group->activeTenants()->count(),
            'total_federated_users' => $group->activeFederatedUsers()->count(),
            'pending_conflicts' => $this->getPendingConflicts($group)->count(),
            'recent_syncs' => FederationSyncLog::where('federation_group_id', $group->id)
                ->where('created_at', '>=', now()->subDay())
                ->count(),
        ];
    }

    /**
     * Get overall federation statistics.
     */
    public function getOverallStats(): array
    {
        return [
            'total_groups' => FederationGroup::active()->count(),
            'total_federated_tenants' => FederationGroupTenant::whereNull('left_at')->count(),
            'total_federated_users' => FederatedUser::active()->count(),
            'total_pending_conflicts' => FederationConflict::pending()->count(),
        ];
    }
}
