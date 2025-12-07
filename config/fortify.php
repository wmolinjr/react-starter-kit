<?php

use Laravel\Fortify\Features;

/**
 * Fortify Configuration - 2FA Only
 *
 * IMPORTANT: Fortify is used ONLY as a library for Two-Factor Authentication.
 * All authentication routes (login, register, password reset) are handled by
 * custom controllers in app/Http/Controllers/Central/Auth and Tenant/Auth.
 *
 * This config file exists solely to configure 2FA behavior:
 * - TwoFactorAuthenticatable trait on User models
 * - TwoFactorAuthenticationProvider for code verification
 * - Features::twoFactorAuthentication() for option checks
 *
 * @see docs/FORTIFY-REMOVAL-PLAN.md
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Fortify Guard (not used - custom controllers handle auth)
    |--------------------------------------------------------------------------
    */
    'guard' => 'tenant',

    /*
    |--------------------------------------------------------------------------
    | Password Broker (not used - custom controllers handle password reset)
    |--------------------------------------------------------------------------
    */
    'passwords' => 'tenant_users',

    /*
    |--------------------------------------------------------------------------
    | Username / Email
    |--------------------------------------------------------------------------
    */
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => true,

    /*
    |--------------------------------------------------------------------------
    | Home Path (not used - custom controllers handle redirects)
    |--------------------------------------------------------------------------
    */
    'home' => '/home',

    /*
    |--------------------------------------------------------------------------
    | Disable Fortify Routes
    |--------------------------------------------------------------------------
    | We don't use Fortify's routes - all auth is handled by custom controllers.
    | This prevents Fortify from registering any routes.
    |--------------------------------------------------------------------------
    */
    'views' => false,
    'prefix' => '',
    'domain' => null,
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    | These features are used by custom controllers for feature flag checks.
    | Fortify routes are disabled (see Fortify::ignoreRoutes() in AppServiceProvider).
    | Authentication is handled entirely by custom controllers.
    |--------------------------------------------------------------------------
    */
    'features' => [
        Features::registration(),
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]),
    ],
];
