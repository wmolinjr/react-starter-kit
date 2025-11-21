<?php

namespace App\Console\Commands;

use App\Models\Plan;
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
    protected $description = 'Sync permissions enabled by each plan';

    /**
     * Permission definitions grouped by feature
     */
    protected array $featurePermissions = [
        // Base features (all plans)
        'projects' => [
            'tenant.projects:view',
            'tenant.projects:create',
            'tenant.projects:editOwn',
            'tenant.projects:download',
        ],

        // Professional+
        'customRoles' => [
            'tenant.roles:view',
            'tenant.roles:create',
            'tenant.roles:edit',
            'tenant.roles:delete',
        ],

        'apiAccess' => [
            'tenant.apiTokens:view',
            'tenant.apiTokens:create',
            'tenant.apiTokens:delete',
        ],

        // Enterprise only
        'advancedReports' => [
            'tenant.reports:view',
            'tenant.reports:export',
            'tenant.reports:schedule',
            'tenant.reports:customize',
        ],

        'sso' => [
            'tenant.sso:configure',
            'tenant.sso:manage',
            'tenant.sso:testConnection',
        ],

        'whiteLabel' => [
            'tenant.branding:view',
            'tenant.branding:edit',
            'tenant.branding:preview',
            'tenant.branding:publish',
        ],
    ];

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

        foreach ($plans as $plan) {
            $this->syncPlan($plan);
        }

        $this->info('✅ Plan permissions synced successfully!');

        return self::SUCCESS;
    }

    /**
     * Sync a single plan's permission map
     */
    protected function syncPlan(Plan $plan): void
    {
        $this->line("Processing plan: {$plan->name}");

        $permissionMap = [];

        // Build permission map based on plan features
        foreach ($plan->features ?? [] as $feature => $enabled) {
            if ($enabled && isset($this->featurePermissions[$feature])) {
                $permissionMap[$feature] = $this->featurePermissions[$feature];
            }
        }

        // Update plan
        $plan->update(['permission_map' => $permissionMap]);

        $totalPermissions = count(array_merge(...array_values($permissionMap)));
        $this->info("  ✓ {$plan->name}: {$totalPermissions} permissions mapped");
    }
}
