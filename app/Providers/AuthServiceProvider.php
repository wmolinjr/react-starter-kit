<?php

namespace App\Providers;

use App\Models\Shared\PersonalAccessToken;
use App\Models\Tenant\Project;
use App\Policies\ProjectPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Sanctum\Sanctum;

/**
 * AuthServiceProvider
 *
 * Configures authentication policies and Sanctum integration.
 *
 * SANCTUM v4 + TENANCY v4 INTEGRATION:
 * - Uses smart PersonalAccessToken model that works in both contexts
 * - Tenant context: uses 'personal_access_tokens' table in tenant database
 * - Central context: uses 'admin_personal_access_tokens' table in central database
 * - Sanctum v4 no longer auto-loads migrations (no ignoreMigrations() needed)
 *
 * @see https://v4.tenancyforlaravel.com/integrations/sanctum/
 * @see https://github.com/laravel/sanctum/blob/4.x/UPGRADE.md
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Project::class => ProjectPolicy::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        // Sanctum v4: Migrations are no longer auto-loaded
        // Migrations:
        // - Central: database/migrations/2025_11_19_150407_create_personal_access_tokens_table.php (admin_personal_access_tokens)
        // - Tenant: database/migrations/tenant/2025_12_01_000003_create_tenant_personal_access_tokens_table.php
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Use smart PersonalAccessToken model that works in both contexts
        // - In tenant context: uses tenant database's personal_access_tokens table
        // - In central context: uses central database's admin_personal_access_tokens table
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Note: Super Admin Gate::before() is now in AppServiceProvider
        // to follow Spatie Laravel Permission best practices
    }
}
