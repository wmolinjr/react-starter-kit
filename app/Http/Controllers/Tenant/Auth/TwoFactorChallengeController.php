<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/**
 * Handles two-factor authentication challenge for tenant users during login.
 *
 * Custom implementation replacing Laravel Fortify.
 * Uses 'tenant' guard for authentication.
 */
class TwoFactorChallengeController extends Controller
{
    public function __construct(
        protected TwoFactorAuthenticationProvider $provider
    ) {}

    /**
     * Display the two-factor challenge view.
     */
    public function create(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('tenant.login.id')) {
            return redirect()->route('tenant.admin.auth.login');
        }

        return Inertia::render('tenant/auth/two-factor-challenge');
    }

    /**
     * Attempt to authenticate a new session using the two factor authentication code.
     */
    public function store(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('tenant.login.id');

        if (! $userId) {
            return redirect()->route('tenant.admin.auth.login');
        }

        $user = User::find($userId);

        if (! $user) {
            return redirect()->route('tenant.admin.auth.login');
        }

        // Validate either code or recovery_code
        if ($code = $request->input('code')) {
            if (! $this->provider->verify(decrypt($user->two_factor_secret), $code)) {
                throw ValidationException::withMessages([
                    'code' => [__('The provided two factor authentication code was invalid.')],
                ]);
            }
        } elseif ($recoveryCode = $request->input('recovery_code')) {
            if (! $user->two_factor_recovery_codes) {
                throw ValidationException::withMessages([
                    'recovery_code' => [__('The provided recovery code was invalid.')],
                ]);
            }

            $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);

            if (! in_array($recoveryCode, $recoveryCodes)) {
                throw ValidationException::withMessages([
                    'recovery_code' => [__('The provided recovery code was invalid.')],
                ]);
            }

            // Remove used recovery code
            $user->forceFill([
                'two_factor_recovery_codes' => encrypt(json_encode(
                    array_values(array_diff($recoveryCodes, [$recoveryCode]))
                )),
            ])->save();
        } else {
            throw ValidationException::withMessages([
                'code' => [__('Please provide a two factor authentication code or recovery code.')],
            ]);
        }

        // Clear the session data
        $request->session()->forget('tenant.login.id');
        $request->session()->forget('tenant.login.remember');

        // Login the user
        Auth::guard('tenant')->login(
            $user,
            $request->session()->get('tenant.login.remember', false)
        );

        $request->session()->regenerate();

        return redirect()->intended(route('tenant.admin.dashboard'));
    }
}
