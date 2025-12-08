<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\WithTenant;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeTenant();
    }

    public function test_reset_password_link_screen_can_be_rendered()
    {
        $response = $this->get($this->tenantRoute('tenant.admin.auth.password.request'));

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post($this->tenantRoute('tenant.admin.auth.password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post($this->tenantRoute('tenant.admin.auth.password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get($this->tenantRoute('tenant.admin.auth.password.reset', ['token' => $notification->token]));

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post($this->tenantRoute('tenant.admin.auth.password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post($this->tenantRoute('tenant.admin.auth.password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('tenant.admin.auth.login'));

            return true;
        });
    }

    public function test_password_cannot_be_reset_with_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->post($this->tenantRoute('tenant.admin.auth.password.update'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
