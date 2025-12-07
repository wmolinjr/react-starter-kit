<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\WithTenant;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeTenant();
    }

    public function test_confirm_password_screen_can_be_rendered()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'tenant')->get($this->tenantUrl('/confirm-password'));

        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('tenant/auth/confirm-password')
        );
    }

    public function test_password_confirmation_requires_authentication()
    {
        $response = $this->get($this->tenantUrl('/confirm-password'));

        // Should redirect to login (might include intended URL)
        $response->assertRedirect();
        $this->assertStringContainsString('login', $response->headers->get('Location'));
    }

    public function test_password_can_be_confirmed()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'tenant')->post($this->tenantUrl('/confirm-password'), [
            'password' => 'password',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('tenant.admin.user-settings.two-factor.show'));
    }

    public function test_password_is_not_confirmed_with_invalid_password()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'tenant')->post($this->tenantUrl('/confirm-password'), [
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('password');
    }
}
