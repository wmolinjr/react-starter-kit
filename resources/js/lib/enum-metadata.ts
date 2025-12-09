/**
 * Enum Metadata - Auto-generated from PHP Enums
 *
 * DO NOT EDIT MANUALLY!
 * Run: sail artisan enums:generate-types
 *
 * Contains the actual metadata (icon, color, label, etc.) for each enum value.
 */

import type {
    FederatedUserStatus,
    FederatedUserStatusOption,
    FederatedUserLinkSyncStatus,
    FederatedUserLinkSyncStatusOption,
    FederationConflictStatus,
    FederationConflictStatusOption,
    FederationSyncStrategy,
    FederationSyncStrategyOption,
} from '@/types/enums';
export const FEDERATED_USER_STATUS: Record<FederatedUserStatus, FederatedUserStatusOption> = {
    'active': { value: 'active', label: 'Active', description: 'User is active and synchronized across tenants', icon: 'CheckCircle', color: 'green', badge_variant: 'default', can_sync: true, is_pending: false },
    'suspended': { value: 'suspended', label: 'Suspended', description: 'User is suspended and not syncing', icon: 'XCircle', color: 'red', badge_variant: 'destructive', can_sync: false, is_pending: false },
    'pending_review': { value: 'pending_review', label: 'Pending Review', description: 'User has conflicts that need manual review', icon: 'AlertTriangle', color: 'yellow', badge_variant: 'secondary', can_sync: false, is_pending: true },
    'pending_master_sync': { value: 'pending_master_sync', label: 'Pending Master Sync', description: 'Awaiting creation in new master tenant after master change', icon: 'Clock', color: 'blue', badge_variant: 'outline', can_sync: true, is_pending: true },
};

export const FEDERATED_USER_LINK_SYNC_STATUS: Record<FederatedUserLinkSyncStatus, FederatedUserLinkSyncStatusOption> = {
    'synced': { value: 'synced', label: 'Synced', description: 'User data is synchronized with this tenant', icon: 'CheckCircle', color: 'green', badge_variant: 'default', needs_sync: false, has_issue: false },
    'pending_sync': { value: 'pending_sync', label: 'Pending Sync', description: 'User data needs to be synchronized', icon: 'Clock', color: 'blue', badge_variant: 'secondary', needs_sync: true, has_issue: false },
    'sync_failed': { value: 'sync_failed', label: 'Sync Failed', description: 'Last synchronization attempt failed', icon: 'AlertTriangle', color: 'red', badge_variant: 'destructive', needs_sync: true, has_issue: true },
    'conflict': { value: 'conflict', label: 'Conflict', description: 'Data conflict detected, requires resolution', icon: 'AlertOctagon', color: 'yellow', badge_variant: 'secondary', needs_sync: false, has_issue: true },
    'disabled': { value: 'disabled', label: 'Disabled', description: 'Synchronization is disabled for this link', icon: 'XCircle', color: 'gray', badge_variant: 'outline', needs_sync: false, has_issue: false },
};

export const FEDERATION_CONFLICT_STATUS: Record<FederationConflictStatus, FederationConflictStatusOption> = {
    'pending': { value: 'pending', label: 'Pending', description: 'Conflict needs to be reviewed and resolved', icon: 'AlertTriangle', color: 'yellow', badge_variant: 'secondary', requires_action: true, is_terminal: false },
    'resolved': { value: 'resolved', label: 'Resolved', description: 'Conflict was resolved with a chosen value', icon: 'CheckCircle', color: 'green', badge_variant: 'default', requires_action: false, is_terminal: true },
    'dismissed': { value: 'dismissed', label: 'Dismissed', description: 'Conflict was dismissed without resolution', icon: 'XCircle', color: 'gray', badge_variant: 'outline', requires_action: false, is_terminal: true },
};

export const FEDERATION_SYNC_STRATEGY: Record<FederationSyncStrategy, FederationSyncStrategyOption> = {
    'master_wins': { value: 'master_wins', label: 'Master Wins', description: 'Master tenant data always takes precedence in conflicts', icon: 'Crown', color: 'yellow', creates_conflicts: false, auto_resolves: true },
    'last_write_wins': { value: 'last_write_wins', label: 'Last Write Wins', description: 'Most recent change wins in case of conflicts', icon: 'Clock', color: 'blue', creates_conflicts: false, auto_resolves: true },
    'manual_review': { value: 'manual_review', label: 'Manual Review', description: 'Conflicts are stored for manual resolution', icon: 'UserCheck', color: 'purple', creates_conflicts: true, auto_resolves: false },
};


/**
 * Get metadata for a FederatedUserStatus value.
 */
export function getFederatedUserStatusMeta(status: FederatedUserStatus): FederatedUserStatusOption {
    return FEDERATED_USER_STATUS[status];
}

/**
 * Get metadata for a FederatedUserLinkSyncStatus value.
 */
export function getFederatedUserLinkSyncStatusMeta(status: FederatedUserLinkSyncStatus): FederatedUserLinkSyncStatusOption {
    return FEDERATED_USER_LINK_SYNC_STATUS[status];
}

/**
 * Get metadata for a FederationConflictStatus value.
 */
export function getFederationConflictStatusMeta(status: FederationConflictStatus): FederationConflictStatusOption {
    return FEDERATION_CONFLICT_STATUS[status];
}

/**
 * Get metadata for a FederationSyncStrategy value.
 */
export function getFederationSyncStrategyMeta(status: FederationSyncStrategy): FederationSyncStrategyOption {
    return FEDERATION_SYNC_STRATEGY[status];
}