<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\Central\AddonOptionForPlanResource;
use App\Http\Resources\Central\CentralDashboardStatsResource;
use App\Http\Resources\Central\CentralUserResource;
use App\Http\Resources\Central\DomainResource;
// Federation Resources - tests skipped due to missing factories
// use App\Http\Resources\Central\FederatedUserResource;
// use App\Http\Resources\Central\FederationConflictResource;
// use App\Http\Resources\Central\FederationGroupResource;
use App\Http\Resources\Central\PlanEditResource;
use App\Http\Resources\Central\PlanResource;
use App\Http\Resources\Central\PlanSummaryResource;
use App\Http\Resources\Central\TenantResource;
use App\Http\Resources\Central\TenantSummaryResource;
use App\Http\Resources\Shared\CategoryOptionResource;
use App\Http\Resources\Shared\FeatureDefinitionResource;
use App\Http\Resources\Shared\LimitDefinitionResource;
use App\Http\Resources\Shared\PermissionResource;
use App\Http\Resources\Shared\RoleResource;
use App\Models\Central\Addon;
use App\Models\Central\Domain;
// Federation Models - tests skipped due to missing factories
// use App\Models\Central\FederatedUser;
// use App\Models\Central\FederationConflict;
// use App\Models\Central\FederationGroup;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use App\Models\Central\User as CentralUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ValidatesResourceSchema;
use Tests\TestCase;

/**
 * Tests that API Resource outputs match their TypeScript schemas.
 *
 * This ensures type safety between PHP Resources and generated TypeScript types.
 */
class ResourceSchemaValidationTest extends TestCase
{
    use RefreshDatabase;
    use ValidatesResourceSchema;

    #[Test]
    public function all_resources_with_typescript_trait_have_valid_schemas(): void
    {
        $resources = $this->getResourcesWithTypescriptType();

        // Should have resources using the trait
        $this->assertNotEmpty($resources, 'No resources found with HasTypescriptType trait');

        // Each should have a valid typescriptSchema method
        foreach ($resources as $resourceClass) {
            $this->assertTrue(
                method_exists($resourceClass, 'typescriptSchema'),
                "Resource {$resourceClass} missing typescriptSchema() method"
            );

            $schema = $resourceClass::typescriptSchema();
            $this->assertIsArray($schema, "Resource {$resourceClass} typescriptSchema() must return array");
            $this->assertNotEmpty($schema, "Resource {$resourceClass} typescriptSchema() should not be empty");
        }
    }

    #[Test]
    public function plan_resource_matches_typescript_schema(): void
    {
        $plan = Plan::factory()->create([
            'name' => ['en' => 'Test Plan', 'pt_BR' => 'Plano Teste'],
            'slug' => 'test-plan',
            'price' => 2900,
            'is_active' => true,
        ]);

        $resource = new PlanResource($plan);
        $this->assertResourceMatchesSchema($resource);
    }

    #[Test]
    public function plan_summary_resource_matches_typescript_schema(): void
    {
        $plan = Plan::factory()->create([
            'name' => ['en' => 'Summary Plan'],
            'slug' => 'summary-plan',
            'price' => 1900,
            'is_featured' => true,
        ]);

        $resource = new PlanSummaryResource($plan);
        $this->assertResourceMatchesSchema($resource);
    }

    #[Test]
    public function plan_edit_resource_matches_typescript_schema(): void
    {
        $plan = Plan::factory()->create([
            'name' => ['en' => 'Edit Plan', 'pt_BR' => 'Plano Editar'],
            'description' => ['en' => 'Description', 'pt_BR' => 'Descrição'],
        ]);
        $plan->load('addons');

        $resource = new PlanEditResource($plan);
        $this->assertResourceMatchesSchema($resource);
    }

