<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant\User;
use Laravel\Fortify\Features;
use Tests\Concerns\WithTenant;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeTenant();
    }

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get($this->tenantUrl('/login'));

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->withoutTwoFactor()->create();

        $response = $this->post($this->tenantUrl('/login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        // Tenant users are redirected to admin dashboard after successful login
        $response->assertRedirect(route('tenant.admin.dashboard', absolute: false));
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge()
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        $user = User::factory()->create();

        $user->forceFill([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $response = $this->post($this->tenantUrl('/login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('tenant.auth.two-factor.challenge'));
        $response->assertSessionHas('tenant.login.id', $user->id);
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $response = $this->post($this->tenantUrl('/login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        // Should stay on login page with validation error
        $response->assertSessionHasErrors('email');
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'tenant')->post($this->tenantUrl('/logout'));

        $response->assertRedirect(route('tenant.auth.login'));
    }
}
