<?php

namespace Tests\Feature\Central;

use App\Models\Central\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    /**
     * Helper to confirm password before 2FA actions.
     */
    protected function confirmPassword(): void
    {
        $this->actingAs($this->admin, 'central')
            ->post(route('central.admin.auth.confirm-password.store'), [
                'password' => 'password',
            ]);
    }

    public function test_two_factor_settings_page_can_be_rendered(): void
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        $this->confirmPassword();

        $response = $this->actingAs($this->admin, 'central')
            ->get(route('central.admin.settings.two-factor.show'));

        $response->assertOk();
    }

    public function test_two_factor_settings_page_requires_password_confirmation(): void
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        // Without password confirmation, should redirect to confirm password page
        $response = $this->actingAs($this->admin, 'central')
            ->get(route('central.admin.settings.two-factor.show'));

        $response->assertRedirect(route('central.admin.auth.confirm-password'));
    }

    public function test_two_factor_can_be_enabled(): void
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        $this->confirmPassword();

        $response = $this->actingAs($this->admin, 'central')
            ->post(route('central.admin.settings.two-factor.enable'));

        $response->assertRedirect();
        $response->assertSessionHas('status', 'two-factor-authentication-enabled');

        $this->admin->refresh();
        $this->assertNotNull($this->admin->two_factor_secret);
        $this->assertNotNull($this->admin->two_factor_recovery_codes);
    }

    public function test_two_factor_can_be_confirmed(): void
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        $this->confirmPassword();

        // First enable 2FA
        $this->actingAs($this->admin, 'central')
            ->post(route('central.admin.settings.two-factor.enable'));

        $this->admin->refresh();

        // Generate a valid code using the secret
        $secret = decrypt($this->admin->two_factor_secret);
        $google2fa = new \PragmaRX\Google2FA\Google2FA;
        $code = $google2fa->getCurrentOtp($secret);

        $response = $this->actingAs($this->admin, 'central')
            ->post(route('central.admin.settings.two-factor.confirm'), [
                'code' => $code,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'two-factor-authentication-confirmed');

        $this->admin->refresh();
        $this->assertNotNull($this->admin->two_factor_confirmed_at);
    }

    public function test_two_factor_can_be_disabled(): void
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        $this->confirmPassword();

        // Enable 2FA first
        $this->admin->forceFill([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $response = $this->actingAs($this->admin, 'central')
            ->delete(route('central.admin.settings.two-factor.disable'));

        $response->assertRedirect();
        $response->assertSessionHas('status', 'two-factor-authentication-disabled');

        $this->admin->refresh();
        $this->assertNull($this->admin->two_factor_secret);
        $this->assertNull($this->admin->two_factor_recovery_codes);
        $this->assertNull($this->admin->two_factor_confirmed_at);
    }

    public function test_qr_code_can_be_retrieved(): void
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        $this->confirmPassword();

        // Enable 2FA first
        $this->actingAs($this->admin, 'central')
            ->post(route('central.admin.settings.two-factor.enable'));

        $response = $this->actingAs($this->admin, 'central')
            ->getJson(route('central.admin.settings.two-factor.qr-code'));

        $response->assertOk();
        $response->assertJsonStructure(['svg', 'url']);
    }

    public function test_qr_code_returns_empty_when_2fa_not_enabled(): void
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        $this->confirmPassword();

        $response = $this->actingAs($this->admin, 'central')
            ->getJson(route('central.admin.settings.two-factor.qr-code'));

        $response->assertOk();
        $response->assertJson([]);
    }

    public function test_recovery_codes_can_be_retrieved(): void
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        $this->confirmPassword();

        // Enable 2FA first
        $this->admin->forceFill([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2', 'code3'])),
        ])->save();

        $response = $this->actingAs($this->admin, 'central')
            ->getJson(route('central.admin.settings.two-factor.recovery-codes'));

        $response->assertOk();
        $response->assertJson(['code1', 'code2', 'code3']);
    }

    public function test_recovery_codes_can_be_regenerated(): void
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        $this->confirmPassword();

        // Enable 2FA first
        $oldCodes = ['old-code1', 'old-code2'];
        $this->admin->forceFill([
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode($oldCodes)),
        ])->save();

        $response = $this->actingAs($this->admin, 'central')
            ->post(route('central.admin.settings.two-factor.recovery-codes.store'));

        $response->assertRedirect();
        $response->assertSessionHas('status', 'recovery-codes-generated');

        $this->admin->refresh();
        $newCodes = json_decode(decrypt($this->admin->two_factor_recovery_codes), true);
        $this->assertNotEquals($oldCodes, $newCodes);
        $this->assertCount(8, $newCodes);
    }

    public function test_two_factor_routes_require_authentication(): void
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        $this->get(route('central.admin.settings.two-factor.show'))
            ->assertRedirect(route('central.admin.auth.login'));

        $this->post(route('central.admin.settings.two-factor.enable'))
            ->assertRedirect(route('central.admin.auth.login'));

        $this->delete(route('central.admin.settings.two-factor.disable'))
            ->assertRedirect(route('central.admin.auth.login'));
    }
}
