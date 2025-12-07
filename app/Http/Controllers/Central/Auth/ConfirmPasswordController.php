<?php

namespace App\Http\Controllers\Central\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Password confirmation for Central administrators.
 *
 * Central admins use a different guard ('central') than tenant users,
 * so we need a custom password confirmation flow separate from Fortify's.
 */
class ConfirmPasswordController extends Controller
{
    /**
     * Show the password confirmation page.
     */
    public function show(Request $request): Response
    {
        return Inertia::render('central/auth/confirm-password');
    }

    /**
     * Confirm the user's password.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = Auth::guard('central')->user();

        if (! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', Date::now()->unix());

        return redirect()->intended(route('central.admin.settings.two-factor.show'));
    }
}
