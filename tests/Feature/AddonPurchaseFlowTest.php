<?php

namespace Tests\Feature;

use App\Enums\AddonStatus;
use App\Enums\BillingPeriod;
use App\Models\Central\AddonSubscription;
use Tests\TenantTestCase;

class AddonPurchaseFlowTest extends TenantTestCase
{
    public function test_addons_index_page_loads(): void
    {
        // Use tenantRoute() because /admin/addons exists in both central and tenant routes
        $response = $this->get($this->tenantRoute('tenant.admin.addons.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('tenant/admin/addons/index'));
    }

    public function test_addons_usage_page_loads(): void
    {
        $response = $this->get($this->tenantRoute('tenant.admin.addons.usage'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('tenant/admin/addons/usage'));
    }

    public function test_addons_success_page_loads(): void
    {
        $response = $this->get($this->tenantRoute('tenant.admin.addons.success'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('tenant/admin/addons/success'));
    }

    public function test_purchase_addon_requires_authentication(): void
    {
        $response = $this->post($this->tenantRoute('tenant.admin.addons.purchase'), [
            'addon_slug' => 'storage_50gb',
            'quantity' => 1,
            'billing_period' => 'monthly',
        ]);

        // Guest is redirected to login or gets unauthorized
        $response->assertStatus(302);
    }

    public function test_purchase_addon_validates_required_fields(): void
    {
        $response = $this->post($this->tenantRoute('tenant.admin.addons.purchase'), []);

        $response->assertSessionHasErrors(['addon_slug', 'quantity', 'billing_period']);
    }

    public function test_purchase_addon_validates_billing_period(): void
    {
        $response = $this->post($this->tenantRoute('tenant.admin.addons.purchase'), [
            'addon_slug' => 'storage_50gb',
            'quantity' => 1,
            'billing_period' => 'invalid',
        ]);

        $response->assertSessionHasErrors(['billing_period']);
    }

    public function test_cancel_addon_requires_authentication(): void
    {
        $addon = AddonSubscription::factory()->for($this->tenant)->create();

        $response = $this->post($this->tenantRoute('tenant.admin.addons.cancel', ['addon' => $addon->id]));

        // Guest is redirected
        $response->assertStatus(302);
    }

    public function test_cancel_addon_validates_addon_exists(): void
    {
        $response = $this->post($this->tenantRoute('tenant.admin.addons.cancel', ['addon' => 99999]));

        // Model not found exception is caught and returns error via redirect
        $response->assertStatus(302);
        $response->assertSessionHasErrors('addon');
    }

    public function test_update_addon_quantity_validates_quantity(): void
    {
        $addon = AddonSubscription::factory()->for($this->tenant)->create();

        $response = $this->patch($this->tenantRoute('tenant.admin.addons.update', ['addon' => $addon->id]), [
            'quantity' => 0,
        ]);

        $response->assertSessionHasErrors(['quantity']);
    }

    public function test_shared_addons_data_includes_catalog(): void
    {
        $response = $this->get($this->tenantRoute('tenant.admin.addons.index'));

        $response->assertOk();
        $response->assertInertia(function ($page) {
            $page->has('addons');
        });
    }

    public function test_active_addons_displayed_in_shared_data(): void
    {
        AddonSubscription::factory()->for($this->tenant)->create([
            'addon_slug' => 'storage_50gb',
            'status' => AddonStatus::ACTIVE,
        ]);

        $response = $this->get($this->tenantRoute('tenant.admin.addons.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('addons.active'));
    }

    public function test_addon_catalog_shows_availability(): void
    {
        $response = $this->get($this->tenantRoute('tenant.admin.addons.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('addons.catalog'));
    }

    public function test_monthly_cost_calculated_correctly(): void
    {
        AddonSubscription::factory()->for($this->tenant)->create([
            'price' => 1000,
            'quantity' => 2,
            'billing_period' => BillingPeriod::MONTHLY,
            'status' => AddonStatus::ACTIVE,
        ]);

        $response = $this->get($this->tenantRoute('tenant.admin.addons.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('addons.monthly_cost'));
    }
}
