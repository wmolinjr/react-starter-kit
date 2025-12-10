<?php

namespace App\Http\Controllers\Central\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles password reset link requests for central admins.
 *
 * Custom implementation for Central admins since Fortify only works with 'tenant' guard.
 */
class ForgotPasswordController extends Controller
{
    /**
     * Display the forgot password view.
     */
    public function create(): Response
    {
        return Inertia::render('central/auth/forgot-password', [
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

        // Send password reset link using the 'central_users' broker
        $status = Password::broker('central_users')->sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', __($status));
        }

        return back()->withErrors(['email' => __($status)]);
    }
}
