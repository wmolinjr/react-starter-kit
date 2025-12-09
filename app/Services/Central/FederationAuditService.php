<?php

namespace App\Services\Central;

use App\Models\Central\FederatedUser;
use App\Models\Central\FederationConflict;
use App\Models\Central\FederationGroup;
use App\Models\Central\FederationSyncLog;
use App\Models\Central\Tenant;

/**
 * FederationAuditService
 *
 * Handles audit logging for all federation operations.
 * Creates records in federation_sync_logs table.
 */
class FederationAuditService
{
    /**
     * Log group creation.
     */
    public function logGroupCreated(FederationGroup $group, Tenant $masterTenant): void
    {
        FederationSyncLog::logSuccess(
            groupId: $group->id,
            operation: FederationSyncLog::OP_TENANT_JOINED,
            sourceTenantId: $masterTenant->id,
            newData: [
                'group_name' => $group->name,
                'master_tenant' => $masterTenant->name,
                'event' => 'group_created',
            ]
        );
    }

    /**
     * Log group update.
     */
    public function logGroupUpdated(FederationGroup $group, array $oldData, array $newData): void
    {
        FederationSyncLog::logSuccess(
            groupId: $group->id,
            operation: FederationSyncLog::OP_USER_UPDATED,
            oldData: $oldData,
            newData: array_merge($newData, ['event' => 'group_updated'])
        );
    }

    /**
     * Log group deletion.
     */
    public function logGroupDeleted(FederationGroup $group): void
    {
        FederationSyncLog::logSuccess(
            groupId: $group->id,
            operation: FederationSyncLog::OP_USER_DELETED,
            oldData: [
                'group_name' => $group->name,
                'tenant_count' => $group->tenants()->count(),
                'user_count' => $group->federatedUsers()->count(),
                'event' => 'group_deleted',
            ]
        );
    }

    /**
     * Log tenant joining a group.
     */
    public function logTenantJoined(FederationGroup $group, Tenant $tenant): void
    {
        FederationSyncLog::logSuccess(
            groupId: $group->id,
            operation: FederationSyncLog::OP_TENANT_JOINED,
            targetTenantId: $tenant->id,
            newData: [
                'tenant_name' => $tenant->name,
                'tenant_id' => $tenant->id,
            ]
        );
    }

    /**
     * Log tenant leaving a group.
     */
    public function logTenantLeft(FederationGroup $group, Tenant $tenant): void
    {
        FederationSyncLog::logSuccess(
            groupId: $group->id,
            operation: FederationSyncLog::OP_TENANT_LEFT,
            sourceTenantId: $tenant->id,
            oldData: [
                'tenant_name' => $tenant->name,
                'tenant_id' => $tenant->id,
            ]
        );
    }

    /**
     * Log master tenant change.
     */
    public function logMasterChanged(
        FederationGroup $group,
        Tenant $oldMaster,
        Tenant $newMaster
    ): void {
        FederationSyncLog::logSuccess(
            groupId: $group->id,
            operation: FederationSyncLog::OP_MASTER_CHANGED,
            sourceTenantId: $oldMaster->id,
            targetTenantId: $newMaster->id,
            oldData: [
                'master_tenant_id' => $oldMaster->id,
                'master_tenant_name' => $oldMaster->name,
            ],
            newData: [
                'master_tenant_id' => $newMaster->id,
                'master_tenant_name' => $newMaster->name,
                'event' => 'master_changed',
            ]
        );
    }

    /**
     * Log federated user creation.
     */
    public function logUserCreated(
        FederationGroup $group,
        FederatedUser $federatedUser,
        Tenant $sourceTenant
    ): void {
        FederationSyncLog::logSuccess(
            groupId: $group->id,
            operation: FederationSyncLog::OP_USER_CREATED,
            federatedUserId: $federatedUser->id,
            sourceTenantId: $sourceTenant->id,
            newData: [
                'email' => $federatedUser->global_email,
                'name' => $federatedUser->getSyncedField('name'),
            ]
        );
    }

    /**
     * Log federated user update.
     */
    public function logUserUpdated(
        FederationGroup $group,
        FederatedUser $federatedUser,
        Tenant $sourceTenant,
        array $oldData,
        array $newData
    ): void {
        FederationSyncLog::logSuccess(
            groupId: $group->id,
            operation: FederationSyncLog::OP_USER_UPDATED,
            federatedUserId: $federatedUser->id,
            sourceTenantId: $sourceTenant->id,
            oldData: $oldData,
            newData: $newData
        );
    }

