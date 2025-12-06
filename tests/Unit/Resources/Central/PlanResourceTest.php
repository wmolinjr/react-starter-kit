<?php

namespace Tests\Unit\Resources\Central;

use App\Http\Resources\Central\PlanEditResource;
use App\Http\Resources\Central\PlanResource;
use App\Http\Resources\Central\PlanSummaryResource;
use App\Models\Central\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_resource_includes_basic_fields(): void
    {
        $plan = Plan::factory()->create([
            'name' => ['en' => 'Starter', 'pt_BR' => 'Inicial'],
            'slug' => 'starter',
            'price' => 2900,
            'currency' => 'BRL',
            'is_active' => true,
        ]);

        $resource = new PlanResource($plan);
        $result = $resource->resolve(request());

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('slug', $result);
        $this->assertArrayHasKey('price', $result);
        $this->assertArrayHasKey('formatted_price', $result);
        $this->assertArrayHasKey('features', $result);
        $this->assertArrayHasKey('limits', $result);
        $this->assertArrayHasKey('is_active', $result);
        $this->assertArrayHasKey('tenants_count', $result);

        $this->assertEquals('starter', $result['slug']);
        $this->assertEquals(2900, $result['price']);
        $this->assertTrue($result['is_active']);
    }

    public function test_plan_resource_returns_translated_name(): void
    {
        $plan = Plan::factory()->create([
            'name' => ['en' => 'Enterprise', 'pt_BR' => 'Empresarial'],
        ]);

        $resource = new PlanResource($plan);
        $result = $resource->resolve(request());

        // Should return translated name based on current locale
        $this->assertNotEmpty($result['name']);
    }

    public function test_plan_summary_resource_includes_minimal_fields(): void
    {
        $plan = Plan::factory()->create([
            'name' => ['en' => 'Pro'],
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

        // Should NOT have detailed fields
        $this->assertArrayNotHasKey('features', $result);
        $this->assertArrayNotHasKey('limits', $result);
        $this->assertArrayNotHasKey('permission_map', $result);
    }

    public function test_plan_edit_resource_includes_translations(): void
    {
        $plan = Plan::factory()->create([
            'name' => ['en' => 'Custom', 'pt_BR' => 'Personalizado'],
            'description' => ['en' => 'Custom plan', 'pt_BR' => 'Plano personalizado'],
        ]);

        $resource = new PlanEditResource($plan);
        $result = $resource->resolve(request());

        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('description', $result);

        // Should include all translations, not just the current locale
        $this->assertIsArray($result['name']);
        $this->assertArrayHasKey('en', $result['name']);
        $this->assertArrayHasKey('pt_BR', $result['name']);
    }

    public function test_plan_edit_resource_includes_addon_ids_when_loaded(): void
    {
        $plan = Plan::factory()->create();
        $plan->load('addons');

        $resource = new PlanEditResource($plan);
        $result = $resource->resolve(request());

        $this->assertArrayHasKey('addon_ids', $result);
        $this->assertIsArray($result['addon_ids']);
    }
}
