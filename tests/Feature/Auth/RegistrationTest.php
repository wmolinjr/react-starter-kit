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
        $response = $this->get($this->tenantUrl('/register'));

        $response->assertStatus(200);
    }

    public function test_new_users_can_register()
    {
        $response = $this->post($this->tenantUrl('/register'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // Verify user was created in tenant database
        $user = \App\Models\Tenant\User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user, 'User should be created in tenant database');

        // Tenant users are redirected to admin dashboard after registration
        $response->assertRedirect(route('tenant.admin.dashboard', absolute: false));
    }
}
