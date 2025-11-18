<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenantByDomain
{
    /**
     * Handle an incoming request.
     *
     * Identifies tenant by subdomain or custom domain.
     * Priority: custom domain > subdomain
     *
     * Examples:
     * - cliente.localhost -> finds tenant with subdomain='cliente'
     * - www.cliente.com -> finds tenant with domain='www.cliente.com'
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        // Try to find tenant by custom domain first
        $tenant = Tenant::where('domain', $host)
            ->where('status', 'active')
            ->first();

        // If not found by domain, try subdomain
        if (!$tenant) {
            $subdomain = $this->extractSubdomain($host);

            if ($subdomain) {
                $tenant = Tenant::where('subdomain', $subdomain)
                    ->where('status', 'active')
                    ->first();
            }
        }

        // If still not found, abort
        if (!$tenant) {
            abort(404, 'Tenant not found for domain: ' . $host);
        }

        // Store tenant in container and request
        app()->instance('tenant', $tenant);
        $request->merge(['current_tenant' => $tenant]);

        return $next($request);
    }

    /**
     * Extract subdomain from host.
     *
     * Examples:
     * - cliente.localhost -> 'cliente'
     * - acme.myapp.com -> 'acme'
     * - localhost -> null (no subdomain)
     * - myapp.com -> null (no subdomain)
     */
    private function extractSubdomain(string $host): ?string
    {
        // Get the base domain from config (e.g., 'localhost' or 'myapp.com')
        $baseDomain = config('app.domain', 'localhost');

        // Remove port if present (e.g., localhost:8000 -> localhost)
        $hostWithoutPort = explode(':', $host)[0];

        // If host equals base domain, no subdomain
        if ($hostWithoutPort === $baseDomain) {
            return null;
        }

        // Check if host ends with base domain
        if (!str_ends_with($hostWithoutPort, '.' . $baseDomain)) {
            return null;
        }

        // Extract subdomain
        $subdomain = str_replace('.' . $baseDomain, '', $hostWithoutPort);

        // Ignore www subdomain
        if ($subdomain === 'www') {
            return null;
        }

        return $subdomain;
    }
}
