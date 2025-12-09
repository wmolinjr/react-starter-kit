/**
 * Enum Types - Auto-generated from PHP Enums
 *
 * DO NOT EDIT MANUALLY!
 * Run: sail artisan types:generate
 *
 * Source of truth:
 * - app/Enums/AddonType.php
 * - app/Enums/AddonStatus.php
 * - app/Enums/BillingPeriod.php
 * - app/Enums/PlanFeature.php
 * - app/Enums/PlanLimit.php
 * - app/Enums/TenantRole.php
 * - app/Enums/FederatedUserStatus.php
 * - app/Enums/FederatedUserLinkSyncStatus.php
 * - app/Enums/FederationConflictStatus.php
 * - app/Enums/FederationSyncStrategy.php
 * - app/Enums/CentralPermission.php
 * - app/Enums/TenantPermission.php
 * - app/Enums/PermissionCategory.php
 * - app/Enums/PermissionAction.php
 * - app/Enums/BadgePreset.php
 * - app/Enums/TenantConfigKey.php
 */
export type AddonType = 'quota' | 'feature' | 'metered' | 'credit';

export interface AddonTypeOption {
    value: AddonType;
    label: string;
    description: string;
    icon: string;
    color: string;
    badge_variant: 'default' | 'destructive' | 'secondary' | 'outline';
    category: string;
    unit_label: string;
    is_metered: boolean;
    is_stackable: boolean;
    is_recurring: boolean;
    is_one_time: boolean;
    has_validity: boolean;
}

export type AddonStatus = 'pending' | 'active' | 'canceled' | 'expired' | 'failed';

export interface AddonStatusOption {
    value: AddonStatus;
    label: string;
    description: string;
    icon: string;
    color: string;
    badge_variant: 'default' | 'destructive' | 'secondary' | 'outline';
    is_usable: boolean;
    is_terminal: boolean;
}

export type BillingPeriod = 'monthly' | 'yearly' | 'one_time' | 'metered' | 'manual';

export interface BillingPeriodOption {
    value: BillingPeriod;
    label: string;
    description: string;
    icon: string;
    color: string;
    badge_variant: 'default' | 'destructive' | 'secondary' | 'outline';
    is_recurring: boolean;
}

export type PlanFeature = 'base' | 'projects' | 'customRoles' | 'apiAccess' | 'advancedReports' | 'sso' | 'whiteLabel' | 'auditLog' | 'prioritySupport' | 'multiLanguage' | 'federation';

export interface PlanFeatureOption {
    value: PlanFeature;
    label: string;
    description: string;
    icon: string;
    color: string;
    badge_variant: 'default' | 'destructive' | 'secondary' | 'outline';
    category: string;
    permissions: string[];
    is_customizable: boolean;
}

export type PlanLimit = 'users' | 'projects' | 'storage' | 'apiCalls' | 'logRetention' | 'fileUploadSize' | 'customRoles' | 'locales';

export interface PlanLimitOption {
    value: PlanLimit;
    label: string;
    description: string;
    icon: string;
    color: string;
    badge_variant: 'default' | 'destructive' | 'secondary' | 'outline';
    unit: string;
    unit_label: string;
    default_value: number;
    allows_unlimited: boolean;
    is_customizable: boolean;
}

export type TenantRole = 'owner' | 'admin' | 'member';

export interface TenantRoleOption {
    value: TenantRole;
    label: string;
    description: string;
    icon: string;
    color: string;
    badge_variant: 'default' | 'destructive' | 'secondary' | 'outline';
    is_system: boolean;
}

export type FederatedUserStatus = 'active' | 'suspended' | 'pending_review' | 'pending_master_sync';

export interface FederatedUserStatusOption {
    value: FederatedUserStatus;
    label: string;
    description: string;
    icon: string;
    color: string;
    badge_variant: 'default' | 'destructive' | 'secondary' | 'outline';
    can_sync: boolean;
    is_pending: boolean;
}

export type FederatedUserLinkSyncStatus = 'synced' | 'pending_sync' | 'sync_failed' | 'conflict' | 'disabled';

export interface FederatedUserLinkSyncStatusOption {
    value: FederatedUserLinkSyncStatus;
    label: string;
    description: string;
    icon: string;
    color: string;
    badge_variant: 'default' | 'destructive' | 'secondary' | 'outline';
    needs_sync: boolean;
    has_issue: boolean;
}

export type FederationConflictStatus = 'pending' | 'resolved' | 'dismissed';

export interface FederationConflictStatusOption {
    value: FederationConflictStatus;
    label: string;
    description: string;
    icon: string;
    color: string;
    badge_variant: 'default' | 'destructive' | 'secondary' | 'outline';
    requires_action: boolean;
    is_terminal: boolean;
}

