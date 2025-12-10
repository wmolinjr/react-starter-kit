<?php

namespace App\Http\Controllers\Central\Auth;

use App\Http\Controllers\Controller;
use App\Models\Central\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Handles email verification for central admins.
 *
 * Custom implementation for Central admins since Fortify only works with 'tenant' guard.
 */
class VerifyEmailController extends Controller
{
    /**
     * Display the email verification prompt.
     */
    public function notice(Request $request): Response|RedirectResponse
    {
        $user = $request->user('central');

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('central.admin.dashboard'));
        }

        return Inertia::render('central/auth/verify-email', [
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
            return redirect()->route('central.admin.auth.verification.notice')
                ->withErrors(['email' => __('The verification link is invalid.')]);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('central.admin.dashboard').'?verified=1');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended(route('central.admin.dashboard').'?verified=1');
    }

    /**
     * Send a new email verification notification.
     */
    public function send(Request $request): RedirectResponse
    {
        $user = $request->user('central');

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended(route('central.admin.dashboard'));
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
