<?php

namespace App\Console\Commands;

use App\Services\Central\MeteredBillingService;
use Illuminate\Console\Command;

class ReportMeteredUsage extends Command
{
    protected $signature = 'billing:report-usage
                            {--tenant= : Report usage for specific tenant}
                            {--dry-run : Show what would be reported without sending to Stripe}';

    protected $description = 'Report metered usage (storage, bandwidth) to Stripe';

    public function handle(MeteredBillingService $service): int
    {
        $tenantId = $this->option('tenant');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be sent to Stripe');
        }

        $this->info('Reporting metered usage to Stripe...');

        if ($tenantId) {
            $tenant = \App\Models\Central\Tenant::find($tenantId);

            if (! $tenant) {
                $this->error("Tenant {$tenantId} not found.");

                return self::FAILURE;
            }

            if ($dryRun) {
                $summary = $service->getUsageSummary($tenant);
                $this->displayUsageSummary($tenant, $summary);

                return self::SUCCESS;
            }

            $results = $service->reportTenantUsage($tenant);
            $this->line('Storage reported: '.($results['storage'] ? 'Yes' : 'No'));
            $this->line('Bandwidth reported: '.($results['bandwidth'] ? 'Yes' : 'No'));

            return self::SUCCESS;
        }

        if ($dryRun) {
            $tenants = \App\Models\Central\Tenant::whereNotNull('stripe_id')->get();

            foreach ($tenants as $tenant) {
                $summary = $service->getUsageSummary($tenant);
                $this->displayUsageSummary($tenant, $summary);
            }

            return self::SUCCESS;
        }

        $count = $service->reportAllTenants();
        $this->info("Reported usage for {$count} tenant(s) with overage.");

        return self::SUCCESS;
    }

    protected function displayUsageSummary($tenant, array $summary): void
    {
        $this->newLine();
        $this->line("Tenant: {$tenant->name} (ID: {$tenant->id})");
        $this->table(
            ['Type', 'Used (MB)', 'Limit/Free (MB)', 'Overage (MB)', 'Est. Cost'],
            [
                [
                    'Storage',
                    number_format($summary['storage']['used_mb']),
                    number_format($summary['storage']['limit_mb']),
                    number_format($summary['storage']['overage_mb']),
                    '$'.number_format($summary['storage']['overage_cost'] / 100, 2),
                ],
                [
                    'Bandwidth',
                    number_format($summary['bandwidth']['used_mb']),
                    number_format($summary['bandwidth']['free_tier_mb']),
                    number_format($summary['bandwidth']['overage_mb']),
                    '$'.number_format($summary['bandwidth']['overage_cost'] / 100, 2),
                ],
            ]
        );
    }
}
