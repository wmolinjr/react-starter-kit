<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles password reset link requests for tenant users.
 *
 * Custom implementation replacing Laravel Fortify.
 * Uses 'tenant_users' password broker.
 */
class ForgotPasswordController extends Controller
{
    /**
     * Display the forgot password view.
     */
    public function create(): Response
    {
        return Inertia::render('tenant/auth/forgot-password', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Send password reset link using the 'tenant_users' broker
        $status = Password::broker('tenant_users')->sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', __($status));
        }

        return back()->withErrors(['email' => __($status)]);
    }
}
