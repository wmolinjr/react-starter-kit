<?php

namespace App\Services\Central;

use App\Enums\PlanFeature;
use App\Enums\PlanLimit;
use App\Models\Central\Tenant;

/**
 * Plan Feature Resolver
 *
 * Resolves Pennant feature values dynamically based on feature/limit type.
 * Replaces all 18 individual Feature classes with a single resolver.
 *
 * This class is used by PlanFeatureServiceProvider to register all features
 * with Laravel Pennant dynamically.
 */
class PlanFeatureResolver
{
    /**
     * Resolve a boolean feature value.
     * Used for features like customRoles, apiAccess, etc.
     */
    public static function resolveFeature(string $featureKey, Tenant $tenant): bool
    {
        // Validate feature exists
        if (! PlanFeature::exists($featureKey)) {
            return false;
        }

        return $tenant->hasFeature($featureKey);
    }

    /**
     * Resolve a numeric limit value.
     * Used for limits like maxUsers, maxProjects, etc.
     */
    public static function resolveLimit(string $pennantName, Tenant $tenant): int
    {
        // Get the limit enum from Pennant name (maxUsers -> users)
        $limit = PlanLimit::fromPennantName($pennantName);

        if (! $limit) {
            return 0;
        }

        return $tenant->getLimit($limit->value);
    }

    /**
     * Determine if a Pennant feature name is a boolean feature.
     */
    public static function isBooleanFeature(string $name): bool
    {
        return PlanFeature::exists($name);
    }

    /**
     * Determine if a Pennant feature name is a numeric limit.
     */
    public static function isNumericLimit(string $name): bool
    {
        return PlanLimit::fromPennantName($name) !== null;
    }

    /**
     * Get the resolver closure for a boolean feature.
     */
    public static function getBooleanResolver(string $featureKey): \Closure
    {
        return fn (Tenant $tenant): bool => self::resolveFeature($featureKey, $tenant);
    }

    /**
     * Get the resolver closure for a numeric limit.
     */
    public static function getNumericResolver(string $pennantName): \Closure
    {
        return fn (Tenant $tenant): int => self::resolveLimit($pennantName, $tenant);
    }
}
