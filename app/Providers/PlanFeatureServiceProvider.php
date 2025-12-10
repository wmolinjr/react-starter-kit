<?php

namespace App\Providers;

use App\Enums\PlanFeature;
use App\Enums\PlanLimit;
use App\Models\Central\Tenant;
use App\Services\Central\PlanFeatureResolver;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

/**
 * Plan Feature Service Provider
 *
 * Registers all plan features and limits dynamically with Laravel Pennant.
 * Uses PlanFeature and PlanLimit enums as source of valid keys.
 *
 * This replaces the 18 individual Feature classes in app/Features/:
 * - Boolean features: Base, Projects, CustomRoles, ApiAccess, etc.
 * - Numeric limits: MaxUsers, MaxProjects, StorageLimit, etc.
 */
class PlanFeatureServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->registerBooleanFeatures();
        $this->registerNumericLimits();
    }

    /**
     * Register all boolean features from PlanFeature enum.
     *
     * Features like: base, projects, customRoles, apiAccess, etc.
     * All resolve via Tenant::hasFeature($key)
     */
    protected function registerBooleanFeatures(): void
    {
        foreach (PlanFeature::values() as $featureKey) {
            Feature::define(
                $featureKey,
                PlanFeatureResolver::getBooleanResolver($featureKey)
            );
        }
    }

    /**
     * Register all numeric limits from PlanLimit enum.
     *
     * Limits like: maxUsers, maxProjects, storageLimit, etc.
     * All resolve via Tenant::getLimit($key)
     */
    protected function registerNumericLimits(): void
    {
        foreach (PlanLimit::cases() as $limit) {
            $pennantName = $limit->pennantFeatureName();

            Feature::define(
                $pennantName,
                PlanFeatureResolver::getNumericResolver($pennantName)
            );
        }
    }
}
