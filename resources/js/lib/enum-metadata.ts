/**
 * Enum Metadata - Auto-generated from PHP Enums
 *
 * DO NOT EDIT MANUALLY!
 * Run: sail artisan enums:generate-types
 *
 * Contains the actual metadata (icon, color, label, etc.) for each enum value.
 */

import type {
    AddonType,
    AddonTypeOption,
    AddonStatus,
    AddonStatusOption,
    BillingPeriod,
    BillingPeriodOption,
    PlanFeature,
    PlanFeatureOption,
    PlanLimit,
    PlanLimitOption,
    TenantRole,
    TenantRoleOption,
    FederatedUserStatus,
    FederatedUserStatusOption,
    FederatedUserLinkSyncStatus,
    FederatedUserLinkSyncStatusOption,
    FederationConflictStatus,
    FederationConflictStatusOption,
    FederationSyncStrategy,
    FederationSyncStrategyOption,
} from '@/types/enums';
export const ADDON_TYPE: Record<AddonType, AddonTypeOption> = {
    'quota': { value: 'quota', label: 'Quota Increase', description: 'Increase your plan limits (storage, users, etc.)', icon: 'TrendingUp', color: 'blue', badge_variant: 'default', category: 'limits', unit_label: 'units', is_metered: false, is_stackable: true, is_recurring: true, is_one_time: false, has_validity: false },
    'feature': { value: 'feature', label: 'Feature', description: 'Unlock additional features', icon: 'Sparkles', color: 'purple', badge_variant: 'secondary', category: 'features', unit_label: 'feature', is_metered: false, is_stackable: false, is_recurring: true, is_one_time: false, has_validity: false },
    'metered': { value: 'metered', label: 'Usage-Based', description: 'Pay only for what you use', icon: 'Activity', color: 'orange', badge_variant: 'outline', category: 'usage', unit_label: 'units', is_metered: true, is_stackable: true, is_recurring: false, is_one_time: false, has_validity: false },
    'credit': { value: 'credit', label: 'Credit Pack', description: 'One-time purchase with validity period', icon: 'CreditCard', color: 'green', badge_variant: 'default', category: 'credits', unit_label: 'credits', is_metered: false, is_stackable: true, is_recurring: false, is_one_time: true, has_validity: true },
};

export const ADDON_STATUS: Record<AddonStatus, AddonStatusOption> = {
    'pending': { value: 'pending', label: 'Pending', description: 'Awaiting activation', icon: 'Clock', color: 'yellow', badge_variant: 'secondary', is_usable: false, is_terminal: false },
    'active': { value: 'active', label: 'Active', description: 'Currently active and in use', icon: 'CheckCircle', color: 'green', badge_variant: 'default', is_usable: true, is_terminal: false },
    'canceled': { value: 'canceled', label: 'Canceled', description: 'Subscription was canceled', icon: 'XCircle', color: 'gray', badge_variant: 'outline', is_usable: false, is_terminal: true },
    'expired': { value: 'expired', label: 'Expired', description: 'Subscription period ended', icon: 'CalendarX', color: 'orange', badge_variant: 'outline', is_usable: false, is_terminal: true },
    'failed': { value: 'failed', label: 'Failed', description: 'Payment or activation failed', icon: 'AlertTriangle', color: 'red', badge_variant: 'destructive', is_usable: false, is_terminal: true },
};

export const BILLING_PERIOD: Record<BillingPeriod, BillingPeriodOption> = {
    'monthly': { value: 'monthly', label: 'Monthly', description: 'Billed monthly', icon: 'Calendar', color: 'blue', badge_variant: 'default', is_recurring: true },
    'yearly': { value: 'yearly', label: 'Yearly', description: 'Billed annually with discount', icon: 'CalendarRange', color: 'green', badge_variant: 'default', is_recurring: true },
    'one_time': { value: 'one_time', label: 'One-time', description: 'One-time payment, no recurring charges', icon: 'CreditCard', color: 'purple', badge_variant: 'secondary', is_recurring: false },
    'metered': { value: 'metered', label: 'Metered', description: 'Usage-based billing', icon: 'Gauge', color: 'orange', badge_variant: 'outline', is_recurring: false },
    'manual': { value: 'manual', label: 'Manual', description: 'Manually managed billing', icon: 'HandCoins', color: 'gray', badge_variant: 'outline', is_recurring: false },
};

