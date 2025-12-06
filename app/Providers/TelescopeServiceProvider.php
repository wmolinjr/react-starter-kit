<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     *
     * SECURITY: This filters sensitive data in ALL environments,
     * not just production, to prevent accidental exposure.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        // Hide sensitive request parameters
        Telescope::hideRequestParameters([
            // CSRF Tokens
            '_token',
            'csrf_token',

            // Passwords
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'old_password',

            // API & Access Tokens
            'api_token',
            'api_key',
            'api_secret',
            'access_token',
            'refresh_token',
            'bearer_token',
            'token',

            // Two-Factor Authentication
            'two_factor_secret',
            'two_factor_recovery_codes',
            'recovery_code',
            'otp',
            'code',

            // Payment Information
            'card_number',
            'cvv',
            'cvc',
            'card_expiry',
            'billing_info',
            'credit_card',

            // Sensitive User Data
            'ssn',
            'social_security',
            'tax_id',
            'drivers_license',

            // Generic secrets
            'secret',
            'private_key',
            'public_key',
            'encryption_key',
        ]);

        // Hide sensitive request headers
        Telescope::hideRequestHeaders([
            'authorization',
            'cookie',
            'set-cookie',
            'x-csrf-token',
            'x-xsrf-token',
            'x-api-key',
            'x-api-token',
            'x-auth-token',
            'php-auth-user',
            'php-auth-pw',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     *
     * SECURITY: Only super admins can access Telescope in production.
     * In local environment, all authenticated users can access.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user) {
            // In local environment, allow all authenticated users
            if (app()->environment('local')) {
                return true;
            }

            // In production, only super admins can access
            // Multi-database tenancy: Super Admin role is in central database
            return $user->hasRole('Super Admin');
        });
    }
}
