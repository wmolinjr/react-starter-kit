<?php

namespace App\Console\Commands;

use App\Services\Central\StripeSyncService;
use Illuminate\Console\Command;

class SyncStripeProducts extends Command
{
    protected $signature = 'stripe:sync
                            {--addon= : Sync specific addon by slug}
                            {--locale=en : Locale for product names (en, pt_BR, etc.)}
                            {--dry-run : Preview changes without syncing}
                            {--import= : Import product from Stripe by product ID}';

    protected $description = 'Sync addon catalog with Stripe products and prices (with i18n support)';

    public function handle(StripeSyncService $syncService): int
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

    protected function handleDryRun(StripeSyncService $syncService, string $locale): int
    {
        $this->info('Dry run - Preview of changes:');
        $this->newLine();

        $preview = $syncService->dryRun($this->option('addon'), $locale);

        if (empty($preview)) {
            $this->warn('No addons to sync.');

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

    protected function handleSync(StripeSyncService $syncService, string $locale): int
    {
        $addonSlug = $this->option('addon');

        if ($addonSlug) {
            $this->info("Syncing addon: {$addonSlug}");
            $addon = \App\Models\Central\Addon::where('slug', $addonSlug)->first();

            if (! $addon) {
                $this->error("Addon not found: {$addonSlug}");

                return self::FAILURE;
            }

            $result = $syncService->syncAddon($addon, $locale);
            $this->displayResult($result);
        } else {
            $this->info('Syncing all active addons...');
            $results = $syncService->syncAll($locale);

            foreach ($results as $result) {
                $this->displayResult($result);
            }

            $this->newLine();
            $this->info('Sync complete. '.count($results).' addon(s) processed.');
        }

        return self::SUCCESS;
    }

    protected function handleImport(StripeSyncService $syncService, string $productId): int
    {
        $this->info("Importing from Stripe: {$productId}");

        $addon = $syncService->importFromStripe($productId);

        if ($addon) {
            $name = $addon->trans('name');
            $this->info("Imported: {$name} ({$addon->slug})");

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

        foreach ($result['prices_synced'] as $period => $priceId) {
            $this->line("  Price ({$period}): {$priceId}");
        }

        foreach ($result['errors'] as $error) {
            $this->error("  Error: {$error}");
        }
    }
}
