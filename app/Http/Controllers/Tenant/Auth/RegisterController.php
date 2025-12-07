<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

/**
 * Handles user registration for tenant.
 *
 * Custom implementation replacing Laravel Fortify.
 * Uses 'tenant' guard for authentication after registration.
 */
class RegisterController extends Controller
{
    /**
     * Display the registration form.
     */
    public function create(): Response|RedirectResponse
    {
        // Check if registration is enabled
        if (! Features::enabled(Features::registration())) {
            return redirect()->route('tenant.auth.login');
        }

        return Inertia::render('tenant/auth/register');
    }

    /**
     * Handle an incoming registration request.
     */
    public function store(Request $request): RedirectResponse
    {
        // Check if registration is enabled
        if (! Features::enabled(Features::registration())) {
            return redirect()->route('tenant.auth.login');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
        ]);

        event(new Registered($user));

        Auth::guard('tenant')->login($user);

        return redirect()->route('tenant.admin.dashboard');
    }
}
