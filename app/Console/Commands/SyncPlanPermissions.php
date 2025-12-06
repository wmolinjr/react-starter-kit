<?php

namespace App\Console\Commands;

use App\Enums\PlanFeature;
use App\Models\Central\Plan;
use Illuminate\Console\Command;

class SyncPlanPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plans:sync-permissions {--fresh}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync permissions enabled by each plan based on PlanFeature enum';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 Syncing Plan Permissions...');

        if ($this->option('fresh')) {
            $this->warn('⚠️  Fresh mode: Regenerating all permission maps...');
        }

        $plans = Plan::all();

        if ($plans->isEmpty()) {
            $this->warn('⚠️  No plans found. Please run seeders first.');

            return self::FAILURE;
        }

        // Get features from enum (single source of truth)
        $features = PlanFeature::keyed();

        $this->info('Found '.count($features).' feature definitions from PlanFeature enum.');

        foreach ($plans as $plan) {
            $this->syncPlan($plan, $features);
        }

        $this->info('✅ Plan permissions synced successfully!');

        return self::SUCCESS;
    }

    /**
     * Sync a single plan's permission map based on PlanFeature enum
     *
     * @param  array<string, PlanFeature>  $features
     */
    protected function syncPlan(Plan $plan, array $features): void
    {
        $this->line("Processing plan: {$plan->trans('name')}");

        $permissionMap = [];

        // Build permission map based on plan features + PlanFeature.permissions()
        foreach ($plan->features ?? [] as $featureKey => $enabled) {
            if (! $enabled) {
                continue;
            }

            $feature = $features[$featureKey] ?? null;

            if ($feature && ! empty($feature->permissions())) {
                $permissionMap[$featureKey] = $feature->permissions();
            }
        }

        // Update plan
        $plan->update(['permission_map' => $permissionMap]);

        $totalPermissions = empty($permissionMap) ? 0 : count(array_merge(...array_values($permissionMap)));
        $this->info("  ✓ {$plan->trans('name')}: {$totalPermissions} permissions mapped");
    }

    /**
     * Generate permission_map for a single plan (static helper for use in controllers)
     */
    public static function generatePermissionMap(Plan $plan): array
    {
        $features = PlanFeature::keyed();
        $permissionMap = [];

        foreach ($plan->features ?? [] as $featureKey => $enabled) {
            if (! $enabled) {
                continue;
            }

            $feature = $features[$featureKey] ?? null;

            if ($feature && ! empty($feature->permissions())) {
                $permissionMap[$featureKey] = $feature->permissions();
            }
        }

        return $permissionMap;
    }
}
