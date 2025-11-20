<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    /*
     * Paths that should have CORS headers applied
     * API routes and Sanctum CSRF cookie endpoint
     */
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'broadcasting/auth', // For real-time features
    ],

    /*
     * Allowed HTTP methods for CORS requests
     * Restricting to only necessary methods for security
     */
    'allowed_methods' => [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS',
    ],

    /*
     * Allowed origins - configured via environment variables
     * In production, should be set to your specific domains
     * In development, can use '*' for convenience
     */
    'allowed_origins' => env('CORS_ALLOWED_ORIGINS') ?
        explode(',', env('CORS_ALLOWED_ORIGINS')) : [],

    /*
     * Allowed origin patterns for tenant subdomains
     * Supports wildcard subdomains for multi-tenant architecture
     *
     * Example patterns:
     * - https://*.myapp.com (all subdomains in production)
     * - https://*.myapp.test (all subdomains in local dev)
     * - https://*.myapp.localhost (Docker/Sail development)
     */
    'allowed_origins_patterns' => [
        env('APP_ENV') === 'production'
            ? '/^https:\/\/[\w-]+\.'.preg_quote(env('APP_DOMAIN', 'myapp.com'), '/').'$/'
            : '/^https?:\/\/[\w-]+\.(myapp\.test|myapp\.localhost|localhost)(:\d+)?$/',
    ],

    /*
     * Allowed headers in CORS requests
     * Explicitly allowing only necessary headers for security
     */
    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'Accept',
        'Origin',
        'X-CSRF-TOKEN',
        'X-XSRF-TOKEN',
        'X-Tenant-ID', // Custom header for tenant identification
    ],

    /*
     * Headers exposed to the browser in responses
     * Useful for pagination, rate limiting, etc.
     */
    'exposed_headers' => [
        'X-Tenant-ID',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
    ],

    /*
     * Preflight cache duration in seconds
     * Reduces the number of preflight OPTIONS requests
     * 24 hours = 86400 seconds
     */
    'max_age' => 86400,

    /*
     * Support credentials (cookies, authorization headers)
     * Required for Sanctum authentication and tenant context
     */
    'supports_credentials' => true,

];
