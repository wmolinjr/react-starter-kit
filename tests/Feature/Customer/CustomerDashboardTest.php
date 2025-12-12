<?php

namespace Tests\Feature\Customer;

use App\Models\Central\Customer;
use App\Models\Central\Plan;
use App\Models\Central\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_dashboard_requires_authentication(): void
    {
        $response = $this->get(route('central.account.dashboard'));

        $response->assertRedirect(route('central.account.login', absolute: false));
    }

    public function test_customer_dashboard_requires_verified_email(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $response = $this->actingAs($customer, 'customer')
            ->get(route('central.account.dashboard'));

        $response->assertRedirect(route('central.account.verification.notice', absolute: false));
    }

    public function test_verified_customer_can_view_dashboard(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->get(route('central.account.dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('customer/dashboard')
            ->has('customer')
            ->has('tenants')
            ->has('stats')
        );
    }

    public function test_dashboard_shows_customer_tenants(): void
    {
        $plan = Plan::factory()->create(['name' => 'Test Plan']);
        $customer = Customer::factory()->create([
            'email_verified_at' => now(),
        ]);

        $tenant = Tenant::create([
            'name' => 'Test Workspace',
            'slug' => 'test-workspace',
            'customer_id' => $customer->id,
            'plan_id' => $plan->id,
        ]);

        $tenant->domains()->create([
            'domain' => 'test-workspace.test',
            'is_primary' => true,
        ]);

        $customer->tenants()->attach($tenant);

        $response = $this->actingAs($customer, 'customer')
            ->get(route('central.account.dashboard'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('customer/dashboard')
            ->where('stats.tenant_count', 1)
        );
    }

    public function test_dashboard_shows_stats(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->get(route('central.account.dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->has('stats.tenant_count')
            ->has('stats.active_subscriptions')
            ->has('stats.pending_transfers')
            ->has('stats.total_monthly_billing')
        );
    }
}
