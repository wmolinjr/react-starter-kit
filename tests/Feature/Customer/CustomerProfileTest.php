<?php

namespace Tests\Feature\Customer;

use App\Models\Central\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_profile_page_is_displayed(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->get(route('customer.profile.edit'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('customer/profile/edit')
            ->has('customer')
        );
    }

    public function test_customer_profile_information_can_be_updated(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->patch(route('customer.profile.update'), [
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
                'phone' => '+55 11 99999-9999',
                'locale' => 'en',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $customer->refresh();

        $this->assertSame('Updated Name', $customer->name);
        $this->assertSame('updated@example.com', $customer->email);
        $this->assertSame('+55 11 99999-9999', $customer->phone);
        $this->assertSame('en', $customer->locale);
    }

    public function test_customer_email_verification_status_is_unchanged_when_email_is_unchanged(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->patch(route('customer.profile.update'), [
                'name' => 'Updated Name',
                'email' => $customer->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertNotNull($customer->refresh()->email_verified_at);
    }

    public function test_customer_can_update_password(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => now(),
            'password' => 'old-password',
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->patch(route('customer.profile.password'), [
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertTrue(password_verify('new-password', $customer->refresh()->password));
    }

    public function test_customer_cannot_update_password_with_incorrect_current_password(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => now(),
            'password' => 'old-password',
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->patch(route('customer.profile.password'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response->assertSessionHasErrors('current_password');
    }

    public function test_customer_can_update_billing_address(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->patch(route('customer.profile.billing'), [
                'billing_address' => [
                    'line1' => '123 Main St',
                    'line2' => 'Apt 4B',
                    'city' => 'New York',
                    'state' => 'NY',
                    'postal_code' => '10001',
                    'country' => 'US',
                ],
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $customer->refresh();

        $this->assertSame('123 Main St', $customer->billing_address['line1']);
        $this->assertSame('New York', $customer->billing_address['city']);
    }

    public function test_customer_can_delete_their_account(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => now(),
            'password' => 'password',
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->delete(route('customer.profile.destroy'), [
                'password' => 'password',
            ]);

        $response->assertRedirect(route('customer.login', absolute: false));

        $this->assertGuest('customer');
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_customer_cannot_delete_account_with_wrong_password(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => now(),
            'password' => 'password',
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->delete(route('customer.profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response->assertSessionHasErrors('password');

        $this->assertNotSoftDeleted('customers', ['id' => $customer->id]);
    }
}
