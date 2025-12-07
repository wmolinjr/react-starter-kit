<?php

namespace App\Http\Controllers\Central\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\Settings\TwoFactorAuthenticationRequest;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

/**
 * Two-Factor Authentication settings for Central administrators.
 *
 * Central admins don't use Fortify for authentication (they use the central guard),
 * so the password.confirm middleware doesn't work for them. This controller
 * simply displays the 2FA settings page without password confirmation.
 */
class TwoFactorAuthenticationController extends Controller
{
    /**
     * Show the user's two-factor authentication settings page.
     */
    public function show(TwoFactorAuthenticationRequest $request): Response
    {
        $request->ensureStateIsValid();

        return Inertia::render('central/admin/user-settings/two-factor', [
            'twoFactorEnabled' => $request->user()->hasEnabledTwoFactorAuthentication(),
            'requiresConfirmation' => Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
        ]);
    }
}
