<?php

namespace App\Http\Middleware\Shared;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add Security Headers Middleware
 *
 * Adds comprehensive security headers to all responses to protect
 * against common web vulnerabilities.
 *
 * @see https://owasp.org/www-project-secure-headers/
 */
class AddSecurityHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // X-Frame-Options: Prevent clickjacking attacks
        // DENY: Page cannot be displayed in a frame
        // SAMEORIGIN would allow framing from same origin
        $response->headers->set('X-Frame-Options', 'DENY');

        // X-Content-Type-Options: Prevent MIME sniffing
        // Stops browsers from trying to guess content types
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // X-XSS-Protection: Enable browser XSS protection
        // Note: Modern browsers use CSP instead, but this adds defense in depth
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer-Policy: Control referrer information
        // strict-origin-when-cross-origin: Send full URL for same-origin, only origin for cross-origin
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions-Policy: Control browser features
        // Disable dangerous features like geolocation, microphone, camera by default
        $response->headers->set('Permissions-Policy', implode(', ', [
            'geolocation=()',
            'microphone=()',
            'camera=()',
            'payment=()',
            'usb=()',
            'magnetometer=()',
            'gyroscope=()',
            'speaker=()',
        ]));

        // Strict-Transport-Security (HSTS): Force HTTPS
        // Only add in production and when using HTTPS
        if (app()->environment('production') && $request->secure()) {
            // max-age=31536000: 1 year
            // includeSubDomains: Apply to all subdomains
            // preload: Allow browser preload list inclusion
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Content-Security-Policy (CSP): Prevent XSS and injection attacks
        // This is a strict policy - adjust based on your needs
        $csp = $this->getContentSecurityPolicy($request);
        if ($csp) {
            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }

    /**
     * Get Content Security Policy directives
     *
     * Customize this based on your application's needs.
     * Current policy is strict and allows:
     * - Self-hosted scripts, styles, images
     * - Vite dev server in development
     * - No inline scripts/styles (use nonce or hash if needed)
     *
     * To disable CSP in development (for debugging), set CSP_ENABLED=false in .env
     */
    protected function getContentSecurityPolicy(Request $request): string
    {
        $isLocal = app()->environment('local');

        // Allow disabling CSP in development for debugging
        if ($isLocal && config('app.csp_enabled', true) === false) {
            return '';
        }

        $directives = [
            // Default fallback for all resources
            "default-src 'self'",

            // Scripts: Allow self + Vite in development + Google Analytics
            $isLocal
                ? "script-src 'self' 'unsafe-inline' 'unsafe-eval' http://localhost:* http://127.0.0.1:* https://www.googletagmanager.com https://www.google-analytics.com"
                : "script-src 'self' https://www.googletagmanager.com https://www.google-analytics.com",

            // Styles: Allow self + Vite in development + Bunny Fonts + Google Fonts
            $isLocal
                ? "style-src 'self' 'unsafe-inline' http://localhost:* http://127.0.0.1:* https://fonts.bunny.net https://fonts.googleapis.com"
                : "style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com", // unsafe-inline needed for dynamic styles

            // Images: Allow self, data URLs, HTTPS images, and Google Analytics
            "img-src 'self' data: https: https://www.google-analytics.com https://www.googletagmanager.com",

            // Fonts: Allow self + Vite in development + Bunny Fonts + Google Fonts
            $isLocal
                ? "font-src 'self' data: http://localhost:* http://127.0.0.1:* https://fonts.bunny.net https://fonts.gstatic.com"
                : "font-src 'self' data: https://fonts.bunny.net https://fonts.gstatic.com",

            // Connect (AJAX, WebSocket): Allow self + Vite HMR + tenant subdomains + fonts + analytics
            $isLocal
                ? "connect-src 'self' ws://localhost:* ws://127.0.0.1:* ws://*.localhost http://localhost:* http://127.0.0.1:* http://*.localhost https://fonts.bunny.net https://fonts.googleapis.com https://fonts.gstatic.com https://www.google-analytics.com https://analytics.google.com https://www.googletagmanager.com https://stats.g.doubleclick.net"
                : "connect-src 'self' https://fonts.bunny.net https://fonts.googleapis.com https://fonts.gstatic.com https://www.google-analytics.com https://analytics.google.com https://www.googletagmanager.com https://stats.g.doubleclick.net",

            // Media: Allow self
            "media-src 'self'",

            // Workers: Allow self + blob URLs for Vite HMR
            $isLocal
                ? "worker-src 'self' blob: http://localhost:* http://127.0.0.1:*"
                : "worker-src 'self' blob:",

            // Objects: Block all (Flash, etc.)
            "object-src 'none'",

            // Base URI: Restrict base tag
            "base-uri 'self'",

            // Form actions: Only submit to self
            "form-action 'self'",

            // Frame ancestors: Prevent embedding (same as X-Frame-Options)
            "frame-ancestors 'none'",

            // Upgrade insecure requests in production
            app()->environment('production') ? 'upgrade-insecure-requests' : null,
        ];

        // Remove null directives
        $directives = array_filter($directives);

        return implode('; ', $directives);
    }
}
