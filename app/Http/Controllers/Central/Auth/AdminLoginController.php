<?php

namespace App\Http\Controllers\Central\Auth;

use App\Http\Controllers\Controller;
use App\Models\Central\User as CentralUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

/**
 * Handles authentication for central admins.
 *
 * TENANT-ONLY ARCHITECTURE (Option C):
 * - Uses 'central' guard for authentication (central database)
 * - Separate from tenant user authentication (Fortify)
 * - Admins can impersonate tenants via ImpersonationController
 * - Supports two-factor authentication challenge
 */
class AdminLoginController extends Controller
{
    /**
     * Display the admin login form.
     */
    public function create(): Response
    {
        return Inertia::render('central/admin/auth/login', [
            'status' => session('status'),
            'canResetPassword' => true,
        ]);
    }

    /**
     * Handle an incoming admin authentication request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Find admin by email
        $admin = CentralUser::where('email', $credentials['email'])->first();

        // Validate credentials
        if (! $admin || ! Hash::check($credentials['password'], $admin->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // Check if admin has 2FA enabled
        if (Features::enabled(Features::twoFactorAuthentication()) &&
            $admin->hasEnabledTwoFactorAuthentication()) {
            // Store admin ID in session for 2FA challenge
            $request->session()->put('central_admin.login.id', $admin->id);
            $request->session()->put('central_admin.login.remember', $request->boolean('remember'));

            return redirect()->route('central.admin.auth.two-factor.challenge');
        }

        // Login with central guard (no 2FA)
        Auth::guard('central')->login($admin, $request->boolean('remember'));

        // Regenerate session for security
        $request->session()->regenerate();

        // Redirect to admin dashboard
        return redirect()->intended(route('central.admin.dashboard'));
    }
}
