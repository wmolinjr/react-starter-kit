<?php

namespace Tests\Feature\Central;

use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Mockery;
use Tests\TestCase;

class TwoFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run central migrations
        $this->artisan('migrate', [
            '--path' => 'database/migrations',
            '--database' => 'central',
        ]);
    }

    public function test_two_factor_challenge_page_redirects_without_pending_login(): void
    {
        $response = $this->get(route('central.admin.auth.two-factor.challenge'));

        $response->assertRedirect(route('central.admin.auth.login'));
    }

    public function test_two_factor_challenge_page_can_be_rendered_with_pending_login(): void
    {
        $admin = User::factory()->create();

        // Simulate a pending 2FA login
        session(['central_admin.login.id' => $admin->id]);

        $response = $this->withSession(['central_admin.login.id' => $admin->id])
            ->get(route('central.admin.auth.two-factor.challenge'));

        $response->assertStatus(200);
    }

    public function test_two_factor_challenge_fails_without_pending_login(): void
    {
        $response = $this->post(route('central.admin.auth.two-factor.challenge.store'), [
            'code' => '123456',
        ]);

        $response->assertRedirect(route('central.admin.auth.login'));
    }

    public function test_two_factor_challenge_fails_with_invalid_code(): void
    {
        $admin = $this->createAdminWith2FA();

        // Mock the TwoFactorAuthenticationProvider
        $provider = Mockery::mock(TwoFactorAuthenticationProvider::class);
        $provider->shouldReceive('verify')
            ->once()
            ->andReturn(false);

        $this->app->instance(TwoFactorAuthenticationProvider::class, $provider);

        $response = $this->withSession(['central_admin.login.id' => $admin->id])
            ->post(route('central.admin.auth.two-factor.challenge.store'), [
                'code' => '000000',
            ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_two_factor_challenge_succeeds_with_valid_code(): void
    {
        $admin = $this->createAdminWith2FA();

        // Mock the TwoFactorAuthenticationProvider
        $provider = Mockery::mock(TwoFactorAuthenticationProvider::class);
        $provider->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $this->app->instance(TwoFactorAuthenticationProvider::class, $provider);

        $response = $this->withSession([
            'central_admin.login.id' => $admin->id,
            'central_admin.login.remember' => false,
        ])->post(route('central.admin.auth.two-factor.challenge.store'), [
            'code' => '123456',
        ]);

        $response->assertRedirect(route('central.admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'central');
    }

    public function test_two_factor_challenge_succeeds_with_valid_recovery_code(): void
    {
        $recoveryCodes = ['CODE1-12345', 'CODE2-67890'];
        $admin = $this->createAdminWith2FA($recoveryCodes);

        $response = $this->withSession([
            'central_admin.login.id' => $admin->id,
            'central_admin.login.remember' => false,
        ])->post(route('central.admin.auth.two-factor.challenge.store'), [
            'recovery_code' => 'CODE1-12345',
        ]);

        $response->assertRedirect(route('central.admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'central');

        // Verify recovery code was removed
        $admin->refresh();
        $remainingCodes = json_decode(decrypt($admin->two_factor_recovery_codes), true);
        $this->assertNotContains('CODE1-12345', $remainingCodes);
        $this->assertContains('CODE2-67890', $remainingCodes);
    }

    public function test_two_factor_challenge_fails_with_invalid_recovery_code(): void
    {
        $recoveryCodes = ['CODE1-12345', 'CODE2-67890'];
        $admin = $this->createAdminWith2FA($recoveryCodes);

        $response = $this->withSession([
            'central_admin.login.id' => $admin->id,
        ])->post(route('central.admin.auth.two-factor.challenge.store'), [
            'recovery_code' => 'INVALID-CODE',
        ]);

        $response->assertSessionHasErrors('recovery_code');
    }

    public function test_login_with_2fa_enabled_redirects_to_challenge(): void
    {
        $admin = $this->createAdminWith2FA();

        $response = $this->post(route('central.admin.auth.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('central.admin.auth.two-factor.challenge'));
        $this->assertGuest('central');
    }

    /**
     * Create an admin with 2FA enabled.
     */
    protected function createAdminWith2FA(?array $recoveryCodes = null): User
    {
        $admin = User::factory()->create([
            'password' => bcrypt('password'),
        ]);

        $admin->forceFill([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode($recoveryCodes ?? ['RECOVERY-CODE-1', 'RECOVERY-CODE-2'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $admin;
    }
}
