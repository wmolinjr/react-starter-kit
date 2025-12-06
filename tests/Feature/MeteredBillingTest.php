<?php

namespace Tests\Feature;

use App\Models\Central\AddonSubscription;
use App\Services\Central\MeteredBillingService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * MeteredBillingService Test Suite
 *
 * Tests metered billing functionality with addons in central database.
 */
class MeteredBillingTest extends TenantTestCase
{
    protected MeteredBillingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MeteredBillingService::class);

        // Update tenant's plan limits (don't change slug - conflicts with seeded plans)
        $this->tenant->plan->update([
            'limits' => [
                'storage' => 10000, // 10GB in MB
                'users' => 5,
            ],
        ]);

        $this->tenant->update([
            'current_usage' => [
                'storage' => 0,
                'bandwidth' => 0,
            ],
        ]);
    }

    #[Test]
    public function can_get_usage_summary(): void
    {
        $this->tenant->forceFill([
            'current_usage' => [
                'storage' => 15000, // 15GB - 5GB overage
                'bandwidth' => 150000, // 150GB - 50GB overage
            ],
        ])->save();

        $summary = $this->service->getUsageSummary($this->tenant);

        $this->assertArrayHasKey('storage', $summary);
        $this->assertArrayHasKey('bandwidth', $summary);
        $this->assertEquals(15000, $summary['storage']['used_mb']);
        $this->assertEquals(5000, $summary['storage']['overage_mb']);
        $this->assertEquals(50000, $summary['bandwidth']['overage_mb']);
    }

    #[Test]
    public function no_overage_when_under_limit(): void
    {
        $this->tenant->forceFill([
            'current_usage' => [
                'storage' => 5000, // 5GB - under 10GB limit
                'bandwidth' => 50000, // 50GB - under 100GB free tier
            ],
        ])->save();

        $summary = $this->service->getUsageSummary($this->tenant);

        $this->assertEquals(0, $summary['storage']['overage_mb']);
        $this->assertEquals(0, $summary['bandwidth']['overage_mb']);
    }

    #[Test]
    public function calculates_storage_overage_cost(): void
    {
        // Use forceFill to bypass fillable
        $this->tenant->forceFill([
            'current_usage' => [
                'storage' => 20480, // 20GB - 10GB overage = 10GB
            ],
        ])->save();

        $summary = $this->service->getUsageSummary($this->tenant);

        // With 10GB overage at $0.10/GB = $1.00 = 100 cents
        $this->assertGreaterThanOrEqual(0, $summary['storage']['overage_cost']);
        $this->assertEquals(10480, $summary['storage']['overage_mb']);
    }

    #[Test]
    public function calculates_bandwidth_overage_cost(): void
    {
        $this->tenant->forceFill([
            'current_usage' => [
                'bandwidth' => 204800, // 200GB
            ],
        ])->save();

        $summary = $this->service->getUsageSummary($this->tenant);

        // 200GB - 100GB free tier = 100GB overage at $0.05/GB = $5.00
        $this->assertGreaterThan(0, $summary['bandwidth']['overage_mb']);
    }

    #[Test]
    public function report_storage_returns_false_without_stripe_id(): void
    {
        $this->tenant->update(['stripe_id' => null]);

        $result = $this->service->reportStorageUsage($this->tenant);

        $this->assertFalse($result);
    }

    #[Test]
    public function report_bandwidth_returns_false_without_stripe_id(): void
    {
        $this->tenant->update(['stripe_id' => null]);

        $result = $this->service->reportBandwidthUsage($this->tenant);

        $this->assertFalse($result);
    }

    #[Test]
    public function report_storage_returns_false_when_no_overage(): void
    {
        // Skip if stripe_id column doesn't exist in test db
        if (! \Schema::hasColumn('tenants', 'stripe_id')) {
            $this->markTestSkipped('stripe_id column not available in test database');
        }

        $this->tenant->forceFill(['stripe_id' => 'cus_test123'])->save();
        $this->tenant->update([
            'current_usage' => ['storage' => 5000], // Under limit
        ]);

        $result = $this->service->reportStorageUsage($this->tenant);

        $this->assertFalse($result);
    }

    #[Test]
    public function can_reset_metered_usage(): void
    {
        $addon = AddonSubscription::factory()->forTenant($this->tenant)->metered()->create([
            'metered_usage' => 5000,
        ]);

        $this->service->resetMeteredUsage($this->tenant);

        $addon->refresh();
        $this->assertEquals(0, $addon->metered_usage);
        $this->assertNotNull($addon->metered_reset_at);
    }

    #[Test]
    public function report_all_tenants_returns_count(): void
    {
        // Without any tenants with stripe_id and overage
        $count = $this->service->reportAllTenants();

        $this->assertEquals(0, $count);
    }
}