export type FederationSyncStrategy = 'master_wins' | 'last_write_wins' | 'manual_review';

export interface FederationSyncStrategyOption {
    value: FederationSyncStrategy;
    label: string;
    description: string;
    icon: string;
    color: string;
    badge_variant: 'default' | 'destructive' | 'secondary' | 'outline';
    creates_conflicts: boolean;
    auto_resolves: boolean;
}

export type CentralPermission = 'tenants:view' | 'tenants:show' | 'tenants:edit' | 'tenants:delete' | 'tenants:impersonate' | 'users:view' | 'users:show' | 'users:edit' | 'users:delete' | 'plans:view' | 'plans:create' | 'plans:edit' | 'plans:delete' | 'plans:sync' | 'catalog:view' | 'catalog:create' | 'catalog:edit' | 'catalog:delete' | 'catalog:sync' | 'addons:view' | 'addons:revenue' | 'addons:grant' | 'addons:revoke' | 'roles:view' | 'roles:create' | 'roles:edit' | 'roles:delete' | 'system:view' | 'system:edit' | 'system:logs' | 'federation:view' | 'federation:create' | 'federation:edit' | 'federation:delete' | 'federation:manageConflicts';

export interface CentralPermissionOption {
    value: CentralPermission;
    label: string;
    description: string;
    icon: string;
    color: string;
    badge_variant: 'default' | 'destructive' | 'secondary' | 'outline';
    category: string;
    action: string;
}

export type TenantPermission = 'projects:view' | 'projects:create' | 'projects:edit' | 'projects:editOwn' | 'projects:delete' | 'projects:upload' | 'projects:download' | 'projects:archive' | 'team:view' | 'team:invite' | 'team:remove' | 'team:manageRoles' | 'team:activity' | 'settings:view' | 'settings:edit' | 'settings:danger' | 'billing:view' | 'billing:manage' | 'billing:invoices' | 'apiTokens:view' | 'apiTokens:create' | 'apiTokens:delete' | 'roles:view' | 'roles:create' | 'roles:edit' | 'roles:delete' | 'reports:view' | 'reports:export' | 'reports:schedule' | 'reports:customize' | 'sso:configure' | 'sso:manage' | 'sso:testConnection' | 'branding:view' | 'branding:edit' | 'branding:preview' | 'branding:publish' | 'audit:view' | 'audit:export' | 'locales:view' | 'locales:manage' | 'federation:view' | 'federation:manage' | 'federation:invite' | 'federation:leave';

export interface TenantPermissionOption {
    value: TenantPermission;
    label: string;
    description: string;
    icon: string;
    color: string;
    badge_variant: 'default' | 'destructive' | 'secondary' | 'outline';
    category: string;
    action: string;
}

export type PermissionCategory = 'projects' | 'team' | 'settings' | 'billing' | 'apiTokens' | 'roles' | 'reports' | 'sso' | 'branding' | 'audit' | 'locales' | 'federation';

export interface PermissionCategoryOption {
    value: PermissionCategory;
    label: string;
    icon: string;
    color: string;
    badge_variant: 'default' | 'destructive' | 'secondary' | 'outline';
}

export type PermissionAction = 'view' | 'create' | 'edit' | 'editOwn' | 'delete' | 'upload' | 'download' | 'archive' | 'invite' | 'remove' | 'manageRoles' | 'activity' | 'danger' | 'manage' | 'invoices' | 'export' | 'schedule' | 'customize' | 'configure' | 'testConnection' | 'preview' | 'publish' | 'leave';

export interface PermissionActionOption {
    value: PermissionAction;
    label: string;
}

export type BadgePreset = 'most_popular' | 'best_value' | 'best_for_teams' | 'enterprise' | 'one_time' | 'new' | 'limited_time' | 'recommended' | 'sale' | 'hot' | 'starter' | 'pro';

export interface BadgePresetOption {
    value: BadgePreset;
    label: string;
    description: string;
    icon: string;
    color: string;
    badge_variant: 'default' | 'destructive' | 'secondary' | 'outline';
    bg: string;
    text: string;
    border: string;
}

export type TenantConfigKey = 'app_name' | 'locale' | 'timezone' | 'mail_from_address' | 'mail_from_name' | 'currency' | 'currency_locale';

export interface TenantConfigKeyOption {
    value: TenantConfigKey;
    label: string;
    description: string;
    icon: string;
    color: string;
    badge_variant: 'default' | 'destructive' | 'secondary' | 'outline';
    category: string;
    default_value: string | number | null;
}

