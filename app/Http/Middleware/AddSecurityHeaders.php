<?php

namespace App\Http\Middleware;

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
     */
    protected function getContentSecurityPolicy(Request $request): string
    {
        $isLocal = app()->environment('local');

        $directives = [
            // Default fallback for all resources
            "default-src 'self'",

            // Scripts: Allow self + Vite in development
            $isLocal
                ? "script-src 'self' 'unsafe-inline' 'unsafe-eval' http://localhost:* http://127.0.0.1:*"
                : "script-src 'self'",

            // Styles: Allow self + Vite in development
            $isLocal
                ? "style-src 'self' 'unsafe-inline' http://localhost:* http://127.0.0.1:*"
                : "style-src 'self' 'unsafe-inline'", // unsafe-inline needed for dynamic styles

            // Images: Allow self, data URLs, and HTTPS images
            "img-src 'self' data: https:",

            // Fonts: Allow self
            "font-src 'self' data:",

            // Connect (AJAX, WebSocket): Allow self + Vite HMR in development
            $isLocal
                ? "connect-src 'self' ws://localhost:* ws://127.0.0.1:* http://localhost:* http://127.0.0.1:*"
                : "connect-src 'self'",

            // Media: Allow self
            "media-src 'self'",

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
