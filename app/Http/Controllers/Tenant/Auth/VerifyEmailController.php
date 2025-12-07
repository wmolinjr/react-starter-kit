<?php

namespace App\Http\Controllers\Tenant\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles email verification for tenant users.
 *
 * Custom implementation replacing Laravel Fortify.
 * Uses 'tenant' guard for authentication.
 */
class VerifyEmailController extends Controller
{
    /**
     * Display the email verification prompt.
     */
    public function notice(Request $request): Response|RedirectResponse
    {
        $user = $request->user('tenant');

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('tenant.admin.dashboard'));
        }

        return Inertia::render('tenant/auth/verify-email', [
            'status' => session('status'),
        ]);
    }

    /**
     * Mark the authenticated user's email address as verified.
     */
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return redirect()->route('tenant.auth.verification.notice')
                ->withErrors(['email' => __('The verification link is invalid.')]);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('tenant.admin.dashboard').'?verified=1');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended(route('tenant.admin.dashboard').'?verified=1');
    }

    /**
     * Send a new email verification notification.
     */
    public function send(Request $request): RedirectResponse
    {
        $user = $request->user('tenant');

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('tenant.admin.dashboard'));
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
