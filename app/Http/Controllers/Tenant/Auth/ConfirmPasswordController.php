<?php

namespace App\Http\Controllers\Tenant\Auth;

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
 * Handles password confirmation for tenant users.
 *
 * Custom implementation replacing Laravel Fortify.
 * Uses 'tenant' guard for authentication.
 */
class ConfirmPasswordController extends Controller
{
    /**
     * Show the confirm password view.
     */
    public function show(Request $request): Response
    {
        return Inertia::render('tenant/auth/confirm-password');
    }

    /**
     * Confirm the user's password.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        $user = Auth::guard('tenant')->user();

        if (! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', Date::now()->unix());

        return redirect()->intended(route('tenant.admin.user-settings.two-factor.show'));
    }
}
