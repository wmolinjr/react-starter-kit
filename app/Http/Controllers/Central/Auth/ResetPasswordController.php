<?php

namespace App\Http\Controllers\Central\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles password reset for central admins.
 *
 * Custom implementation for Central admins since Fortify only works with 'tenant' guard.
 */
class ResetPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request, string $token): Response
    {
        return Inertia::render('central/auth/reset-password', [
            'email' => $request->email,
            'token' => $token,
        ]);
    }

    /**
     * Handle an incoming new password request.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Reset password using the 'central_users' broker
        $status = Password::broker('central_users')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('central.admin.auth.login')->with('status', __($status));
        }

        return back()->withErrors(['email' => __($status)]);
    }
}
