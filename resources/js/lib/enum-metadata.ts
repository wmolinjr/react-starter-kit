/**
 * Enum Metadata - Auto-generated from PHP Enums
 *
 * DO NOT EDIT MANUALLY!
 * Run: sail artisan types:generate
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
    CentralPermission,
    CentralPermissionOption,
    TenantPermission,
    TenantPermissionOption,
    PermissionCategory,
    PermissionCategoryOption,
    PermissionAction,
    PermissionActionOption,
    BadgePreset,
    BadgePresetOption,
    TenantConfigKey,
    TenantConfigKeyOption,
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

export const CENTRAL_PERMISSION: Record<CentralPermission, CentralPermissionOption> = {
    'tenants:view': { value: 'tenants:view', label: 'Tenants: View', description: 'View all tenants', icon: 'Building2', color: 'blue', badge_variant: 'default', category: 'tenants', action: 'view' },
    'tenants:show': { value: 'tenants:show', label: 'Tenants: Show Details', description: 'View tenant details', icon: 'Building2', color: 'blue', badge_variant: 'default', category: 'tenants', action: 'show' },
    'tenants:edit': { value: 'tenants:edit', label: 'Tenants: Edit', description: 'Edit tenant settings', icon: 'Building2', color: 'blue', badge_variant: 'default', category: 'tenants', action: 'edit' },
    'tenants:delete': { value: 'tenants:delete', label: 'Tenants: Delete', description: 'Delete tenants', icon: 'Building2', color: 'blue', badge_variant: 'default', category: 'tenants', action: 'delete' },
    'tenants:impersonate': { value: 'tenants:impersonate', label: 'Tenants: Impersonate', description: 'Impersonate tenant users', icon: 'Building2', color: 'blue', badge_variant: 'default', category: 'tenants', action: 'impersonate' },
    'users:view': { value: 'users:view', label: 'Users: View', description: 'View all users', icon: 'Users', color: 'purple', badge_variant: 'default', category: 'users', action: 'view' },
    'users:show': { value: 'users:show', label: 'Users: Show Details', description: 'View user details', icon: 'Users', color: 'purple', badge_variant: 'default', category: 'users', action: 'show' },
    'users:edit': { value: 'users:edit', label: 'Users: Edit', description: 'Edit user details', icon: 'Users', color: 'purple', badge_variant: 'default', category: 'users', action: 'edit' },
    'users:delete': { value: 'users:delete', label: 'Users: Delete', description: 'Delete users', icon: 'Users', color: 'purple', badge_variant: 'default', category: 'users', action: 'delete' },
    'plans:view': { value: 'plans:view', label: 'Plans: View', description: 'View all plans', icon: 'CreditCard', color: 'green', badge_variant: 'secondary', category: 'plans', action: 'view' },
    'plans:create': { value: 'plans:create', label: 'Plans: Create', description: 'Create new plans', icon: 'CreditCard', color: 'green', badge_variant: 'secondary', category: 'plans', action: 'create' },
    'plans:edit': { value: 'plans:edit', label: 'Plans: Edit', description: 'Edit plans', icon: 'CreditCard', color: 'green', badge_variant: 'secondary', category: 'plans', action: 'edit' },
    'plans:delete': { value: 'plans:delete', label: 'Plans: Delete', description: 'Delete plans', icon: 'CreditCard', color: 'green', badge_variant: 'secondary', category: 'plans', action: 'delete' },
    'plans:sync': { value: 'plans:sync', label: 'Plans: Sync', description: 'Sync plans with Stripe', icon: 'CreditCard', color: 'green', badge_variant: 'secondary', category: 'plans', action: 'sync' },
    'catalog:view': { value: 'catalog:view', label: 'Catalog: View', description: 'View addon catalog', icon: 'Package', color: 'orange', badge_variant: 'secondary', category: 'catalog', action: 'view' },
    'catalog:create': { value: 'catalog:create', label: 'Catalog: Create', description: 'Create new addons', icon: 'Package', color: 'orange', badge_variant: 'secondary', category: 'catalog', action: 'create' },
    'catalog:edit': { value: 'catalog:edit', label: 'Catalog: Edit', description: 'Edit addons', icon: 'Package', color: 'orange', badge_variant: 'secondary', category: 'catalog', action: 'edit' },
    'catalog:delete': { value: 'catalog:delete', label: 'Catalog: Delete', description: 'Delete addons', icon: 'Package', color: 'orange', badge_variant: 'secondary', category: 'catalog', action: 'delete' },
    'catalog:sync': { value: 'catalog:sync', label: 'Catalog: Sync', description: 'Sync addons with Stripe', icon: 'Package', color: 'orange', badge_variant: 'secondary', category: 'catalog', action: 'sync' },
    'addons:view': { value: 'addons:view', label: 'Addons: View', description: 'View tenant addons', icon: 'Puzzle', color: 'pink', badge_variant: 'secondary', category: 'addons', action: 'view' },
    'addons:revenue': { value: 'addons:revenue', label: 'Addons: Revenue', description: 'View addon revenue reports', icon: 'Puzzle', color: 'pink', badge_variant: 'secondary', category: 'addons', action: 'revenue' },
    'addons:grant': { value: 'addons:grant', label: 'Addons: Grant', description: 'Grant addons to tenants', icon: 'Puzzle', color: 'pink', badge_variant: 'secondary', category: 'addons', action: 'grant' },
    'addons:revoke': { value: 'addons:revoke', label: 'Addons: Revoke', description: 'Revoke addons from tenants', icon: 'Puzzle', color: 'pink', badge_variant: 'secondary', category: 'addons', action: 'revoke' },
    'roles:view': { value: 'roles:view', label: 'Roles: View', description: 'View central roles', icon: 'Shield', color: 'yellow', badge_variant: 'outline', category: 'roles', action: 'view' },
    'roles:create': { value: 'roles:create', label: 'Roles: Create', description: 'Create central roles', icon: 'Shield', color: 'yellow', badge_variant: 'outline', category: 'roles', action: 'create' },
    'roles:edit': { value: 'roles:edit', label: 'Roles: Edit', description: 'Edit central roles', icon: 'Shield', color: 'yellow', badge_variant: 'outline', category: 'roles', action: 'edit' },
    'roles:delete': { value: 'roles:delete', label: 'Roles: Delete', description: 'Delete central roles', icon: 'Shield', color: 'yellow', badge_variant: 'outline', category: 'roles', action: 'delete' },
    'system:view': { value: 'system:view', label: 'System: View', description: 'View system settings', icon: 'Settings', color: 'gray', badge_variant: 'outline', category: 'system', action: 'view' },
    'system:edit': { value: 'system:edit', label: 'System: Edit', description: 'Edit system settings', icon: 'Settings', color: 'gray', badge_variant: 'outline', category: 'system', action: 'edit' },
    'system:logs': { value: 'system:logs', label: 'System: Logs', description: 'View system logs', icon: 'Settings', color: 'gray', badge_variant: 'outline', category: 'system', action: 'logs' },
    'payment:view': { value: 'payment:view', label: 'Payment: View', description: 'View payment gateway settings', icon: 'Wallet', color: 'emerald', badge_variant: 'default', category: 'payment', action: 'view' },
    'payment:manage': { value: 'payment:manage', label: 'Payment: Manage', description: 'Manage payment gateway credentials', icon: 'Wallet', color: 'emerald', badge_variant: 'default', category: 'payment', action: 'manage' },
    'federation:view': { value: 'federation:view', label: 'Federation: View', description: 'View federation groups', icon: 'Network', color: 'cyan', badge_variant: 'default', category: 'federation', action: 'view' },
    'federation:create': { value: 'federation:create', label: 'Federation: Create', description: 'Create federation groups', icon: 'Network', color: 'cyan', badge_variant: 'default', category: 'federation', action: 'create' },
    'federation:edit': { value: 'federation:edit', label: 'Federation: Edit', description: 'Edit federation groups', icon: 'Network', color: 'cyan', badge_variant: 'default', category: 'federation', action: 'edit' },
    'federation:delete': { value: 'federation:delete', label: 'Federation: Delete', description: 'Delete federation groups', icon: 'Network', color: 'cyan', badge_variant: 'default', category: 'federation', action: 'delete' },
    'federation:manageConflicts': { value: 'federation:manageConflicts', label: 'Federation: Manage Conflicts', description: 'Manage federation conflicts', icon: 'Network', color: 'cyan', badge_variant: 'default', category: 'federation', action: 'manageConflicts' },
    'audit:view': { value: 'audit:view', label: 'Audit: View', description: 'View audit logs', icon: 'ClipboardList', color: 'amber', badge_variant: 'default', category: 'audit', action: 'view' },
    'audit:export': { value: 'audit:export', label: 'Audit: Export', description: 'Export audit logs', icon: 'ClipboardList', color: 'amber', badge_variant: 'default', category: 'audit', action: 'export' },
};

export const TENANT_PERMISSION: Record<TenantPermission, TenantPermissionOption> = {
    'projects:view': { value: 'projects:view', label: 'Projects: View', description: 'View all projects', icon: 'Folder', color: 'blue', badge_variant: 'default', category: 'projects', action: 'view' },
    'projects:create': { value: 'projects:create', label: 'Projects: Create', description: 'Create new projects', icon: 'Folder', color: 'blue', badge_variant: 'default', category: 'projects', action: 'create' },
    'projects:edit': { value: 'projects:edit', label: 'Projects: Edit', description: 'Edit any project', icon: 'Folder', color: 'blue', badge_variant: 'default', category: 'projects', action: 'edit' },
    'projects:editOwn': { value: 'projects:editOwn', label: 'Projects: Edit Own', description: 'Edit own projects only', icon: 'Folder', color: 'blue', badge_variant: 'default', category: 'projects', action: 'editOwn' },
    'projects:delete': { value: 'projects:delete', label: 'Projects: Delete', description: 'Delete projects', icon: 'Folder', color: 'blue', badge_variant: 'default', category: 'projects', action: 'delete' },
    'projects:upload': { value: 'projects:upload', label: 'Projects: Upload', description: 'Upload files', icon: 'Folder', color: 'blue', badge_variant: 'default', category: 'projects', action: 'upload' },
    'projects:download': { value: 'projects:download', label: 'Projects: Download', description: 'Download files', icon: 'Folder', color: 'blue', badge_variant: 'default', category: 'projects', action: 'download' },
    'projects:archive': { value: 'projects:archive', label: 'Projects: Archive', description: 'Archive projects', icon: 'Folder', color: 'blue', badge_variant: 'default', category: 'projects', action: 'archive' },
    'team:view': { value: 'team:view', label: 'Team: View', description: 'View team members', icon: 'Users', color: 'purple', badge_variant: 'default', category: 'team', action: 'view' },
    'team:invite': { value: 'team:invite', label: 'Team: Invite', description: 'Invite members', icon: 'Users', color: 'purple', badge_variant: 'default', category: 'team', action: 'invite' },
    'team:remove': { value: 'team:remove', label: 'Team: Remove', description: 'Remove members', icon: 'Users', color: 'purple', badge_variant: 'default', category: 'team', action: 'remove' },
    'team:manageRoles': { value: 'team:manageRoles', label: 'Team: Manage Roles', description: 'Manage roles', icon: 'Users', color: 'purple', badge_variant: 'default', category: 'team', action: 'manageRoles' },
    'team:activity': { value: 'team:activity', label: 'Team: Activity', description: 'View activity logs', icon: 'Users', color: 'purple', badge_variant: 'default', category: 'team', action: 'activity' },
    'settings:view': { value: 'settings:view', label: 'Settings: View', description: 'View settings', icon: 'Settings', color: 'gray', badge_variant: 'outline', category: 'settings', action: 'view' },
    'settings:edit': { value: 'settings:edit', label: 'Settings: Edit', description: 'Edit settings', icon: 'Settings', color: 'gray', badge_variant: 'outline', category: 'settings', action: 'edit' },
    'settings:danger': { value: 'settings:danger', label: 'Settings: Danger Zone', description: 'Danger zone access', icon: 'Settings', color: 'gray', badge_variant: 'outline', category: 'settings', action: 'danger' },
    'billing:view': { value: 'billing:view', label: 'Billing: View', description: 'View billing', icon: 'CreditCard', color: 'green', badge_variant: 'secondary', category: 'billing', action: 'view' },
    'billing:manage': { value: 'billing:manage', label: 'Billing: Manage', description: 'Manage subscriptions', icon: 'CreditCard', color: 'green', badge_variant: 'secondary', category: 'billing', action: 'manage' },
    'billing:invoices': { value: 'billing:invoices', label: 'Billing: Invoices', description: 'Download invoices', icon: 'CreditCard', color: 'green', badge_variant: 'secondary', category: 'billing', action: 'invoices' },
    'apiTokens:view': { value: 'apiTokens:view', label: 'API Tokens: View', description: 'View API tokens', icon: 'Key', color: 'orange', badge_variant: 'secondary', category: 'apiTokens', action: 'view' },
    'apiTokens:create': { value: 'apiTokens:create', label: 'API Tokens: Create', description: 'Create API tokens', icon: 'Key', color: 'orange', badge_variant: 'secondary', category: 'apiTokens', action: 'create' },
    'apiTokens:delete': { value: 'apiTokens:delete', label: 'API Tokens: Delete', description: 'Delete API tokens', icon: 'Key', color: 'orange', badge_variant: 'secondary', category: 'apiTokens', action: 'delete' },
    'roles:view': { value: 'roles:view', label: 'Custom Roles: View', description: 'View custom roles', icon: 'Shield', color: 'yellow', badge_variant: 'destructive', category: 'roles', action: 'view' },
    'roles:create': { value: 'roles:create', label: 'Custom Roles: Create', description: 'Create custom roles', icon: 'Shield', color: 'yellow', badge_variant: 'destructive', category: 'roles', action: 'create' },
    'roles:edit': { value: 'roles:edit', label: 'Custom Roles: Edit', description: 'Edit custom roles', icon: 'Shield', color: 'yellow', badge_variant: 'destructive', category: 'roles', action: 'edit' },
    'roles:delete': { value: 'roles:delete', label: 'Custom Roles: Delete', description: 'Delete custom roles', icon: 'Shield', color: 'yellow', badge_variant: 'destructive', category: 'roles', action: 'delete' },
    'reports:view': { value: 'reports:view', label: 'Reports: View', description: 'View reports', icon: 'BarChart3', color: 'cyan', badge_variant: 'outline', category: 'reports', action: 'view' },
    'reports:export': { value: 'reports:export', label: 'Reports: Export', description: 'Export reports', icon: 'BarChart3', color: 'cyan', badge_variant: 'outline', category: 'reports', action: 'export' },
    'reports:schedule': { value: 'reports:schedule', label: 'Reports: Schedule', description: 'Schedule reports', icon: 'BarChart3', color: 'cyan', badge_variant: 'outline', category: 'reports', action: 'schedule' },
    'reports:customize': { value: 'reports:customize', label: 'Reports: Customize', description: 'Customize reports', icon: 'BarChart3', color: 'cyan', badge_variant: 'outline', category: 'reports', action: 'customize' },
    'sso:configure': { value: 'sso:configure', label: 'Single Sign-On: Configure', description: 'Configure SSO', icon: 'Lock', color: 'red', badge_variant: 'destructive', category: 'sso', action: 'configure' },
    'sso:manage': { value: 'sso:manage', label: 'Single Sign-On: Manage', description: 'Manage SSO providers', icon: 'Lock', color: 'red', badge_variant: 'destructive', category: 'sso', action: 'manage' },
    'sso:testConnection': { value: 'sso:testConnection', label: 'Single Sign-On: Test Connection', description: 'Test SSO connection', icon: 'Lock', color: 'red', badge_variant: 'destructive', category: 'sso', action: 'testConnection' },
    'branding:view': { value: 'branding:view', label: 'Branding: View', description: 'View branding', icon: 'Palette', color: 'pink', badge_variant: 'outline', category: 'branding', action: 'view' },
    'branding:edit': { value: 'branding:edit', label: 'Branding: Edit', description: 'Edit branding', icon: 'Palette', color: 'pink', badge_variant: 'outline', category: 'branding', action: 'edit' },
    'branding:preview': { value: 'branding:preview', label: 'Branding: Preview', description: 'Preview branding', icon: 'Palette', color: 'pink', badge_variant: 'outline', category: 'branding', action: 'preview' },
    'branding:publish': { value: 'branding:publish', label: 'Branding: Publish', description: 'Publish branding', icon: 'Palette', color: 'pink', badge_variant: 'outline', category: 'branding', action: 'publish' },
    'audit:view': { value: 'audit:view', label: 'Audit Log: View', description: 'View audit logs', icon: 'FileText', color: 'gray', badge_variant: 'outline', category: 'audit', action: 'view' },
    'audit:export': { value: 'audit:export', label: 'Audit Log: Export', description: 'Export audit logs', icon: 'FileText', color: 'gray', badge_variant: 'outline', category: 'audit', action: 'export' },
    'locales:view': { value: 'locales:view', label: 'Languages: View', description: 'View language settings', icon: 'Globe', color: 'blue', badge_variant: 'outline', category: 'locales', action: 'view' },
    'locales:manage': { value: 'locales:manage', label: 'Languages: Manage', description: 'Manage language settings', icon: 'Globe', color: 'blue', badge_variant: 'outline', category: 'locales', action: 'manage' },
    'federation:view': { value: 'federation:view', label: 'Federation: View', description: 'View federation settings', icon: 'Network', color: 'cyan', badge_variant: 'default', category: 'federation', action: 'view' },
    'federation:manage': { value: 'federation:manage', label: 'Federation: Manage', description: 'Manage federation settings', icon: 'Network', color: 'cyan', badge_variant: 'default', category: 'federation', action: 'manage' },
    'federation:invite': { value: 'federation:invite', label: 'Federation: Invite', description: 'Invite tenants to federation', icon: 'Network', color: 'cyan', badge_variant: 'default', category: 'federation', action: 'invite' },
    'federation:leave': { value: 'federation:leave', label: 'Federation: Leave', description: 'Leave federation group', icon: 'Network', color: 'cyan', badge_variant: 'default', category: 'federation', action: 'leave' },
};

export const PERMISSION_CATEGORY: Record<PermissionCategory, PermissionCategoryOption> = {
    'projects': { value: 'projects', label: 'Projects', icon: 'Folder', color: 'blue', badge_variant: 'default' },
    'team': { value: 'team', label: 'Team', icon: 'Users', color: 'purple', badge_variant: 'default' },
    'settings': { value: 'settings', label: 'Settings', icon: 'Settings', color: 'gray', badge_variant: 'outline' },
    'billing': { value: 'billing', label: 'Billing', icon: 'CreditCard', color: 'green', badge_variant: 'secondary' },
    'apiTokens': { value: 'apiTokens', label: 'API Tokens', icon: 'Key', color: 'orange', badge_variant: 'secondary' },
    'roles': { value: 'roles', label: 'Custom Roles', icon: 'Shield', color: 'yellow', badge_variant: 'destructive' },
    'reports': { value: 'reports', label: 'Reports', icon: 'BarChart3', color: 'cyan', badge_variant: 'outline' },
    'sso': { value: 'sso', label: 'Single Sign-On', icon: 'Lock', color: 'red', badge_variant: 'destructive' },
    'branding': { value: 'branding', label: 'Branding', icon: 'Palette', color: 'pink', badge_variant: 'outline' },
    'audit': { value: 'audit', label: 'Audit Log', icon: 'FileText', color: 'gray', badge_variant: 'outline' },
    'locales': { value: 'locales', label: 'Languages', icon: 'Globe', color: 'blue', badge_variant: 'outline' },
    'federation': { value: 'federation', label: 'Federation', icon: 'Network', color: 'cyan', badge_variant: 'default' },
};

export const PERMISSION_ACTION: Record<PermissionAction, PermissionActionOption> = {
    'view': { value: 'view', label: 'View' },
    'create': { value: 'create', label: 'Create' },
    'edit': { value: 'edit', label: 'Edit' },
    'editOwn': { value: 'editOwn', label: 'Edit Own' },
    'delete': { value: 'delete', label: 'Delete' },
    'upload': { value: 'upload', label: 'Upload' },
    'download': { value: 'download', label: 'Download' },
    'archive': { value: 'archive', label: 'Archive' },
    'invite': { value: 'invite', label: 'Invite' },
    'remove': { value: 'remove', label: 'Remove' },
    'manageRoles': { value: 'manageRoles', label: 'Manage Roles' },
    'activity': { value: 'activity', label: 'Activity' },
    'danger': { value: 'danger', label: 'Danger Zone' },
    'manage': { value: 'manage', label: 'Manage' },
    'invoices': { value: 'invoices', label: 'Invoices' },
    'export': { value: 'export', label: 'Export' },
    'schedule': { value: 'schedule', label: 'Schedule' },
    'customize': { value: 'customize', label: 'Customize' },
    'configure': { value: 'configure', label: 'Configure' },
    'testConnection': { value: 'testConnection', label: 'Test Connection' },
    'preview': { value: 'preview', label: 'Preview' },
    'publish': { value: 'publish', label: 'Publish' },
    'leave': { value: 'leave', label: 'Leave' },
};

export const BADGE_PRESET: Record<BadgePreset, BadgePresetOption> = {
    'most_popular': { value: 'most_popular', label: 'Most Popular', description: 'Our most popular choice', icon: 'Star', color: 'amber', badge_variant: 'default', bg: 'bg-amber-100 dark:bg-amber-900/30', text: 'text-amber-700 dark:text-amber-300', border: 'border-amber-300 dark:border-amber-700' },
    'best_value': { value: 'best_value', label: 'Best Value', description: 'Best value for money', icon: 'Trophy', color: 'green', badge_variant: 'outline', bg: 'bg-green-100 dark:bg-green-900/30', text: 'text-green-700 dark:text-green-300', border: 'border-green-300 dark:border-green-700' },
    'best_for_teams': { value: 'best_for_teams', label: 'Best for Teams', description: 'Ideal for team collaboration', icon: 'Users', color: 'blue', badge_variant: 'outline', bg: 'bg-blue-100 dark:bg-blue-900/30', text: 'text-blue-700 dark:text-blue-300', border: 'border-blue-300 dark:border-blue-700' },
    'enterprise': { value: 'enterprise', label: 'Enterprise', description: 'For large organizations', icon: 'Building2', color: 'purple', badge_variant: 'outline', bg: 'bg-purple-100 dark:bg-purple-900/30', text: 'text-purple-700 dark:text-purple-300', border: 'border-purple-300 dark:border-purple-700' },
    'one_time': { value: 'one_time', label: 'One Time', description: 'Pay once, use forever', icon: 'Coins', color: 'teal', badge_variant: 'outline', bg: 'bg-teal-100 dark:bg-teal-900/30', text: 'text-teal-700 dark:text-teal-300', border: 'border-teal-300 dark:border-teal-700' },
    'new': { value: 'new', label: 'New', description: 'Recently added feature', icon: 'Sparkles', color: 'cyan', badge_variant: 'secondary', bg: 'bg-cyan-100 dark:bg-cyan-900/30', text: 'text-cyan-700 dark:text-cyan-300', border: 'border-cyan-300 dark:border-cyan-700' },
    'limited_time': { value: 'limited_time', label: 'Limited Time', description: 'Available for a limited time', icon: 'Clock', color: 'orange', badge_variant: 'destructive', bg: 'bg-orange-100 dark:bg-orange-900/30', text: 'text-orange-700 dark:text-orange-300', border: 'border-orange-300 dark:border-orange-700' },
    'recommended': { value: 'recommended', label: 'Recommended', description: 'Recommended by our team', icon: 'ThumbsUp', color: 'indigo', badge_variant: 'secondary', bg: 'bg-indigo-100 dark:bg-indigo-900/30', text: 'text-indigo-700 dark:text-indigo-300', border: 'border-indigo-300 dark:border-indigo-700' },
    'sale': { value: 'sale', label: 'Sale', description: 'Special promotional price', icon: 'Tag', color: 'red', badge_variant: 'destructive', bg: 'bg-red-100 dark:bg-red-900/30', text: 'text-red-700 dark:text-red-300', border: 'border-red-300 dark:border-red-700' },
    'hot': { value: 'hot', label: 'Hot', description: 'Trending right now', icon: 'Flame', color: 'rose', badge_variant: 'default', bg: 'bg-rose-100 dark:bg-rose-900/30', text: 'text-rose-700 dark:text-rose-300', border: 'border-rose-300 dark:border-rose-700' },
    'starter': { value: 'starter', label: 'Starter', description: 'Perfect for getting started', icon: 'Rocket', color: 'sky', badge_variant: 'outline', bg: 'bg-sky-100 dark:bg-sky-900/30', text: 'text-sky-700 dark:text-sky-300', border: 'border-sky-300 dark:border-sky-700' },
    'pro': { value: 'pro', label: 'Pro', description: 'For professionals', icon: 'Crown', color: 'violet', badge_variant: 'outline', bg: 'bg-violet-100 dark:bg-violet-900/30', text: 'text-violet-700 dark:text-violet-300', border: 'border-violet-300 dark:border-violet-700' },
};

export const TENANT_CONFIG_KEY: Record<TenantConfigKey, TenantConfigKeyOption> = {
    'app_name': { value: 'app_name', label: 'Application Name', description: 'Custom name displayed to your users', icon: 'Type', color: 'purple', badge_variant: 'default', category: 'branding', default_value: null },
    'locale': { value: 'locale', label: 'Language', description: 'Default language for your workspace', icon: 'Globe', color: 'blue', badge_variant: 'secondary', category: 'localization', default_value: 'en' },
    'timezone': { value: 'timezone', label: 'Timezone', description: 'Timezone for dates and times', icon: 'Clock', color: 'blue', badge_variant: 'secondary', category: 'localization', default_value: 'UTC' },
    'date_format': { value: 'date_format', label: 'Date Format', description: 'How dates are displayed throughout the application', icon: 'Calendar', color: 'blue', badge_variant: 'secondary', category: 'localization', default_value: 'dd/MM/yyyy' },
    'time_format': { value: 'time_format', label: 'Time Format', description: 'How times are displayed (12-hour or 24-hour)', icon: 'Clock3', color: 'blue', badge_variant: 'secondary', category: 'localization', default_value: '24h' },
    'week_starts_on': { value: 'week_starts_on', label: 'Week Starts On', description: 'First day of the week in calendars', icon: 'CalendarDays', color: 'blue', badge_variant: 'secondary', category: 'localization', default_value: 0 },
    'mail_from_address': { value: 'mail_from_address', label: 'From Email', description: 'Email address used for sending notifications', icon: 'Mail', color: 'orange', badge_variant: 'outline', category: 'email', default_value: null },
    'mail_from_name': { value: 'mail_from_name', label: 'From Name', description: 'Name displayed in email sender field', icon: 'User', color: 'orange', badge_variant: 'outline', category: 'email', default_value: null },
    'currency': { value: 'currency', label: 'Currency', description: 'Default currency for billing', icon: 'DollarSign', color: 'green', badge_variant: 'default', category: 'payments', default_value: 'usd' },
    'currency_locale': { value: 'currency_locale', label: 'Currency Locale', description: 'Locale for currency formatting', icon: 'Languages', color: 'green', badge_variant: 'default', category: 'payments', default_value: 'en' },
};


/**
 * Get metadata for a AddonType value.
 */
