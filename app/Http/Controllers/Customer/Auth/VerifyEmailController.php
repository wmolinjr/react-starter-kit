<?php

namespace App\Http\Controllers\Customer\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VerifyEmailController extends Controller
{
    /**
     * Display the email verification notice.
     */
    public function notice(Request $request): RedirectResponse|Response
    {
        return $request->user('customer')->hasVerifiedEmail()
            ? redirect()->intended(route('central.account.dashboard'))
            : Inertia::render('customer/auth/verify-email', [
                'status' => session('status'),
            ]);
    }

    /**
     * Mark the authenticated user's email address as verified.
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user('customer')->hasVerifiedEmail()) {
            return redirect()->intended(route('central.account.dashboard').'?verified=1');
        }

        if ($request->user('customer')->markEmailAsVerified()) {
            event(new Verified($request->user('customer')));
        }

        return redirect()->intended(route('central.account.dashboard').'?verified=1');
    }

    /**
     * Send a new email verification notification.
     */
    public function send(Request $request): RedirectResponse
    {
        if ($request->user('customer')->hasVerifiedEmail()) {
            return redirect()->intended(route('central.account.dashboard'));
        }

        $request->user('customer')->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
