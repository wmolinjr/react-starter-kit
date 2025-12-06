<?php

namespace Tests\Unit\Resources\Central;

use App\Http\Resources\Central\DomainResource;
use App\Http\Resources\Central\PlanSummaryResource;
use App\Http\Resources\Central\TenantDetailResource;
use App\Http\Resources\Central\TenantEditResource;
use App\Models\Central\Domain;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_resource_formats_correctly(): void
    {
        $tenant = Tenant::factory()->create();

        $domain = new Domain([
            'domain' => 'acme.localhost',
            'is_primary' => true,
        ]);
        $domain->tenant_id = $tenant->id;
        $domain->save();

        $resource = new DomainResource($domain);
        $result = $resource->resolve(request());

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('domain', $result);
        $this->assertArrayHasKey('is_primary', $result);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertEquals('acme.localhost', $result['domain']);
        $this->assertTrue($result['is_primary']);
    }

    public function test_plan_summary_resource_formats_correctly(): void
    {
        $plan = Plan::factory()->create([
            'name' => ['en' => 'Pro', 'pt_BR' => 'Profissional'],
            'slug' => 'pro',
            'price' => 9900,
            'is_featured' => true,
        ]);

        $resource = new PlanSummaryResource($plan);
        $result = $resource->resolve(request());

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('slug', $result);
        $this->assertArrayHasKey('price', $result);
        $this->assertArrayHasKey('formatted_price', $result);
        $this->assertArrayHasKey('is_featured', $result);
        $this->assertEquals('pro', $result['slug']);
        $this->assertEquals(9900, $result['price']);
        $this->assertTrue($result['is_featured']);
    }

    public function test_tenant_edit_resource_includes_plan_id(): void
    {
        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);

        $resource = new TenantEditResource($tenant);
        $result = $resource->resolve(request());

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('slug', $result);
        $this->assertArrayHasKey('plan_id', $result);
        $this->assertArrayHasKey('plan_features_override', $result);
        $this->assertArrayHasKey('plan_limits_override', $result);
        $this->assertEquals($plan->id, $result['plan_id']);
    }

    public function test_tenant_edit_resource_includes_domains(): void
    {
        $tenant = Tenant::factory()->create();

        $domain = new Domain(['domain' => 'test.localhost', 'is_primary' => true]);
        $domain->tenant_id = $tenant->id;
        $domain->save();

        $tenant->load('domains');

        $resource = new TenantEditResource($tenant);
        $result = $resource->resolve(request());

        $this->assertArrayHasKey('domains', $result);
        $this->assertCount(1, $result['domains']);
    }
}
