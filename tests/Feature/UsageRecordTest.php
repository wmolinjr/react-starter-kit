<?php

namespace Tests\Feature;

use App\Models\Central\Addon;
use App\Models\Central\UsageRecord;
use App\Services\Central\MeteredBillingService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TenantTestCase;

/**
 * UsageRecord Test Suite
 *
 * Tests UsageRecord model and related MeteredBillingService methods.
 */
class UsageRecordTest extends TenantTestCase
{
    protected MeteredBillingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MeteredBillingService::class);

        // Update tenant's plan limits
        $this->tenant->plan->update([
            'limits' => [
                'storage' => 10000, // 10GB in MB
                'users' => 5,
            ],
        ]);
    }

    #[Test]
    public function can_record_usage(): void
    {
        $record = $this->service->recordUsage(
            tenant: $this->tenant,
            usageType: 'storage',
            quantity: 15000,
            unit: 'MB'
        );

        $this->assertInstanceOf(UsageRecord::class, $record);
        $this->assertEquals($this->tenant->id, $record->tenant_id);
        $this->assertEquals('storage', $record->usage_type);
        $this->assertEquals(15000, $record->quantity);
        $this->assertEquals('MB', $record->unit);
        $this->assertEquals(10000, $record->plan_limit);
        $this->assertEquals(5000, $record->overage);
        $this->assertEmpty($record->reported_to_provider);
    }

    #[Test]
    public function records_zero_overage_when_under_limit(): void
    {
        $record = $this->service->recordUsage(
            tenant: $this->tenant,
            usageType: 'storage',
            quantity: 5000,
            unit: 'MB'
        );

        $this->assertEquals(0, $record->overage);
        $this->assertEquals(0, $record->total_cost);
    }

    #[Test]
    public function can_mark_record_as_reported(): void
    {
        $record = $this->service->recordUsage(
            tenant: $this->tenant,
            usageType: 'storage',
            quantity: 15000,
            unit: 'MB'
        );

        $record->markAsReported('stripe_event_123');

        $record->refresh();
        $this->assertTrue($record->reported_to_provider);
        $this->assertNotNull($record->reported_at);
        $this->assertEquals('stripe_event_123', $record->provider_reference_id);
    }

    #[Test]
    public function can_get_usage_history(): void
    {
        // Create multiple records
        $this->service->recordUsage($this->tenant, 'storage', 12000, 'MB');
        $this->service->recordUsage($this->tenant, 'storage', 15000, 'MB');
        $this->service->recordUsage($this->tenant, 'bandwidth', 200000, 'MB');

        $history = $this->service->getUsageHistory($this->tenant);

        $this->assertCount(3, $history);
    }

    #[Test]
    public function can_filter_usage_history_by_type(): void
    {
        $this->service->recordUsage($this->tenant, 'storage', 12000, 'MB');
        $this->service->recordUsage($this->tenant, 'bandwidth', 200000, 'MB');

        $storageHistory = $this->service->getUsageHistory($this->tenant, 'storage');

        $this->assertCount(1, $storageHistory);
        $this->assertEquals('storage', $storageHistory->first()->usage_type);
    }

    #[Test]
    public function can_get_aggregated_usage(): void
    {
        $start = Carbon::now()->startOfMonth();
        $end = Carbon::now()->endOfMonth();

        // Create records for current month
        $this->service->recordUsage($this->tenant, 'storage', 12000, 'MB');
        $this->service->recordUsage($this->tenant, 'storage', 15000, 'MB');

        $aggregated = $this->service->getAggregatedUsage($this->tenant, $start, $end);

        $this->assertArrayHasKey('storage', $aggregated);
        $this->assertEquals(27000, $aggregated['storage']['total_quantity']);
        $this->assertEquals(2, $aggregated['storage']['records_count']);
    }

    #[Test]
    public function can_get_unreported_records(): void
    {
        // Create records with overage
        $record1 = $this->service->recordUsage($this->tenant, 'storage', 15000, 'MB');
        $record2 = $this->service->recordUsage($this->tenant, 'storage', 20000, 'MB');

        // Mark one as reported
        $record1->markAsReported('test_reference');

        $unreported = $this->service->getUnreportedRecords($this->tenant);

        $this->assertCount(1, $unreported);
        $this->assertEquals($record2->id, $unreported->first()->id);
    }

    #[Test]
    public function unreported_records_excludes_zero_overage(): void
    {
        // Create record under limit (no overage)
        $this->service->recordUsage($this->tenant, 'storage', 5000, 'MB');

        $unreported = $this->service->getUnreportedRecords($this->tenant);

        $this->assertCount(0, $unreported);
    }

    #[Test]
    public function usage_record_belongs_to_tenant(): void
    {
        $record = $this->service->recordUsage(
            tenant: $this->tenant,
            usageType: 'storage',
            quantity: 15000,
            unit: 'MB'
        );

        $this->assertInstanceOf(\App\Models\Central\Tenant::class, $record->tenant);
        $this->assertEquals($this->tenant->id, $record->tenant->id);
    }

    #[Test]
    public function usage_record_can_have_addon(): void
    {
        $addon = Addon::where('slug', 'storage_50gb')->first();

        $record = $this->service->recordUsage(
            tenant: $this->tenant,
            usageType: 'storage',
            quantity: 15000,
            unit: 'MB',
            addon: $addon
        );

        $this->assertNotNull($record->addon);
        $this->assertEquals($addon->id, $record->addon_id);
    }

    #[Test]
    public function can_store_metadata(): void
    {
        $metadata = ['source' => 'scheduled_job', 'version' => '1.0'];

        $record = $this->service->recordUsage(
            tenant: $this->tenant,
            usageType: 'storage',
            quantity: 15000,
            unit: 'MB',
            metadata: $metadata
        );

        $this->assertEquals($metadata, $record->metadata);
    }

    #[Test]
    public function scope_unreported_works(): void
    {
        // Clean up any existing records for this tenant
        UsageRecord::where('tenant_id', $this->tenant->id)->delete();

        $record1 = $this->service->recordUsage($this->tenant, 'storage', 15000, 'MB');
        $record2 = $this->service->recordUsage($this->tenant, 'storage', 20000, 'MB');

        $record1->markAsReported('test');

        $unreported = UsageRecord::where('tenant_id', $this->tenant->id)->unreported()->get();

        $this->assertCount(1, $unreported);
    }

    #[Test]
    public function scope_with_overage_works(): void
    {
        // Clean up any existing records for this tenant
        UsageRecord::where('tenant_id', $this->tenant->id)->delete();

        // Under limit
        $this->service->recordUsage($this->tenant, 'storage', 5000, 'MB');
        // Over limit
        $this->service->recordUsage($this->tenant, 'storage', 15000, 'MB');

        $withOverage = UsageRecord::where('tenant_id', $this->tenant->id)->withOverage()->get();

        $this->assertCount(1, $withOverage);
    }

    #[Test]
    public function scope_of_type_works(): void
    {
        // Clean up any existing records for this tenant
        UsageRecord::where('tenant_id', $this->tenant->id)->delete();

        $this->service->recordUsage($this->tenant, 'storage', 15000, 'MB');
        $this->service->recordUsage($this->tenant, 'bandwidth', 200000, 'MB');

        $storageRecords = UsageRecord::where('tenant_id', $this->tenant->id)->ofType('storage')->get();

        $this->assertCount(1, $storageRecords);
        $this->assertEquals('storage', $storageRecords->first()->usage_type);
    }

    #[Test]
    public function formatted_attributes_work(): void
    {
        $record = $this->service->recordUsage(
            tenant: $this->tenant,
            usageType: 'storage',
            quantity: 15000,
            unit: 'MB'
        );

        $this->assertStringContainsString('15', $record->formatted_quantity);
        $this->assertStringContainsString('MB', $record->formatted_quantity);
    }

    #[Test]
    public function calculate_cost_handles_mb_conversion(): void
    {
        $record = new UsageRecord([
            'overage' => 10240, // 10GB in MB
            'unit_price' => 100, // 100 cents per GB
            'unit' => 'MB',
        ]);

        // 10GB * 100 cents = 1000 cents ($10)
        $cost = $record->calculateCost();

        $this->assertEquals(1000, $cost);
    }

    #[Test]
    public function calculate_cost_handles_units(): void
    {
        $record = new UsageRecord([
            'overage' => 100,
            'unit_price' => 10,
            'unit' => 'units',
        ]);

        // 100 * 10 = 1000 cents
        $cost = $record->calculateCost();

        $this->assertEquals(1000, $cost);
    }
}