    /**
     * Log federated user deletion.
     */
    public function logUserDeleted(
        FederationGroup $group,
        FederatedUser $federatedUser,
        Tenant $sourceTenant
    ): void {
        FederationSyncLog::logSuccess(
            groupId: $group->id,
            operation: FederationSyncLog::OP_USER_DELETED,
            federatedUserId: $federatedUser->id,
            sourceTenantId: $sourceTenant->id,
            oldData: [
                'email' => $federatedUser->global_email,
                'name' => $federatedUser->getSyncedField('name'),
            ]
        );
    }

    /**
     * Log password change.
     */
    public function logPasswordChanged(
        FederationGroup $group,
        FederatedUser $federatedUser,
        Tenant $sourceTenant
    ): void {
        FederationSyncLog::logSuccess(
            groupId: $group->id,
            operation: FederationSyncLog::OP_PASSWORD_CHANGED,
            federatedUserId: $federatedUser->id,
            sourceTenantId: $sourceTenant->id,
            newData: [
                'email' => $federatedUser->global_email,
                'changed_at' => now()->toIso8601String(),
            ]
        );
    }

    /**
     * Log 2FA change.
     */
    public function logTwoFactorChanged(
        FederationGroup $group,
        FederatedUser $federatedUser,
        Tenant $sourceTenant,
        bool $enabled
    ): void {
        FederationSyncLog::logSuccess(
            groupId: $group->id,
            operation: FederationSyncLog::OP_TWO_FACTOR_CHANGED,
            federatedUserId: $federatedUser->id,
            sourceTenantId: $sourceTenant->id,
            newData: [
                'email' => $federatedUser->global_email,
                'two_factor_enabled' => $enabled,
            ]
        );
    }

    /**
     * Log conflict detection.
     */
    public function logConflictDetected(
        FederationGroup $group,
        FederatedUser $federatedUser,
        array $conflictingFields
    ): void {
        FederationSyncLog::logSuccess(
            groupId: $group->id,
            operation: FederationSyncLog::OP_CONFLICT_DETECTED,
            federatedUserId: $federatedUser->id,
            newData: [
                'email' => $federatedUser->global_email,
                'fields' => $conflictingFields,
            ]
        );
    }

    /**
     * Log conflict resolution.
     */
    public function logConflictResolved(
        FederationGroup $group,
        FederationConflict $conflict,
        string $resolverId
    ): void {
        FederationSyncLog::logSuccess(
            groupId: $group->id,
            operation: FederationSyncLog::OP_CONFLICT_RESOLVED,
            federatedUserId: $conflict->federated_user_id,
            newData: [
                'field' => $conflict->field,
                'resolution' => $conflict->resolution,
                'resolved_by' => $resolverId,
            ],
            actorId: $resolverId,
            actorType: FederationSyncLog::ACTOR_CENTRAL_USER
        );
    }

    /**
     * Log sync failure.
     */
    public function logSyncFailed(
        FederationGroup $group,
        ?FederatedUser $federatedUser,
        Tenant $sourceTenant,
        ?Tenant $targetTenant,
        string $errorMessage
    ): void {
        FederationSyncLog::logFailure(
            groupId: $group->id,
            operation: FederationSyncLog::OP_SYNC_FAILED,
            errorMessage: $errorMessage,
            federatedUserId: $federatedUser?->id,
            sourceTenantId: $sourceTenant->id,
            targetTenantId: $targetTenant?->id
        );
    }

    /**
     * Log sync retry.
     */
    public function logSyncRetry(
        FederationGroup $group,
        FederatedUser $federatedUser,
        Tenant $targetTenant,
        int $attemptNumber
    ): void {
        FederationSyncLog::logSuccess(
            groupId: $group->id,
            operation: FederationSyncLog::OP_SYNC_RETRY,
            federatedUserId: $federatedUser->id,
            targetTenantId: $targetTenant->id,
            newData: [
                'attempt' => $attemptNumber,
            ]
        );
    }

    /**
     * Log user sync to a specific tenant.
     */
    public function logUserSyncedToTenant(
        FederationGroup $group,
        FederatedUser $federatedUser,
        Tenant $sourceTenant,
        Tenant $targetTenant
    ): void {
        FederationSyncLog::logSuccess(
            groupId: $group->id,
            operation: FederationSyncLog::OP_USER_UPDATED,
            federatedUserId: $federatedUser->id,
            sourceTenantId: $sourceTenant->id,
            targetTenantId: $targetTenant->id,
            newData: [
                'email' => $federatedUser->global_email,
                'synced_to' => $targetTenant->name,
            ]
        );
    }
}
