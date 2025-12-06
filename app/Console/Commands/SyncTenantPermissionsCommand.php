<?php

namespace App\Console\Commands;

use App\Jobs\Central\SyncTenantPermissions;
use App\Models\Central\Tenant;
use App\Services\Central\PlanPermissionResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * SyncTenantPermissionsCommand
 *
 * CLI command to sync tenant permissions based on their plan.
 * Useful for:
 * - Manual sync after plan changes
 * - Batch update all tenants
 * - Cleanup after downgrades
 */
class SyncTenantPermissionsCommand extends Command
{
    protected $signature = 'tenant:sync-permissions
                            {tenant? : Tenant ID or slug (optional if using --all)}
                            {--all : Sync permissions for all tenants}
                            {--cleanup : Remove unauthorized permissions from roles}
                            {--cache-only : Only update plan_enabled_permissions cache (skip tenant DB sync)}
                            {--dry-run : Show what would be changed without making changes}';

    protected $description = 'Sync tenant permissions based on their subscription plan';

    public function handle(PlanPermissionResolver $resolver): int
    {
        if ($this->option('all')) {
            return $this->syncAllTenants($resolver);
        }

        $tenantInput = $this->argument('tenant');

        if (!$tenantInput) {
            $this->error('Please provide a tenant ID/slug or use --all flag.');
            return Command::FAILURE;
        }

        return $this->syncSingleTenant($tenantInput, $resolver);
    }

    /**
     * Sync permissions for a single tenant.
     */
    protected function syncSingleTenant(string $tenantInput, PlanPermissionResolver $resolver): int
    {
        $tenant = $this->findTenant($tenantInput);

        if (!$tenant) {
            $this->error("Tenant not found: {$tenantInput}");
            return Command::FAILURE;
        }

        return $this->syncTenant($tenant, $resolver);
    }

    /**
     * Sync permissions for all tenants.
     */
    protected function syncAllTenants(PlanPermissionResolver $resolver): int
    {
        $tenants = Tenant::with('plan')->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found.');
            return Command::SUCCESS;
        }

        $this->info("Syncing permissions for {$tenants->count()} tenants...");
        $this->newLine();

        $bar = $this->output->createProgressBar($tenants->count());
        $bar->start();

        $results = ['success' => 0, 'failed' => 0, 'skipped' => 0];

        foreach ($tenants as $tenant) {
            try {
                if (!$tenant->plan) {
                    $results['skipped']++;
                    $bar->advance();
                    continue;
                }

                if ($this->option('dry-run')) {
                    $this->showDryRun($tenant, $resolver);
                } elseif ($this->option('cache-only')) {
                    $this->updateCacheOnly($tenant, $resolver);
                } else {
                    $this->syncTenantSync($tenant, $resolver);
                }

                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                Log::error("Failed to sync permissions for tenant {$tenant->id}", [
                    'error' => $e->getMessage(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Results:");
        $this->line("  Success: {$results['success']}");
        $this->line("  Failed: {$results['failed']}");
        $this->line("  Skipped (no plan): {$results['skipped']}");

        return $results['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Sync a single tenant (with output).
     */
    protected function syncTenant(Tenant $tenant, PlanPermissionResolver $resolver): int
    {
        $this->info("Syncing permissions for tenant: {$tenant->name} ({$tenant->id})");
        $this->line("Plan: " . ($tenant->plan?->name ?? 'None'));

        if (!$tenant->plan) {
            $this->warn('Tenant has no plan assigned. Skipping.');
            return Command::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->showDryRun($tenant, $resolver);
            return Command::SUCCESS;
        }

        if ($this->option('cache-only')) {
            $this->updateCacheOnly($tenant, $resolver);
            $this->info('Cache updated successfully!');
            return Command::SUCCESS;
        }

        $isDowngrade = $this->option('cleanup');

        if ($isDowngrade) {
            $this->warn('Cleanup mode enabled - will remove unauthorized permissions.');
        }

        try {
            $this->syncTenantSync($tenant, $resolver, $isDowngrade);
            $this->info('Permissions synced successfully!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to sync permissions: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Sync tenant permissions synchronously (not queued).
     */
    protected function syncTenantSync(Tenant $tenant, PlanPermissionResolver $resolver, bool $isDowngrade = false): void
    {
        $job = new SyncTenantPermissions($tenant, $isDowngrade);
        $job->handle($resolver);
    }

    /**
     * Only update the plan_enabled_permissions cache (skip tenant DB operations).
     * Useful when you only need to refresh the cache without touching tenant database.
     */
    protected function updateCacheOnly(Tenant $tenant, PlanPermissionResolver $resolver): void
    {
        $newPermissions = $resolver->resolve($tenant);

        $tenant->forceFill([
            'plan_enabled_permissions' => $newPermissions,
        ])->saveQuietly();
    }

    /**
     * Show what would be changed (dry-run).
     */
    protected function showDryRun(Tenant $tenant, PlanPermissionResolver $resolver): void
    {
        $this->newLine();
        $this->line("=== DRY RUN for {$tenant->name} ===");

        $currentPermissions = $tenant->getPlanEnabledPermissions();
        $newPermissions = $resolver->resolve($tenant);

        $added = array_diff($newPermissions, $currentPermissions);
        $removed = array_diff($currentPermissions, $newPermissions);
        $unchanged = array_intersect($currentPermissions, $newPermissions);

        $this->line("Current permissions: " . count($currentPermissions));
        $this->line("New permissions: " . count($newPermissions));
        $this->newLine();

        if (!empty($added)) {
            $this->info("Would ADD " . count($added) . " permissions:");
            foreach (array_slice($added, 0, 10) as $p) {
                $this->line("  + {$p}");
            }
            if (count($added) > 10) {
                $this->line("  ... and " . (count($added) - 10) . " more");
            }
        }

        if (!empty($removed)) {
            $this->warn("Would REMOVE " . count($removed) . " permissions:");
            foreach (array_slice($removed, 0, 10) as $p) {
                $this->line("  - {$p}");
            }
            if (count($removed) > 10) {
                $this->line("  ... and " . (count($removed) - 10) . " more");
            }
        }

        if (empty($added) && empty($removed)) {
            $this->info("No changes needed.");
        }

        $this->newLine();
    }

    /**
     * Find tenant by ID or slug.
     */
    protected function findTenant(string $input): ?Tenant
    {
        // Try as UUID first
        $tenant = Tenant::find($input);

        if ($tenant) {
            return $tenant;
        }

        // Try as slug
        return Tenant::where('slug', $input)->first();
    }
}
