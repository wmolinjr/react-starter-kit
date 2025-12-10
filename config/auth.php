<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'tenant'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'tenant_users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | TENANT-ONLY ARCHITECTURE (Option C):
    | - 'tenant': Guard for tenant users (users table in tenant database)
    | - 'central': Guard for central users (users table in central database)
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        // Guard for tenant users (tenant database)
        'tenant' => [
            'driver' => 'session',
            'provider' => 'tenant_users',
        ],

        // Guard for central users (central database)
        'central' => [
            'driver' => 'session',
            'provider' => 'central_users',
        ],

        // Guard for customers (billing entity, central database)
        // Used for /account/* routes (customer portal)
        'customer' => [
            'driver' => 'session',
            'provider' => 'customers',
        ],

        // Sanctum guards - using smart PersonalAccessToken model that auto-detects context
        // @see App\Models\Shared\PersonalAccessToken

        // API tokens for tenant users (Sanctum)
        'tenant-sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'tenant_users',
        ],

        // API tokens for central users (Sanctum)
        'central-sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'central_users',
        ],

        // API tokens for customers (Sanctum)
        'customer-sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'customers',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | TENANT-ONLY ARCHITECTURE (Option C):
    | Both use 'users' table but in different databases:
    | - 'tenant_users': Tenant\User model → users table in tenant database
    | - 'central_users': Central\User model → users table in central database
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        // Tenant users (users table in each tenant's database)
        'tenant_users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', App\Models\Tenant\User::class),
        ],

        // Central users (users table in central database)
        'central_users' => [
            'driver' => 'eloquent',
            'model' => App\Models\Central\User::class,
        ],

        // Customers (billing entities, customers table in central database)
        'customers' => [
            'driver' => 'eloquent',
            'model' => App\Models\Central\Customer::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | TENANT-ONLY ARCHITECTURE (Option C):
    | Both use 'password_reset_tokens' table but in different databases:
    | - 'tenant_users': password_reset_tokens in tenant database
    | - 'central_users': password_reset_tokens in central database
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        // Password reset for tenant users (tenant database)
        'tenant_users' => [
            'provider' => 'tenant_users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],

        // Password reset for central users (central database)
        'central_users' => [
            'provider' => 'central_users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
            'connection' => 'central', // Explicitly use central connection
        ],

        // Password reset for customers (central database)
        'customers' => [
            'provider' => 'customers',
            'table' => 'customer_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
            'connection' => 'central', // Explicitly use central connection
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
