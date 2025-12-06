<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use Illuminate\Console\Command;

/**
 * Regenerate plan_enabled_permissions cache for all tenants.
 *
 * This command should be run after:
 * - Updating plan permission_map in PlanSeeder
 * - Adding new permissions to tenant databases
 * - Fixing corrupted/outdated permission caches
 */
class RegeneratePlanPermissions extends Command
{
    protected $signature = 'tenants:regenerate-permissions
                            {--tenant= : Specific tenant ID to regenerate}
                            {--dry-run : Show what would be changed without making changes}';

    protected $description = 'Regenerate plan_enabled_permissions cache for tenants';

    public function handle(): int
    {
        $this->info('🔄 Regenerating Plan Permissions Cache...');

        $tenantId = $this->option('tenant');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('⚠️  Dry run mode - no changes will be made');
        }

        $query = Tenant::query()->whereNotNull('plan_id');

        if ($tenantId) {
            $query->where('id', $tenantId);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->warn('⚠️  No tenants found with plan_id set.');
            return self::SUCCESS;
        }

        $this->info("Found {$tenants->count()} tenant(s) to process.");
        $this->newLine();

        foreach ($tenants as $tenant) {
            $this->processTenant($tenant, $dryRun);
        }

        $this->newLine();
        $this->info('✅ Done!');

        return self::SUCCESS;
    }

    protected function processTenant(Tenant $tenant, bool $dryRun): void
    {
        $this->line("Processing: {$tenant->name} (Plan: {$tenant->plan?->slug})");

        $currentCount = count($tenant->plan_enabled_permissions ?? []);

        if ($dryRun) {
            // Calculate what would be generated
            $newPermissions = $tenant->plan?->getAllEnabledPermissions() ?? [];
            $expanded = $tenant->plan?->expandPermissions($newPermissions) ?? [];
            $newCount = count($expanded);

            $this->info("  Current: {$currentCount} permissions");
            $this->info("  Would be: {$newCount} permissions");

            if ($newCount > $currentCount) {
                $added = array_diff($expanded, $tenant->plan_enabled_permissions ?? []);
                $this->line("  + Would add: " . implode(', ', array_slice($added, 0, 5)) . (count($added) > 5 ? '...' : ''));
            }
        } else {
            // Clear cache to force regeneration
            $tenant->forceFill(['plan_enabled_permissions' => null])->saveQuietly();

            // Regenerate
            $newPermissions = $tenant->regeneratePlanPermissions();
            $newCount = count($newPermissions);

            $this->info("  ✓ {$currentCount} → {$newCount} permissions");
        }
    }
}
