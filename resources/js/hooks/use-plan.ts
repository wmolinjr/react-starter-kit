import { usePage } from '@inertiajs/react';
import type { PageProps, Plan, PlanFeatures, PlanLimits, PlanUsage } from '@/types';

interface UsePlanReturn {
    plan: Plan | null;
    features: PlanFeatures | null;
    limits: PlanLimits | null;
    usage: PlanUsage | null;
    hasFeature: (feature: keyof PlanFeatures) => boolean;
    getLimit: (resource: keyof PlanLimits) => number;
    getUsage: (resource: keyof PlanUsage) => number;
    hasReachedLimit: (resource: keyof PlanLimits) => boolean;
    canAdd: (resource: keyof PlanLimits, amount?: number) => boolean;
    getUsagePercentage: (resource: keyof PlanLimits) => number;
    isUnlimited: (resource: keyof PlanLimits) => boolean;
    isOnTrial: boolean;
}

/**
 * Hook para acessar dados do plano do tenant atual
 *
 * @example
 * ```tsx
 * const { hasFeature, getLimit, hasReachedLimit } = usePlan();
 *
 * if (hasFeature('customRoles')) {
 *     // Show custom roles UI
 * }
 *
 * if (hasReachedLimit('users')) {
 *     // Show upgrade prompt
 * }
 * ```
 */
export function usePlan(): UsePlanReturn {
    const { tenant } = usePage<PageProps>().props;
    const plan = tenant?.plan ?? null;
    const features = plan?.features ?? null;
    const limits = plan?.limits ?? null;
    const usage = plan?.usage ?? null;

    /**
     * Check if tenant has a specific feature
     */
    const hasFeature = (feature: keyof PlanFeatures): boolean => {
        if (!features) return false;
        return features[feature] ?? false;
    };

    /**
     * Get limit for a resource (-1 = unlimited)
     */
    const getLimit = (resource: keyof PlanLimits): number => {
        if (!limits) return 0;
        return limits[resource] ?? 0;
    };

    /**
     * Get current usage for a resource
     */
    const getUsage = (resource: keyof PlanUsage): number => {
        if (!usage) return 0;
        return usage[resource] ?? 0;
    };

    /**
     * Check if limit is unlimited
     */
    const isUnlimited = (resource: keyof PlanLimits): boolean => {
        return getLimit(resource) === -1;
    };

    /**
     * Check if limit has been reached
     */
    const hasReachedLimit = (resource: keyof PlanLimits): boolean => {
        const limit = getLimit(resource);
        const current = getUsage(resource as keyof PlanUsage);

        if (limit === -1) return false; // Unlimited
        return current >= limit;
    };

    /**
     * Check if can add more resources
     */
    const canAdd = (resource: keyof PlanLimits, amount: number = 1): boolean => {
        const limit = getLimit(resource);
        const current = getUsage(resource as keyof PlanUsage);

        if (limit === -1) return true; // Unlimited
        return current + amount <= limit;
    };

    /**
     * Get usage percentage (0-100)
     */
    const getUsagePercentage = (resource: keyof PlanLimits): number => {
        const limit = getLimit(resource);
        const current = getUsage(resource as keyof PlanUsage);

        if (limit === -1) return 0; // Unlimited
        if (limit === 0) return 100; // No limit set

        return Math.min(Math.round((current / limit) * 100), 100);
    };

    /**
     * Check if tenant is on trial
     */
    const isOnTrial = plan?.is_on_trial ?? false;

    return {
        plan,
        features,
        limits,
        usage,
        hasFeature,
        getLimit,
        getUsage,
        hasReachedLimit,
        canAdd,
        getUsagePercentage,
        isUnlimited,
        isOnTrial,
    };
}
