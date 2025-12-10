<?php

namespace Tests\Feature;

use App\Enums\TenantPermission;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Services\Central\PlanPermissionResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlanPermissionResolverTest extends TestCase
{
    protected PlanPermissionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new PlanPermissionResolver;

        // Seed plans
        \Artisan::call('db:seed', ['--class' => 'PlanSeeder']);
    }

    #[Test]
    public function resolves_base_permissions_for_starter_plan(): void
    {
        $starterPlan = Plan::where('slug', 'starter')->first();
        $tenant = Tenant::factory()->create(['plan_id' => $starterPlan->id]);

        $permissions = $this->resolver->resolve($tenant);

        // Starter should have basic project permissions
        $this->assertContains('projects:view', $permissions);
        $this->assertContains('projects:create', $permissions);
        $this->assertContains('team:view', $permissions);
        $this->assertContains('settings:view', $permissions);

        // Starter should NOT have custom roles permissions
        $this->assertNotContains('roles:view', $permissions);
        $this->assertNotContains('roles:create', $permissions);

        // Starter should NOT have API token permissions
        $this->assertNotContains('apiTokens:view', $permissions);
    }

    #[Test]
    public function resolves_feature_permissions_for_professional_plan(): void
    {
        $proPlan = Plan::where('slug', 'professional')->first();
        $tenant = Tenant::factory()->create(['plan_id' => $proPlan->id]);

        $permissions = $this->resolver->resolve($tenant);

        // Professional should have all base permissions
        $this->assertContains('projects:view', $permissions);
        $this->assertContains('projects:edit', $permissions);
        $this->assertContains('projects:delete', $permissions);

        // Professional should have custom roles (customRoles feature)
        $this->assertContains('roles:view', $permissions);
        $this->assertContains('roles:create', $permissions);
        $this->assertContains('roles:edit', $permissions);
        $this->assertContains('roles:delete', $permissions);

        // Professional should have API tokens (apiAccess feature)
        $this->assertContains('apiTokens:view', $permissions);
        $this->assertContains('apiTokens:create', $permissions);

        // Professional should NOT have enterprise features
        $this->assertNotContains('reports:view', $permissions);
        $this->assertNotContains('sso:configure', $permissions);
    }

    #[Test]
    public function resolves_all_permissions_for_enterprise_plan(): void
    {
        $enterprisePlan = Plan::where('slug', 'enterprise')->first();
        $tenant = Tenant::factory()->create(['plan_id' => $enterprisePlan->id]);

        $permissions = $this->resolver->resolve($tenant);

        // Enterprise should have advanced reports
        $this->assertContains('reports:view', $permissions);
        $this->assertContains('reports:export', $permissions);

        // Enterprise should have SSO
        $this->assertContains('sso:configure', $permissions);
        $this->assertContains('sso:manage', $permissions);

        // Enterprise should have white label
        $this->assertContains('branding:view', $permissions);
        $this->assertContains('branding:edit', $permissions);

        // Enterprise should have audit log
        $this->assertContains('audit:view', $permissions);
        $this->assertContains('audit:export', $permissions);
    }

    #[Test]
    public function expands_wildcards_correctly(): void
    {
        $permissions = ['roles:*'];

        $expanded = $this->resolver->expandWildcards($permissions);

        $this->assertContains('roles:view', $expanded);
        $this->assertContains('roles:create', $expanded);
        $this->assertContains('roles:edit', $expanded);
        $this->assertContains('roles:delete', $expanded);
    }

    #[Test]
    public function expands_project_wildcards_with_extended_actions(): void
    {
        $permissions = ['projects:*'];

        $expanded = $this->resolver->expandWildcards($permissions);

        // Should include all project actions
        $this->assertContains('projects:view', $expanded);
        $this->assertContains('projects:create', $expanded);
        $this->assertContains('projects:edit', $expanded);
        $this->assertContains('projects:editOwn', $expanded);
        $this->assertContains('projects:delete', $expanded);
        $this->assertContains('projects:upload', $expanded);
        $this->assertContains('projects:download', $expanded);
        $this->assertContains('projects:archive', $expanded);
    }

    #[Test]
    public function returns_empty_array_for_tenant_without_plan(): void
    {
        $tenant = Tenant::factory()->create(['plan_id' => null]);

        $permissions = $this->resolver->resolve($tenant);

        $this->assertIsArray($permissions);
        $this->assertEmpty($permissions);
    }

    #[Test]
    public function extracts_category_correctly(): void
    {
        // Uses TenantPermission enum as single source of truth
        $this->assertEquals('projects', TenantPermission::extractCategory('projects:view'));
        $this->assertEquals('roles', TenantPermission::extractCategory('roles:create'));
        $this->assertEquals('apiTokens', TenantPermission::extractCategory('apiTokens:delete'));
        $this->assertEquals('sso', TenantPermission::extractCategory('sso:*'));
    }

    #[Test]
    public function groups_permissions_by_category(): void
    {
        $permissions = [
            'projects:view',
            'projects:create',
            'team:view',
            'roles:view',
        ];

        $grouped = $this->resolver->groupByCategory($permissions);

        $this->assertArrayHasKey('projects', $grouped);
        $this->assertArrayHasKey('team', $grouped);
        $this->assertArrayHasKey('roles', $grouped);

        $this->assertCount(2, $grouped['projects']);
        $this->assertCount(1, $grouped['team']);
        $this->assertCount(1, $grouped['roles']);
    }

    #[Test]
    public function compares_plans_correctly(): void
    {
        $starterPlan = Plan::where('slug', 'starter')->first();
        $proPlan = Plan::where('slug', 'professional')->first();

        $comparison = $this->resolver->comparePlans($starterPlan, $proPlan);

        $this->assertArrayHasKey('added', $comparison);
        $this->assertArrayHasKey('removed', $comparison);
        $this->assertArrayHasKey('unchanged', $comparison);

        // Upgrade to pro should add permissions
        $this->assertNotEmpty($comparison['added']);

        // Should add custom roles permissions
        $this->assertContains('roles:view', $comparison['added']);

        // Should add api tokens
        $this->assertContains('apiTokens:view', $comparison['added']);
    }

    #[Test]
    public function detects_downgrade_correctly(): void
    {
        $starterPlan = Plan::where('slug', 'starter')->first();
        $proPlan = Plan::where('slug', 'professional')->first();

        // Starter to Pro = upgrade (not downgrade)
        $this->assertFalse($this->resolver->isDowngrade($starterPlan, $proPlan));

        // Pro to Starter = downgrade
        $this->assertTrue($this->resolver->isDowngrade($proPlan, $starterPlan));
    }

    #[Test]
    public function identifies_removed_permissions_on_downgrade(): void
    {
        $proPlan = Plan::where('slug', 'professional')->first();
        $starterPlan = Plan::where('slug', 'starter')->first();

        $tenant = Tenant::factory()->create(['plan_id' => $proPlan->id]);

        // Resolve current (pro) permissions and save to cache
        $proPermissions = $this->resolver->resolve($tenant);
        $tenant->forceFill(['plan_enabled_permissions' => $proPermissions])->saveQuietly();
        $tenant->refresh();

        // Verify pro permissions were saved
        $this->assertContains('roles:view', $tenant->plan_enabled_permissions);

        // Get starter permissions (without changing tenant plan)
        $starterPermissions = $this->resolver->resolveForPlan($starterPlan);

        $removed = $this->resolver->getRemovedPermissions($tenant, $starterPermissions);

        // Should have removed custom roles
        $this->assertContains('roles:view', $removed);
        $this->assertContains('roles:create', $removed);

        // Should have removed api tokens
        $this->assertContains('apiTokens:view', $removed);
    }

    #[Test]
    public function resolves_for_plan_without_tenant_context(): void
    {
        $proPlan = Plan::where('slug', 'professional')->first();

        $permissions = $this->resolver->resolveForPlan($proPlan);

        // Should resolve based on plan features directly
        $this->assertContains('roles:view', $permissions);
        $this->assertContains('apiTokens:view', $permissions);
    }
}
