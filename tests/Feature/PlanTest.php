<?php

namespace Tests\Feature;

use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set locale to 'en' and currency to USD for consistent formatting in tests
        app()->setLocale('en');
        config(['cashier.currency_locale' => 'en']);
        config(['cashier.currency' => 'usd']);
    }

    #[Test]
    public function it_can_check_if_plan_has_feature(): void
    {
        $plan = Plan::factory()->create([
            'features' => [
                'customRoles' => true,
                'apiAccess' => false,
            ],
        ]);

        $this->assertTrue($plan->hasFeature('customRoles'));
        $this->assertFalse($plan->hasFeature('apiAccess'));
        $this->assertFalse($plan->hasFeature('nonexistent'));
    }

    #[Test]
    public function it_can_get_resource_limits(): void
    {
        $plan = Plan::factory()->create([
            'limits' => [
                'users' => 50,
                'projects' => -1, // unlimited
                'storage' => 1024,
            ],
        ]);

        $this->assertEquals(50, $plan->getLimit('users'));
        $this->assertEquals(-1, $plan->getLimit('projects'));
        $this->assertEquals(0, $plan->getLimit('nonexistent'));
    }

    #[Test]
    public function it_can_check_if_unlimited(): void
    {
        $plan = Plan::factory()->create([
            'limits' => [
                'users' => 50,
                'projects' => -1,
            ],
        ]);

        $this->assertFalse($plan->isUnlimited('users'));
        $this->assertTrue($plan->isUnlimited('projects'));
    }

    #[Test]
    public function it_can_get_all_enabled_permissions(): void
    {
        $plan = Plan::factory()->create([
            'features' => [
                'customRoles' => true,
                'apiAccess' => true,
                'advancedReports' => false,
            ],
            'permission_map' => [
                'customRoles' => ['roles:view', 'roles:create'],
                'apiAccess' => ['apiTokens:view'],
                'advancedReports' => ['reports:view'],
            ],
        ]);

        $permissions = $plan->getAllEnabledPermissions();

        $this->assertCount(3, $permissions);
        $this->assertContains('roles:view', $permissions);
        $this->assertContains('roles:create', $permissions);
        $this->assertContains('apiTokens:view', $permissions);
        $this->assertNotContains('reports:view', $permissions);
    }

    #[Test]
    public function it_returns_formatted_price(): void
    {
        $plan = Plan::factory()->create(['price' => 2900]);
        $this->assertEquals('R$29.00', $plan->formatted_price);

        $plan = Plan::factory()->create(['price' => 0]);
        $this->assertEquals('Custom', $plan->formatted_price);
    }

    #[Test]
    public function it_has_relationship_with_tenants(): void
    {
        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);

        $this->assertTrue($plan->tenants->contains($tenant));
    }
}
