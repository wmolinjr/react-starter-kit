/**
 * Plan Types - Auto-generated from PlanFeature and PlanLimit enums
 *
 * DO NOT EDIT MANUALLY!
 * Run: sail artisan types:generate
 *
 * Source of truth: app/Enums/PlanFeature.php, PlanLimit.php
 */
export interface PlanFeatures {
    projects: boolean;
    customRoles: boolean;
    apiAccess: boolean;
    advancedReports: boolean;
    sso: boolean;
    whiteLabel: boolean;
    auditLog: boolean;
    prioritySupport: boolean;
    multiLanguage: boolean;
    federation: boolean;
}

export interface PlanLimits {
    users: number;
    projects: number;
    storage: number;
    apiCalls: number;
    logRetention: number;
    fileUploadSize: number;
    customRoles: number;
    locales: number;
}

export interface PlanUsage {
    users: number;
    projects: number;
    storage: number;
    apiCalls: number;
    logRetention: number;
    fileUploadSize: number;
    customRoles: number;
    locales: number;
}

/**
 * Note: For union types with all enum values (including 'base'),
 * use PlanFeature and PlanLimit from '@/types/enums'.
 *
 * The interfaces above (PlanFeatures, PlanLimits, PlanUsage) represent
 * the actual data structure returned by the API, where 'base' is always true
 * and thus excluded from the interface.
 */