export const PLAN_FEATURE: Record<PlanFeature, PlanFeatureOption> = {
    'base': { value: 'base', label: 'Base Features', description: 'Core team and settings management features', icon: 'Settings', color: 'gray', badge_variant: 'outline', category: 'other', permissions: ['team:view', 'team:invite', 'team:remove', 'team:manageRoles', 'team:activity', 'settings:view', 'settings:edit', 'settings:danger', 'billing:view', 'billing:manage', 'billing:invoices'], is_customizable: false },
    'projects': { value: 'projects', label: 'Projects', description: 'Create and manage projects with your team', icon: 'Folder', color: 'blue', badge_variant: 'default', category: 'modules', permissions: ['projects:view', 'projects:create', 'projects:edit', 'projects:editOwn', 'projects:delete', 'projects:upload', 'projects:download', 'projects:archive'], is_customizable: true },
    'customRoles': { value: 'customRoles', label: 'Custom Roles', description: 'Create and manage custom roles with granular permissions', icon: 'Shield', color: 'red', badge_variant: 'destructive', category: 'security', permissions: ['roles:view', 'roles:create', 'roles:edit', 'roles:delete'], is_customizable: true },
    'apiAccess': { value: 'apiAccess', label: 'API Access', description: 'Generate API tokens for external integrations', icon: 'Key', color: 'purple', badge_variant: 'secondary', category: 'integration', permissions: ['apiTokens:view', 'apiTokens:create', 'apiTokens:delete'], is_customizable: true },
    'advancedReports': { value: 'advancedReports', label: 'Advanced Reports', description: 'Access advanced analytics and custom report builder', icon: 'BarChart3', color: 'orange', badge_variant: 'outline', category: 'analytics', permissions: ['reports:view', 'reports:export', 'reports:schedule', 'reports:customize'], is_customizable: true },
    'sso': { value: 'sso', label: 'Single Sign-On (SSO)', description: 'Enable SAML/OIDC authentication for enterprise security', icon: 'KeyRound', color: 'red', badge_variant: 'destructive', category: 'security', permissions: ['sso:configure', 'sso:manage', 'sso:testConnection'], is_customizable: false },
    'whiteLabel': { value: 'whiteLabel', label: 'White Label', description: 'Customize branding, colors, and remove platform branding', icon: 'Palette', color: 'pink', badge_variant: 'outline', category: 'customization', permissions: ['branding:view', 'branding:edit', 'branding:preview', 'branding:publish'], is_customizable: false },
    'auditLog': { value: 'auditLog', label: 'Audit Log', description: 'Track all user actions and system events', icon: 'FileText', color: 'red', badge_variant: 'destructive', category: 'security', permissions: ['audit:view', 'audit:export'], is_customizable: true },
    'prioritySupport': { value: 'prioritySupport', label: 'Priority Support', description: '24/7 priority support with dedicated account manager', icon: 'Headphones', color: 'green', badge_variant: 'outline', category: 'support', permissions: [], is_customizable: true },
    'multiLanguage': { value: 'multiLanguage', label: 'Multi-Language', description: 'Enable multiple language support for your users', icon: 'Globe', color: 'pink', badge_variant: 'outline', category: 'customization', permissions: ['locales:view', 'locales:manage'], is_customizable: true },
    'federation': { value: 'federation', label: 'User Federation', description: 'Sync users across multiple tenants in a federation group', icon: 'Network', color: 'red', badge_variant: 'destructive', category: 'security', permissions: ['federation:view', 'federation:manage', 'federation:invite', 'federation:leave'], is_customizable: true },
};

