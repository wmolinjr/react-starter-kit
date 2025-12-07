<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

/**
 * Handles authentication for tenant users.
 *
 * Custom implementation replacing Laravel Fortify.
 * Uses 'tenant' guard for authentication (tenant database).
 */
class LoginController extends Controller
{
    /**
     * Display the login form.
     */
    public function create(): Response
    {
        return Inertia::render('tenant/auth/login', [
            'status' => session('status'),
            'canResetPassword' => true,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Find user by email
        $user = User::where('email', $credentials['email'])->first();

        // Validate credentials
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // Check if user has 2FA enabled
        if (Features::enabled(Features::twoFactorAuthentication()) &&
            $user->hasEnabledTwoFactorAuthentication()) {
            // Store user ID in session for 2FA challenge
            $request->session()->put('tenant.login.id', $user->id);
            $request->session()->put('tenant.login.remember', $request->boolean('remember'));

            return redirect()->route('tenant.auth.two-factor.challenge');
        }

        // Login with tenant guard (no 2FA)
        Auth::guard('tenant')->login($user, $request->boolean('remember'));

        // Regenerate session for security
        $request->session()->regenerate();

        // Redirect to tenant dashboard
        return redirect()->intended(route('tenant.admin.dashboard'));
    }
}
