<?php

namespace App\Providers;

use App\Models\Central\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     * Horizon is only accessible from the central domain by super admins.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            // In local environment, allow access for development
            if (app()->environment('local')) {
                return true;
            }

            // In production, require super admin
            return $user instanceof User && $user->is_super_admin;
        });
    }
}