export function getAddonTypeMeta(type: AddonType): AddonTypeOption {
    return ADDON_TYPE[type];
}

/**
 * Get metadata for a AddonStatus value.
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

/**
 * Get metadata for a CentralPermission value.
 */
export function getCentralPermissionMeta(permission: CentralPermission): CentralPermissionOption {
    return CENTRAL_PERMISSION[permission];
}

/**
 * Get metadata for a TenantPermission value.
 */
export function getTenantPermissionMeta(permission: TenantPermission): TenantPermissionOption {
    return TENANT_PERMISSION[permission];
}

/**
 * Get metadata for a PermissionCategory value.
 */
export function getPermissionCategoryMeta(permission: PermissionCategory): PermissionCategoryOption {
    return PERMISSION_CATEGORY[permission];
}

/**
 * Get metadata for a PermissionAction value.
 */
export function getPermissionActionMeta(permission: PermissionAction): PermissionActionOption {
    return PERMISSION_ACTION[permission];
}

/**
 * Get metadata for a BadgePreset value.
 */
export function getBadgePresetMeta(preset: BadgePreset): BadgePresetOption {
    return BADGE_PRESET[preset];
}

/**
 * Get metadata for a TenantConfigKey value.
 */
export function getTenantConfigKeyMeta(key: TenantConfigKey): TenantConfigKeyOption {
    return TENANT_CONFIG_KEY[key];
}
