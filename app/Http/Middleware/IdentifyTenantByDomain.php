<?php

namespace App\Http\Middleware;

use App\Models\Domain;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenantByDomain
{
    /**
     * Handle an incoming request.
     *
     * Identifies tenant by custom domain (via domains table) or subdomain.
     * Priority: verified custom domain > subdomain > legacy domain field
     *
     * Examples:
     * - cliente.localhost -> finds tenant with subdomain='cliente'
     * - www.cliente.com -> finds tenant via domains table (verified)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        $tenant = null;

        // Priority 1: Try to find tenant by verified custom domain in domains table
        $domain = Domain::where('domain', $host)
            ->verified()
            ->with('tenant')
            ->first();

        if ($domain && $domain->tenant?->isActive()) {
            $tenant = $domain->tenant;
        }

        // Priority 2: If not found by custom domain, try subdomain
        if (! $tenant) {
            $subdomain = $this->extractSubdomain($host);

            if ($subdomain) {
                $tenant = Tenant::where('subdomain', $subdomain)
                    ->where('status', 'active')
                    ->first();
            }
        }

        // Priority 3: Fallback to legacy domain field in tenants table
        if (! $tenant) {
            $tenant = Tenant::where('domain', $host)
                ->where('status', 'active')
                ->first();
        }

        // If still not found, abort
        if (! $tenant) {
            abort(404, 'Tenant not found for domain: '.$host);
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
        if (! str_ends_with($hostWithoutPort, '.'.$baseDomain)) {
            return null;
        }

        // Extract subdomain
        $subdomain = str_replace('.'.$baseDomain, '', $hostWithoutPort);

        // Ignore www subdomain
        if ($subdomain === 'www') {
            return null;
        }

        return $subdomain;
    }
}