    #[Test]
    public function tenant_resource_matches_typescript_schema(): void
    {
        $plan = Plan::factory()->create();
        $tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'plan_id' => $plan->id,
        ]);
        $tenant->load('plan', 'domains');

        $resource = new TenantResource($tenant);
        $this->assertResourceMatchesSchema($resource);
    }

    #[Test]
    public function tenant_summary_resource_matches_typescript_schema(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Summary Tenant']);

        $resource = new TenantSummaryResource($tenant);
        $this->assertResourceMatchesSchema($resource);
    }

    #[Test]
    public function domain_resource_matches_typescript_schema(): void
    {
        $tenant = Tenant::factory()->create();
        // Domain is created automatically by tenant factory, get it
        $domain = $tenant->domains()->first();

        if (! $domain) {
            // Create domain manually if not auto-created
            $domain = new Domain([
                'domain' => 'test.example.com',
                'is_primary' => true,
                'is_verified' => true,
            ]);
            $domain->tenant_id = $tenant->id;
            $domain->save();
        }

        $resource = new DomainResource($domain);
        $this->assertResourceMatchesSchema($resource);
    }

    #[Test]
    public function central_user_resource_matches_typescript_schema(): void
    {
        $user = CentralUser::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
        ]);
        $user->load('roles');

        $resource = new CentralUserResource($user);
        $this->assertResourceMatchesSchema($resource);
    }

    #[Test]
    public function permission_resource_matches_typescript_schema(): void
    {
        // Use a mock model class that supports trans() method
        $permission = new class extends \Illuminate\Database\Eloquent\Model
        {
            public $exists = true;

            protected $keyType = 'string';

            protected $attributes = [
                'id' => '550e8400-e29b-41d4-a716-446655440000',
                'name' => 'projects:view',
                'guard_name' => 'tenant',
                'category' => 'projects',
            ];

            public function trans(string $key): ?string
            {
                return match ($key) {
                    'description' => 'View projects',
                    default => null,
                };
            }
        };

        $resource = new PermissionResource($permission);
        $this->assertResourceMatchesSchema($resource);
    }

    #[Test]
    public function role_resource_matches_typescript_schema(): void
    {
        // Use a mock model class that supports trans() and isProtected() methods
        $role = new class extends \Illuminate\Database\Eloquent\Model
        {
            public $exists = true;

            protected $keyType = 'string';

            protected $attributes = [
                'id' => '550e8400-e29b-41d4-a716-446655440001',
                'name' => 'admin',
                'guard_name' => 'tenant',
                'created_at' => '2024-01-15T10:30:00.000000Z',
            ];

            public $users_count = 5;

            public $permissions_count = 10;

            private bool $_is_protected = false;

            public function trans(string $key): ?string
            {
                return match ($key) {
                    'display_name' => 'Administrator',
                    'description' => 'Admin role',
                    default => null,
                };
            }

            public function isProtected(): bool
            {
                return $this->_is_protected;
            }

            public function getAttribute($key)
            {
                if ($key === 'users_count') {
                    return $this->users_count;
                }
                if ($key === 'permissions_count') {
                    return $this->permissions_count;
                }
                if ($key === 'is_protected') {
                    return $this->_is_protected;
                }

                return parent::getAttribute($key);
            }
        };

        $resource = new RoleResource($role);
        $this->assertResourceMatchesSchema($resource);
    }

    #[Test]
    public function category_option_resource_matches_typescript_schema(): void
    {
        // CategoryOptionResource expects an array, not an object
        $category = [
            'value' => 'team',
            'label' => 'Team Management',
        ];

        $resource = new CategoryOptionResource($category);
        $this->assertResourceMatchesSchema($resource);
    }

    #[Test]
    public function feature_definition_resource_matches_typescript_schema(): void
    {
        // FeatureDefinitionResource expects an array, not an object
        $feature = [
            'value' => 'customRoles',
            'label' => 'Custom Roles',
            'description' => 'Create custom roles',
            'icon' => 'shield',
            'color' => 'blue',
            'category' => 'team',
            'permissions' => ['team:manageRoles'],
            'is_customizable' => true,
        ];

        $resource = new FeatureDefinitionResource($feature);
        $this->assertResourceMatchesSchema($resource);
    }

    #[Test]
    public function limit_definition_resource_matches_typescript_schema(): void
    {
        // LimitDefinitionResource expects an array, not an object
        $limit = [
            'value' => 'users',
            'label' => 'Users',
            'description' => 'Maximum number of users',
            'icon' => 'users',
            'color' => 'blue',
            'category' => 'team',
            'default_value' => 5,
            'unit' => 'users',
            'unit_plural' => 'users',
        ];

        $resource = new LimitDefinitionResource($limit);
        $this->assertResourceMatchesSchema($resource);
    }

    #[Test]
    public function addon_option_for_plan_resource_matches_typescript_schema(): void
    {
        $addon = Addon::factory()->create([
            'name' => ['en' => 'Extra Storage'],
            'slug' => 'extra-storage',
        ]);

        $resource = new AddonOptionForPlanResource($addon);
        $this->assertResourceMatchesSchema($resource);
    }

    #[Test]
    public function central_dashboard_stats_resource_matches_typescript_schema(): void
    {
        // CentralDashboardStatsResource expects an array, not an object
        $stats = [
            'total_tenants' => 10,
            'total_admins' => 2,
            'total_addons' => 5,
            'total_plans' => 3,
        ];

        $resource = new CentralDashboardStatsResource($stats);
        $this->assertResourceMatchesSchema($resource);
    }

    #[Test]
    public function federation_group_resource_matches_typescript_schema(): void
    {
        // Skip if FederationGroup factory doesn't exist
        // Federation models require specific factories to be created
        $this->markTestSkipped('FederationGroup factory not available - create factories to enable this test');
    }

    #[Test]
    public function federated_user_resource_matches_typescript_schema(): void
    {
        // Skip if FederatedUser factory doesn't exist
        $this->markTestSkipped('FederatedUser factory not available - create factories to enable this test');
    }

    #[Test]
    public function federation_conflict_resource_matches_typescript_schema(): void
    {
        // Skip if FederationConflict factory doesn't exist
        $this->markTestSkipped('FederationConflict factory not available - create factories to enable this test');
    }

    /**
     * Test that schema validation trait correctly validates types.
     */
    #[Test]
    public function schema_validator_detects_missing_required_field(): void
    {
        $output = ['name' => 'Test'];
        $schema = ['name' => 'string', 'id' => 'string']; // id is required but missing

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $this->expectExceptionMessage("Missing required key 'id'");

        $this->validateAgainstSchema($output, $schema, 'TestResource');
    }

    #[Test]
    public function schema_validator_allows_null_for_nullable_fields(): void
    {
        $output = ['name' => 'Test', 'description' => null];
        $schema = ['name' => 'string', 'description' => 'string | null'];

        // Should not throw - null is valid for nullable field
        $this->validateAgainstSchema($output, $schema, 'TestResource');
        $this->assertTrue(true); // Assert we got here without exception
    }

    #[Test]
    public function schema_validator_detects_null_in_non_nullable_field(): void
    {
        $output = ['name' => null];
        $schema = ['name' => 'string']; // not nullable

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $this->expectExceptionMessage("is null but schema type 'string' is not nullable");

        $this->validateAgainstSchema($output, $schema, 'TestResource');
    }

    #[Test]
    public function schema_validator_validates_array_types(): void
    {
        $output = ['items' => ['a', 'b', 'c']];
        $schema = ['items' => 'string[]'];

        // Should not throw - array of strings
        $this->validateAgainstSchema($output, $schema, 'TestResource');
        $this->assertTrue(true);
    }

    #[Test]
    public function schema_validator_validates_number_type(): void
    {
        $output = ['count' => 42, 'price' => 19.99];
        $schema = ['count' => 'number', 'price' => 'number'];

        // Should not throw - both int and float are valid numbers
        $this->validateAgainstSchema($output, $schema, 'TestResource');
        $this->assertTrue(true);
    }

    #[Test]
    public function schema_validator_validates_boolean_type(): void
    {
        $output = ['is_active' => true, 'is_deleted' => false];
        $schema = ['is_active' => 'boolean', 'is_deleted' => 'boolean'];

        // Should not throw
        $this->validateAgainstSchema($output, $schema, 'TestResource');
        $this->assertTrue(true);
    }

    #[Test]
    public function schema_validator_validates_union_types(): void
    {
        $output1 = ['value' => 'text'];
        $output2 = ['value' => 42];

        $schema = ['value' => 'string | number'];

        // Both should be valid
        $this->validateAgainstSchema($output1, $schema, 'TestResource');
        $this->validateAgainstSchema($output2, $schema, 'TestResource');
        $this->assertTrue(true);
    }

    #[Test]
    public function schema_validator_validates_nested_resource_types(): void
    {
        $output = [
            'id' => 'abc',
            'user' => ['id' => 'user-1', 'name' => 'John'],
        ];
        $schema = ['id' => 'string', 'user' => 'UserResource'];

        // Should not throw - nested objects are valid for resource references
        $this->validateAgainstSchema($output, $schema, 'TestResource');
        $this->assertTrue(true);
    }

    #[Test]
    public function schema_validator_validates_record_types(): void
    {
        $output = ['settings' => ['key1' => true, 'key2' => false]];
        $schema = ['settings' => 'Record<string, boolean>'];

        // Should not throw
        $this->validateAgainstSchema($output, $schema, 'TestResource');
        $this->assertTrue(true);
    }
}
