<?php

namespace App\Console\Commands;

use App\Enums\AddonStatus;
use App\Enums\AddonType;
use App\Enums\BillingPeriod;
use App\Models\Central\AddonSubscription;
use App\Models\Central\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateOverridesToAddons extends Command
{
    protected $signature = 'addons:migrate-overrides
                            {--dry-run : Show what would be migrated without making changes}
                            {--tenant= : Migrate specific tenant by ID}';

    protected $description = 'Migrate existing limit_overrides to AddonSubscription records';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $tenantId = $this->option('tenant');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Check for tenants with plan_limits_override that were manually set
        // (not from addons, but from old override system)
        // Use raw SQL for PostgreSQL JSON comparison (cast to text for comparison)
        $query = Tenant::query()
            ->whereNotNull('plan_limits_override')
            ->whereRaw("plan_limits_override::text NOT IN ('{}', '[]', 'null')");

        if ($tenantId) {
            $query->where('id', $tenantId);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->info('No tenants with limit_overrides found.');

            return self::SUCCESS;
        }

        $this->info("Found {$tenants->count()} tenant(s) with limit_overrides.");

        $migrated = 0;
        $skipped = 0;

        foreach ($tenants as $tenant) {
            $this->newLine();
            $this->line("Processing: {$tenant->name} (ID: {$tenant->id})");

            $overrides = $tenant->plan_limits_override ?? [];

            if (empty($overrides)) {
                $this->line('  → No overrides to migrate');
                $skipped++;

                continue;
            }

            foreach ($overrides as $key => $value) {
                $addonType = $this->mapOverrideToAddonType($key);

                if (! $addonType) {
                    $this->warn("  → Unknown override key: {$key}");

                    continue;
                }

                $addonSlug = "manual_{$key}_override";
                $addonName = ucfirst(str_replace('_', ' ', $key)) . ' Override';

                $this->line("  → {$key}: {$value} → Addon: {$addonName}");

                if (! $dryRun) {
                    $this->createManualAddon($tenant, $addonSlug, $addonName, $addonType, $value);
                }

                $migrated++;
            }

            // Clear the old overrides after migration (set to empty, not null, so sync works correctly)
            if (! $dryRun) {
                $tenant->update(['plan_limits_override' => []]);
                $this->info("  ✓ Cleared plan_limits_override for {$tenant->name}");
            }
        }

        $this->newLine();
        $this->info("Migration complete: {$migrated} overrides " . ($dryRun ? 'would be' : '') . ' migrated, ' . $skipped . ' tenants skipped.');

        return self::SUCCESS;
    }

    protected function mapOverrideToAddonType(string $key): ?AddonType
    {
        // All limit overrides (storage, users, projects, etc.) are QUOTA type
        $validLimitKeys = ['storage', 'users', 'projects', 'bandwidth', 'api_calls'];

        if (in_array($key, $validLimitKeys)) {
            return AddonType::QUOTA;
        }

        return null;
    }

    protected function createManualAddon(
        Tenant $tenant,
        string $slug,
        string $name,
        AddonType $type,
        int $value
    ): AddonSubscription {
        return AddonSubscription::create([
            'tenant_id' => $tenant->id,
            'addon_slug' => $slug,
            'addon_type' => $type,
            'name' => $name,
            'description' => 'Migrated from limit_overrides',
            'quantity' => $value,
            'price' => 0,
            'billing_period' => BillingPeriod::MANUAL,
            'status' => AddonStatus::ACTIVE,
            'started_at' => now(),
            'notes' => 'Auto-migrated from tenant limit_overrides on ' . now()->toDateString(),
        ]);
    }
}
