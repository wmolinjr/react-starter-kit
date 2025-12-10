<?php

namespace Tests\Feature;

use App\Models\Central\Addon;
use App\Models\Central\AddonBundle;
use App\Models\Central\User as Admin;
use App\Services\Central\StripeSyncService;
use Database\Seeders\AddonBundleSeeder;
use Database\Seeders\AddonSeeder;
use Database\Seeders\PlanSeeder;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * BundleCatalogController Test Suite
 *
 * Tests the bundle catalog management endpoints for the central admin.
 *
 * OPTION C: Uses Admin model with 'central' guard for central admin routes.
 */
class BundleCatalogControllerTest extends TestCase
{
    protected Admin $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // OPTION C: Create super admin using Admin model with super-admin role
        $this->adminUser = Admin::factory()->superAdmin()->create();

        // Seed plans, addons and bundles
        $this->seed(PlanSeeder::class);
        $this->seed(AddonSeeder::class);
        $this->seed(AddonBundleSeeder::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Index Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function guest_cannot_access_bundle_index(): void
    {
        $response = $this->get($this->centralUrl('/admin/bundles'));

        $response->assertRedirect();
    }

    #[Test]
    public function non_super_admin_cannot_access_bundle_index(): void
    {
        // Admin without role cannot access bundle index
        $regularAdmin = Admin::factory()->create();

        $response = $this->actingAs($regularAdmin, 'central')
            ->get($this->centralUrl('/admin/bundles'));

        $response->assertForbidden();
    }

    #[Test]
    public function admin_can_access_bundle_index(): void
    {
        $response = $this->actingAs($this->adminUser, 'central')
            ->get($this->centralUrl('/admin/bundles'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('central/admin/bundles/index')
            ->has('bundles')
        );
    }

    #[Test]
    public function bundle_index_shows_bundles_with_correct_data(): void
    {
        $response = $this->actingAs($this->adminUser, 'central')
            ->get($this->centralUrl('/admin/bundles'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('central/admin/bundles/index')
            ->has('bundles', fn ($bundles) => $bundles
                ->each(fn ($bundle) => $bundle
                    ->has('id')
                    ->has('slug')
                    ->has('name')
                    ->has('name_display')
                    ->has('active')
                    ->has('discount_percent')
                    ->has('addons')
                    ->has('is_synced')
                    ->etc() // Ignore other properties
                )
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Create Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function admin_can_access_bundle_create_form(): void
    {
        $response = $this->actingAs($this->adminUser, 'central')
            ->get($this->centralUrl('/admin/bundles/create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('central/admin/bundles/create')
            ->has('addons')
            ->has('plans')
        );
    }

    #[Test]
    public function admin_can_store_new_bundle(): void
    {
        $addons = Addon::active()->take(2)->get();

        $bundleData = [
            'slug' => 'test_new_bundle',
            'name' => ['en' => 'Test Bundle', 'pt_BR' => 'Pacote Teste'],
            'description' => ['en' => 'A test bundle description'],
            'active' => true,
            'discount_percent' => 15,
            'sort_order' => 0,
            'addons' => $addons->map(fn ($addon, $index) => [
                'addon_id' => $addon->id,
                'quantity' => 1,
            ])->toArray(),
        ];

        $response = $this->actingAs($this->adminUser, 'central')
            ->post($this->centralUrl('/admin/bundles'), $bundleData);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('addon_bundles', [
            'slug' => 'test_new_bundle',
            'discount_percent' => 15,
        ]);
    }

    #[Test]
    public function store_bundle_requires_at_least_two_addons(): void
    {
        $addon = Addon::active()->first();

        $bundleData = [
            'slug' => 'invalid_bundle',
            'name' => ['en' => 'Invalid Bundle'],
            'addons' => [
                ['addon_id' => $addon->id, 'quantity' => 1],
            ],
        ];

        $response = $this->actingAs($this->adminUser, 'central')
            ->post($this->centralUrl('/admin/bundles'), $bundleData);

        $response->assertSessionHasErrors('addons');
    }

    /*
    |--------------------------------------------------------------------------
    | Edit Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function admin_can_access_bundle_edit_form(): void
    {
        $bundle = AddonBundle::first();

        $response = $this->actingAs($this->adminUser, 'central')
            ->get($this->centralUrl("/admin/bundles/{$bundle->id}/edit"));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('central/admin/bundles/edit')
            ->has('bundle')
            ->has('addons')
            ->has('plans')
        );
    }

    #[Test]
    public function admin_can_update_bundle(): void
    {
        $bundle = AddonBundle::first();
        $addons = Addon::active()->take(2)->get();

        $updateData = [
            'name' => ['en' => 'Updated Bundle Name', 'pt_BR' => 'Nome Atualizado'],
            'description' => ['en' => 'Updated description'],
            'active' => true,
            'discount_percent' => 25,
            'sort_order' => 10,
            'addons' => $addons->map(fn ($addon, $index) => [
                'addon_id' => $addon->id,
                'quantity' => 2,
            ])->toArray(),
        ];

        $response = $this->actingAs($this->adminUser, 'central')
            ->put($this->centralUrl("/admin/bundles/{$bundle->id}"), $updateData);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $bundle->refresh();
        $this->assertEquals(25, $bundle->discount_percent);
        $this->assertEquals(10, $bundle->sort_order);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function admin_can_delete_bundle(): void
    {
        $bundle = AddonBundle::factory()->create(['active' => false]);

        $response = $this->actingAs($this->adminUser, 'central')
            ->delete($this->centralUrl("/admin/bundles/{$bundle->id}"));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Bundle uses SoftDeletes
        $this->assertSoftDeleted('addon_bundles', [
            'id' => $bundle->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Sync Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function admin_can_sync_single_bundle(): void
    {
        // Mock StripeSyncService to avoid actual Stripe calls
        $mockService = Mockery::mock(StripeSyncService::class);
        $mockService->shouldReceive('syncBundle')
            ->once()
            ->andReturn([
                'product_synced' => true,
                'prices_synced' => ['monthly' => 'price_test'],
                'errors' => [],
            ]);

        $this->app->instance(StripeSyncService::class, $mockService);

        $bundle = AddonBundle::first();

        $response = $this->actingAs($this->adminUser, 'central')
            ->post($this->centralUrl("/admin/bundles/{$bundle->id}/sync"));

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    #[Test]
    public function sync_bundle_shows_error_on_failure(): void
    {
        // Mock StripeSyncService to return error
        $mockService = Mockery::mock(StripeSyncService::class);
        $mockService->shouldReceive('syncBundle')
            ->once()
            ->andReturn([
                'product_synced' => false,
                'prices_synced' => [],
                'errors' => ['API Error: Invalid API key'],
            ]);

        $this->app->instance(StripeSyncService::class, $mockService);

        $bundle = AddonBundle::first();

        $response = $this->actingAs($this->adminUser, 'central')
            ->post($this->centralUrl("/admin/bundles/{$bundle->id}/sync"));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function admin_can_sync_all_bundles(): void
    {
        // Mock StripeSyncService
        $mockService = Mockery::mock(StripeSyncService::class);
        $mockService->shouldReceive('syncAllBundles')
            ->once()
            ->andReturn([
                [
                    'slug' => 'bundle1',
                    'product_synced' => true,
                    'errors' => [],
                ],
                [
                    'slug' => 'bundle2',
                    'product_synced' => true,
                    'errors' => [],
                ],
            ]);

        $this->app->instance(StripeSyncService::class, $mockService);

        $response = $this->actingAs($this->adminUser, 'central')
            ->post($this->centralUrl('/admin/bundles/sync-all'));

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    #[Test]
    public function sync_all_shows_warning_on_partial_failure(): void
    {
        // Mock StripeSyncService with partial failures
        $mockService = Mockery::mock(StripeSyncService::class);
        $mockService->shouldReceive('syncAllBundles')
            ->once()
            ->andReturn([
                [
                    'slug' => 'bundle1',
                    'product_synced' => true,
                    'errors' => [],
                ],
                [
                    'slug' => 'bundle2',
                    'product_synced' => false,
                    'errors' => ['API Error'],
                ],
            ]);

        $this->app->instance(StripeSyncService::class, $mockService);

        $response = $this->actingAs($this->adminUser, 'central')
            ->post($this->centralUrl('/admin/bundles/sync-all'));

        $response->assertRedirect();
        $response->assertSessionHas('warning');
    }

    #[Test]
    public function non_super_admin_cannot_sync(): void
    {
        // Admin without role cannot sync bundles
        $regularAdmin = Admin::factory()->create();

        $bundle = AddonBundle::first();

        $response = $this->actingAs($regularAdmin, 'central')
            ->post($this->centralUrl("/admin/bundles/{$bundle->id}/sync"));

        $response->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Bundle Data Structure Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function bundle_includes_sync_status_in_response(): void
    {
        // Create bundle with stripe product id
        $syncedBundle = AddonBundle::factory()->create([
            'stripe_product_id' => 'prod_test123',
            'active' => true,
        ]);

        // Create bundle without stripe product id
        $unsyncedBundle = AddonBundle::factory()->create([
            'stripe_product_id' => null,
            'active' => true,
        ]);

        $response = $this->actingAs($this->adminUser, 'central')
            ->get($this->centralUrl('/admin/bundles'));

        $response->assertOk();

        // Find our bundles in response
        $bundles = $response->original->getData()['page']['props']['bundles'];
        $syncedInResponse = collect($bundles)->firstWhere('id', $syncedBundle->id);
        $unsyncedInResponse = collect($bundles)->firstWhere('id', $unsyncedBundle->id);

        $this->assertTrue($syncedInResponse['is_synced']);
        $this->assertFalse($unsyncedInResponse['is_synced']);
    }

    #[Test]
    public function bundle_includes_effective_pricing(): void
    {
        $response = $this->actingAs($this->adminUser, 'central')
            ->get($this->centralUrl('/admin/bundles'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('central/admin/bundles/index')
            ->has('bundles', fn ($bundles) => $bundles
                ->each(fn ($bundle) => $bundle
                    ->has('price_monthly_effective')
                    ->has('price_yearly_effective')
                    ->has('base_price_monthly')
                    ->has('savings_monthly')
                    ->etc() // Ignore other properties
                )
            )
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
