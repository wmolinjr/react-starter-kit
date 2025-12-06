<?php

namespace App\Console\Commands;

use App\Models\Central\Tenant;
use App\Services\Central\AddonService;
use Illuminate\Console\Command;

class SyncAddons extends Command
{
    protected $signature = 'addons:sync
                            {--tenant= : Sync specific tenant by ID}
                            {--all : Sync all tenants (not just those with Stripe)}';

    protected $description = 'Sync tenant addon limits with their active addons';

    public function handle(AddonService $addonService): int
    {
        $tenantId = $this->option('tenant');
        $all = $this->option('all');

        $query = Tenant::query();

        if ($tenantId) {
            $query->where('id', $tenantId);
        } elseif (! $all) {
            $query->whereNotNull('stripe_id');
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found to sync.');

            return self::SUCCESS;
        }

        $this->info("Syncing addons for {$tenants->count()} tenant(s)...");

        $bar = $this->output->createProgressBar($tenants->count());
        $bar->start();

        $success = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            try {
                $addonService->syncTenantLimits($tenant);
                $success++;
            } catch (\Exception $e) {
                $failed++;
                $this->newLine();
                $this->error("Failed: {$tenant->name} - {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Sync complete: {$success} succeeded, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
