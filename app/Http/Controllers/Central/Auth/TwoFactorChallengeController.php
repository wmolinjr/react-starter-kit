<?php

namespace App\Http\Controllers\Central\Auth;

use App\Http\Controllers\Controller;
use App\Models\Central\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

/**
 * Handles two-factor authentication challenge for central admins during login.
 *
 * Custom implementation for Central admins since Fortify only works with 'tenant' guard.
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
        if (! $request->session()->has('central_admin.login.id')) {
            return redirect()->route('central.admin.auth.login');
        }

        return Inertia::render('central/auth/two-factor-challenge');
    }

    /**
     * Attempt to authenticate a new session using the two factor authentication code.
     */
    public function store(Request $request): RedirectResponse
    {
        $adminId = $request->session()->get('central_admin.login.id');

        if (! $adminId) {
            return redirect()->route('central.admin.auth.login');
        }

        $admin = User::find($adminId);

        if (! $admin) {
            return redirect()->route('central.admin.auth.login');
        }

        // Validate either code or recovery_code
        if ($code = $request->input('code')) {
            if (! $this->provider->verify(decrypt($admin->two_factor_secret), $code)) {
                throw ValidationException::withMessages([
                    'code' => [__('The provided two factor authentication code was invalid.')],
                ]);
            }
        } elseif ($recoveryCode = $request->input('recovery_code')) {
            if (! $admin->two_factor_recovery_codes) {
                throw ValidationException::withMessages([
                    'recovery_code' => [__('The provided recovery code was invalid.')],
                ]);
            }

            $recoveryCodes = json_decode(decrypt($admin->two_factor_recovery_codes), true);

            if (! in_array($recoveryCode, $recoveryCodes)) {
                throw ValidationException::withMessages([
                    'recovery_code' => [__('The provided recovery code was invalid.')],
                ]);
            }

            // Remove used recovery code
            $admin->forceFill([
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
        $request->session()->forget('central_admin.login.id');
        $request->session()->forget('central_admin.login.remember');

        // Login the admin
        Auth::guard('central')->login(
            $admin,
            $request->session()->get('central_admin.login.remember', false)
        );

        $request->session()->regenerate();

        return redirect()->intended(route('central.admin.dashboard'));
    }
}
