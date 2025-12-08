<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\Concerns\WithTenant;
use Tests\TestCase;

class TwoFactorChallengeTest extends TestCase
{
    use WithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializeTenant();
    }

    public function test_two_factor_challenge_redirects_to_login_when_no_session(): void
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        $response = $this->get($this->tenantRoute('tenant.admin.auth.two-factor.challenge'));

        $response->assertRedirect(route('tenant.admin.auth.login'));
    }

    public function test_two_factor_challenge_can_be_rendered(): void
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

        // Login first to set session
        $this->post($this->tenantRoute('tenant.admin.auth.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->get($this->tenantRoute('tenant.admin.auth.two-factor.challenge'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tenant/auth/two-factor-challenge')
            );
    }
}
