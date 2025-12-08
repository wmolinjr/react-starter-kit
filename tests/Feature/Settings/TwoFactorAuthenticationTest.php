<?php

namespace Tests\Feature\Settings;

use App\Models\Tenant\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TenantTestCase;

class TwoFactorAuthenticationTest extends TenantTestCase
{
    public function test_two_factor_settings_page_can_be_rendered()
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        // Disable 2FA on test user
        $this->user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->actingAs($this->user, 'tenant')
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get($this->tenantRoute('tenant.admin.user-settings.two-factor.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('tenant/admin/user-settings/two-factor')
                ->where('twoFactorEnabled', false)
            );
    }

    public function test_two_factor_settings_page_requires_password_confirmation_when_enabled()
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $response = $this
            ->actingAs($this->user, 'tenant')
            ->get($this->tenantRoute('tenant.admin.user-settings.two-factor.show'));

        $response->assertRedirect($this->tenantRoute('tenant.admin.auth.confirm-password'));
    }

    public function test_two_factor_settings_page_does_not_requires_password_confirmation_when_disabled()
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => false,
        ]);

        $this->actingAs($this->user, 'tenant')
            ->get($this->tenantRoute('tenant.admin.user-settings.two-factor.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tenant/admin/user-settings/two-factor')
            );
    }

    public function test_two_factor_settings_page_returns_forbidden_response_when_two_factor_is_disabled()
    {
        if (! Features::canManageTwoFactorAuthentication()) {
            $this->markTestSkipped('Two-factor authentication is not enabled.');
        }

        config(['fortify.features' => []]);

        $this->actingAs($this->user, 'tenant')
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get($this->tenantRoute('tenant.admin.user-settings.two-factor.show'))
            ->assertForbidden();
    }
}