export const PLAN_LIMIT: Record<PlanLimit, PlanLimitOption> = {
    'users': { value: 'users', label: 'User Seats', description: 'Maximum number of team members', icon: 'Users', color: 'blue', badge_variant: 'default', unit: 'seats', unit_label: 'users', default_value: 1, allows_unlimited: true, is_customizable: true },
    'projects': { value: 'projects', label: 'Projects', description: 'Maximum number of active projects', icon: 'Folder', color: 'green', badge_variant: 'default', unit: 'projects', unit_label: 'projects', default_value: 10, allows_unlimited: true, is_customizable: true },
    'storage': { value: 'storage', label: 'Storage', description: 'Total storage space available', icon: 'HardDrive', color: 'purple', badge_variant: 'secondary', unit: 'MB', unit_label: 'MB', default_value: 1024, allows_unlimited: true, is_customizable: true },
    'apiCalls': { value: 'apiCalls', label: 'API Calls', description: 'Monthly API request limit', icon: 'Activity', color: 'orange', badge_variant: 'secondary', unit: 'requests', unit_label: 'calls/month', default_value: 0, allows_unlimited: true, is_customizable: true },
    'logRetention': { value: 'logRetention', label: 'Log Retention', description: 'How long activity logs are kept', icon: 'Calendar', color: 'gray', badge_variant: 'outline', unit: 'days', unit_label: 'days', default_value: 30, allows_unlimited: false, is_customizable: false },
    'fileUploadSize': { value: 'fileUploadSize', label: 'Max File Size', description: 'Maximum size per file upload', icon: 'Upload', color: 'cyan', badge_variant: 'outline', unit: 'MB', unit_label: 'MB', default_value: 10, allows_unlimited: false, is_customizable: false },
    'customRoles': { value: 'customRoles', label: 'Custom Roles', description: 'Maximum number of custom roles that can be created', icon: 'Shield', color: 'red', badge_variant: 'outline', unit: 'roles', unit_label: 'roles', default_value: 0, allows_unlimited: true, is_customizable: true },
    'locales': { value: 'locales', label: 'Languages', description: 'Maximum number of languages that can be enabled', icon: 'Globe', color: 'pink', badge_variant: 'outline', unit: 'locales', unit_label: 'languages', default_value: 1, allows_unlimited: true, is_customizable: true },
};

export const TENANT_ROLE: Record<TenantRole, TenantRoleOption> = {
    'owner': { value: 'owner', label: 'Owner', description: 'Full access to all features including billing and API tokens', icon: 'Crown', color: 'yellow', badge_variant: 'default', is_system: true },
    'admin': { value: 'admin', label: 'Administrator', description: 'Manages team and projects, no access to billing or API tokens', icon: 'ShieldCheck', color: 'blue', badge_variant: 'secondary', is_system: true },
    'member': { value: 'member', label: 'Member', description: 'View access and can edit own projects', icon: 'User', color: 'gray', badge_variant: 'outline', is_system: true },
};

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
    'master_wins': { value: 'master_wins', label: 'Master Wins', description: 'Master tenant data always takes precedence in conflicts', icon: 'Crown', color: 'yellow', badge_variant: 'default', creates_conflicts: false, auto_resolves: true },
    'last_write_wins': { value: 'last_write_wins', label: 'Last Write Wins', description: 'Most recent change wins in case of conflicts', icon: 'Clock', color: 'blue', badge_variant: 'secondary', creates_conflicts: false, auto_resolves: true },
    'manual_review': { value: 'manual_review', label: 'Manual Review', description: 'Conflicts are stored for manual resolution', icon: 'UserCheck', color: 'purple', badge_variant: 'outline', creates_conflicts: true, auto_resolves: false },
};


/**
 * Get metadata for an AddonType value.
 */
export function getAddonTypeMeta(type: AddonType): AddonTypeOption {
    return ADDON_TYPE[type];
}

/**
 * Get metadata for an AddonStatus value.
 */
export function getAddonStatusMeta(status: AddonStatus): AddonStatusOption {
    return ADDON_STATUS[status];
}

/**
 * Get metadata for a BillingPeriod value.
 */
export function getBillingPeriodMeta(period: BillingPeriod): BillingPeriodOption {
    return BILLING_PERIOD[period];
}

/**
 * Get metadata for a PlanFeature value.
 */
export function getPlanFeatureMeta(feature: PlanFeature): PlanFeatureOption {
    return PLAN_FEATURE[feature];
}

/**
 * Get metadata for a PlanLimit value.
 */
export function getPlanLimitMeta(limit: PlanLimit): PlanLimitOption {
    return PLAN_LIMIT[limit];
}

/**
 * Get metadata for a TenantRole value.
 */
export function getTenantRoleMeta(role: TenantRole): TenantRoleOption {
    return TENANT_ROLE[role];
}

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
export function getFederationSyncStrategyMeta(strategy: FederationSyncStrategy): FederationSyncStrategyOption {
    return FEDERATION_SYNC_STRATEGY[strategy];
}