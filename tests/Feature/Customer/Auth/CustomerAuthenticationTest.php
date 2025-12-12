<?php

namespace Tests\Feature\Customer\Auth;

use App\Models\Central\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure customer guard is configured
        $this->assertArrayHasKey('customer', config('auth.guards'));
    }

    public function test_customer_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('central.account.login'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('customer/auth/login'));
    }

    public function test_customers_can_authenticate_using_the_login_screen(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $response = $this->post(route('central.account.login'), [
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($customer, 'customer');
        $response->assertRedirect(route('central.account.dashboard', absolute: false));
    }

    public function test_customers_can_not_authenticate_with_invalid_password(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response = $this->post(route('central.account.login'), [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertGuest('customer');
    }

    public function test_customers_can_logout(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->post(route('central.account.logout'));

        $response->assertRedirect(route('central.account.login', absolute: false));

        // After redirect, user should be logged out
        // Test by trying to access a protected route
        $this->get(route('central.account.dashboard'))->assertRedirect(route('central.account.login', absolute: false));
    }

    public function test_customer_registration_screen_can_be_rendered(): void
    {
        $response = $this->get(route('central.account.register'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('customer/auth/register'));
    }

    public function test_new_customers_can_register(): void
    {
        $response = $this->post(route('central.account.register'), [
            'name' => 'Test Customer',
            'email' => 'newcustomer@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertDatabaseHas('customers', [
            'email' => 'newcustomer@example.com',
        ]);

        $this->assertAuthenticated('customer');
    }

    public function test_customer_cannot_register_with_duplicate_email(): void
    {
        Customer::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $response = $this->post(route('central.account.register'), [
            'name' => 'Test Customer',
            'email' => 'existing@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
