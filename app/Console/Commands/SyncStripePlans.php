<?php

namespace App\Console\Commands;

use App\Services\Central\PlanSyncService;
use Illuminate\Console\Command;

class SyncStripePlans extends Command
{
    protected $signature = 'stripe:sync-plans
                            {--plan= : Sync specific plan by slug}
                            {--locale=en : Locale for product names (en, pt_BR, etc.)}
                            {--dry-run : Preview changes without syncing}
                            {--import= : Import plan from Stripe by product ID}';

    protected $description = 'Sync plans with Stripe products and prices (with i18n support)';

    public function handle(PlanSyncService $syncService): int
    {
        $locale = $this->option('locale');
        $syncService->setLocale($locale);

        $this->info("Using locale: <fg=cyan>{$locale}</>");
        $this->newLine();

        // Import mode
        if ($productId = $this->option('import')) {
            return $this->handleImport($syncService, $productId);
        }

        // Dry run mode
        if ($this->option('dry-run')) {
            return $this->handleDryRun($syncService, $locale);
        }

        // Sync mode
        return $this->handleSync($syncService, $locale);
    }

    protected function handleDryRun(PlanSyncService $syncService, string $locale): int
    {
        $this->info('Dry run - Preview of changes:');
        $this->newLine();

        $preview = $syncService->dryRun($this->option('plan'), $locale);

        if (empty($preview)) {
            $this->warn('No plans to sync.');

            return self::SUCCESS;
        }

        foreach ($preview as $item) {
            $this->line("<fg=cyan>{$item['slug']}</> ({$item['name']})");
            foreach ($item['actions'] as $action) {
                $this->line("  - {$action}");
            }
            $this->newLine();
        }

        return self::SUCCESS;
    }

    protected function handleSync(PlanSyncService $syncService, string $locale): int
    {
        $planSlug = $this->option('plan');

        if ($planSlug) {
            $this->info("Syncing plan: {$planSlug}");
            $plan = \App\Models\Central\Plan::where('slug', $planSlug)->first();

            if (! $plan) {
                $this->error("Plan not found: {$planSlug}");

                return self::FAILURE;
            }

            $result = $syncService->syncPlan($plan, $locale);
            $this->displayResult($result);
        } else {
            $this->info('Syncing all active plans...');
            $results = $syncService->syncAll($locale);

            foreach ($results as $result) {
                $this->displayResult($result);
            }

            $this->newLine();
            $this->info('Sync complete. '.count($results).' plan(s) processed.');
        }

        return self::SUCCESS;
    }

    protected function handleImport(PlanSyncService $syncService, string $productId): int
    {
        $this->info("Importing from Stripe: {$productId}");

        $plan = $syncService->importFromStripe($productId);

        if ($plan) {
            $name = $plan->trans('name');
            $this->info("Imported: {$name} ({$plan->slug})");

            return self::SUCCESS;
        }

        $this->error('Import failed. Check logs for details.');

        return self::FAILURE;
    }

    protected function displayResult(array $result): void
    {
        $status = empty($result['errors']) ? '<fg=green>OK</>' : '<fg=red>FAILED</>';

        $this->line("{$status} {$result['slug']} (locale: {$result['locale']})");

        if ($result['product_synced']) {
            $this->line("  Product: {$result['stripe_product_id']}");
        }

        if ($result['price_synced'] ?? false) {
            $this->line("  Price: {$result['stripe_price_id']}");
        }

        foreach ($result['errors'] as $error) {
            $this->error("  Error: {$error}");
        }
    }
}
