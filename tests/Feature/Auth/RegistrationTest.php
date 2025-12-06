<?php

namespace Tests\Feature\Auth;

use Tests\Concerns\WithTenant;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeTenant();
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertStatus(200);
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        // Tenant users are redirected to admin dashboard
        $response->assertRedirect(route('tenant.admin.dashboard', absolute: false));
    }
}
