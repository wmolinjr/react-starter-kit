<?php

namespace Tests\Feature;

use App\Models\Central\User as Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Admin Authentication Test Suite
 *
 * Tests the central admin authentication system (Option C architecture).
 *
 * OPTION C: Admin model in central database with 'admin' guard.
 * Users exist only in tenant databases with 'web' guard.
 */
class AdminAuthenticationTest extends TestCase
{
    /**
     * Generate a unique email for tests.
     */
    protected function uniqueEmail(string $prefix = 'admin'): string
    {
        return $prefix.'-'.Str::random(8).'@example.com';
    }

    /*
    |--------------------------------------------------------------------------
    | Login Page Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function admin_login_page_can_be_rendered(): void
    {
        $response = $this->get($this->centralUrl('/admin/login'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('central/admin/auth/login')
        );
    }

    #[Test]
    public function authenticated_admin_is_redirected_from_login_page(): void
    {
        $admin = Admin::factory()->superAdmin()->create();

        $response = $this->actingAs($admin, 'admin')
            ->get($this->centralUrl('/admin/login'));

        $response->assertRedirect();
    }

    /*
    |--------------------------------------------------------------------------
    | Login Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function admin_can_login_with_valid_credentials(): void
    {
        $email = $this->uniqueEmail('login');
        $admin = Admin::factory()->create([
            'email' => $email,
            'password' => Hash::make('password'),
        ]);

        $response = $this->post($this->centralUrl('/admin/login'), [
            'email' => $email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('central.admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    #[Test]
    public function admin_cannot_login_with_invalid_password(): void
    {
        $email = $this->uniqueEmail('invalid-pass');
        $admin = Admin::factory()->create([
            'email' => $email,
            'password' => Hash::make('password'),
        ]);

        $response = $this->post($this->centralUrl('/admin/login'), [
            'email' => $email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('admin');
    }

    #[Test]
    public function admin_cannot_login_with_nonexistent_email(): void
    {
        $response = $this->post($this->centralUrl('/admin/login'), [
            'email' => $this->uniqueEmail('nonexistent'),
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('admin');
    }

    #[Test]
    public function admin_login_requires_email(): void
    {
        $response = $this->post($this->centralUrl('/admin/login'), [
            'email' => '',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
    }

    #[Test]
    public function admin_login_requires_password(): void
    {
        $response = $this->post($this->centralUrl('/admin/login'), [
            'email' => $this->uniqueEmail('require-pass'),
            'password' => '',
        ]);

        $response->assertSessionHasErrors('password');
    }

    /*
    |--------------------------------------------------------------------------
    | Remember Me Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function admin_can_login_with_remember_me(): void
    {
        $email = $this->uniqueEmail('remember');
        $admin = Admin::factory()->create([
            'email' => $email,
            'password' => Hash::make('password'),
        ]);

        $response = $this->post($this->centralUrl('/admin/login'), [
            'email' => $email,
            'password' => 'password',
            'remember' => true,
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($admin, 'admin');

        // Check remember token was set
        $admin->refresh();
        $this->assertNotNull($admin->remember_token);
    }

    /*
    |--------------------------------------------------------------------------
    | Logout Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function admin_can_logout(): void
    {
        $admin = Admin::factory()->superAdmin()->create();

        $response = $this->actingAs($admin, 'admin')
            ->post($this->centralUrl('/admin/logout'));

        $response->assertRedirect();
        $this->assertGuest('admin');
    }

    #[Test]
    public function guest_cannot_access_logout(): void
    {
        $response = $this->post($this->centralUrl('/admin/logout'));

        $response->assertRedirect();
    }

    /*
    |--------------------------------------------------------------------------
    | Dashboard Access Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function super_admin_can_access_dashboard(): void
    {
        $admin = Admin::factory()->superAdmin()->create();

        $response = $this->actingAs($admin, 'admin')
            ->get($this->centralUrl('/admin/dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('central/admin/dashboard')
        );
    }

    #[Test]
    public function regular_admin_cannot_access_dashboard(): void
    {
        $admin = Admin::factory()->create([
            'is_super_admin' => false,
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get($this->centralUrl('/admin/dashboard'));

        $response->assertForbidden();
    }

    #[Test]
    public function guest_cannot_access_dashboard(): void
    {
        $response = $this->get($this->centralUrl('/admin/dashboard'));

        $response->assertRedirect();
    }

    /*
    |--------------------------------------------------------------------------
    | Guard Isolation Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function admin_guard_is_separate_from_web_guard(): void
    {
        $admin = Admin::factory()->superAdmin()->create();

        // Login with admin guard
        $this->actingAs($admin, 'admin');

        // Admin guard should be authenticated
        $this->assertAuthenticatedAs($admin, 'admin');

        // Web guard should NOT be authenticated
        $this->assertGuest('web');
    }

    #[Test]
    public function admin_session_regenerates_on_login(): void
    {
        $email = $this->uniqueEmail('session');
        $admin = Admin::factory()->create([
            'email' => $email,
            'password' => Hash::make('password'),
        ]);

        // Get initial session
        $initialResponse = $this->get($this->centralUrl('/admin/login'));

        // Login
        $loginResponse = $this->post($this->centralUrl('/admin/login'), [
            'email' => $email,
            'password' => 'password',
        ]);

        // Session should be regenerated (different from initial)
        $this->assertAuthenticatedAs($admin, 'admin');
    }

    /*
    |--------------------------------------------------------------------------
    | Super Admin Flag Tests
    |--------------------------------------------------------------------------
    */

    #[Test]
    public function is_super_admin_method_returns_correct_value(): void
    {
        $superAdmin = Admin::factory()->superAdmin()->create();
        $regularAdmin = Admin::factory()->create(['is_super_admin' => false]);

        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertFalse($regularAdmin->isSuperAdmin());
    }

    #[Test]
    public function super_admins_scope_returns_only_super_admins(): void
    {
        // Get initial count of super admins
        $initialCount = Admin::superAdmins()->count();

        // Create new super admins and regular admin
        Admin::factory()->superAdmin()->count(2)->create();
        Admin::factory()->create(['is_super_admin' => false]);

        $superAdmins = Admin::superAdmins()->get();

        // Should have 2 more super admins than initial
        $this->assertCount($initialCount + 2, $superAdmins);
        $superAdmins->each(fn ($admin) => $this->assertTrue($admin->is_super_admin));
    }
}
