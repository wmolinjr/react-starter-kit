/**
 * Plan Types - Auto-generated from PlanFeature and PlanLimit enums
 *
 * DO NOT EDIT MANUALLY!
 * Run: sail artisan plans:generate-types
 *
 * Source of truth: app/Plans/Enums/PlanFeature.php, PlanLimit.php
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

export type PlanFeatureKey = 'projects' | 'customRoles' | 'apiAccess' | 'advancedReports' | 'sso' | 'whiteLabel' | 'auditLog' | 'prioritySupport' | 'multiLanguage' | 'federation';

export type PlanLimitKey = 'users' | 'projects' | 'storage' | 'apiCalls' | 'logRetention' | 'fileUploadSize' | 'customRoles' | 'locales';
