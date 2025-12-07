<?php

namespace App\Http\Controllers\Central\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\Settings\TwoFactorAuthenticationRequest;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

/**
 * Two-Factor Authentication settings for Central administrators.
 *
 * Central admins use a different guard ('central') than tenant users,
 * so we use the custom 'central.password.confirm' middleware instead of
 * Fortify's 'password.confirm' middleware.
 */
class TwoFactorAuthenticationController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * Password confirmation is required when the confirmPassword feature is enabled.
     * Uses custom middleware since Central admins don't use Fortify for authentication.
     */
    public static function middleware(): array
    {
        if (! Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')) {
            return [];
        }

        return [
            new Middleware('central.password.confirm', only: ['show']),
        ];
    }

    /**
     * Show the user's two-factor authentication settings page.
     */
    public function show(TwoFactorAuthenticationRequest $request): Response
    {
        $request->ensureStateIsValid();

        $user = $request->user();

        return Inertia::render('central/admin/user-settings/two-factor', [
            'twoFactorEnabled' => $user->hasEnabledTwoFactorAuthentication(),
            'requiresConfirmation' => Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'),
            'setupPending' => $user->two_factor_secret && ! $user->two_factor_confirmed_at,
        ]);
    }
}
