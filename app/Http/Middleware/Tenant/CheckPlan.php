<?php

namespace App\Http\Middleware\Tenant;

use App\Models\Central\Tenant;
use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unified middleware for plan-based access control.
 *
 * Usage:
 *   - plan:feature,customRoles  - Check if feature is enabled
 *   - plan:limit,users          - Check if resource limit is reached
 */
class CheckPlan
{
    public function handle(Request $request, Closure $next, string $type, string $key): Response
    {
        $tenant = tenant();

        if (! $tenant) {
            abort(403, 'Tenant not found.');
        }

        return match ($type) {
            'feature' => $this->checkFeature($request, $next, $tenant, $key),
            'limit' => $this->checkLimit($request, $next, $tenant, $key),
            default => abort(500, "Invalid plan check type: {$type}"),
        };
    }

    /**
     * Check if a feature is enabled for the tenant.
     */
    protected function checkFeature(Request $request, Closure $next, Tenant $tenant, string $feature): Response
    {
        if (! Feature::for($tenant)->active($feature)) {
            return $this->denyAccess($request, __('flash.middleware.feature_not_available'), [
                'feature' => $feature,
            ]);
        }

        return $next($request);
    }

    /**
     * Check if a resource limit has been reached.
     */
    protected function checkLimit(Request $request, Closure $next, Tenant $tenant, string $resource): Response
    {
        $limitFeature = 'max'.ucfirst($resource);
        $limit = Feature::for($tenant)->value($limitFeature);
        $usage = $tenant->getCurrentUsage($resource);

        // -1 means unlimited
        if ($limit !== -1 && $usage >= $limit) {
            return $this->denyAccess($request, __('flash.middleware.limit_reached', ['resource' => $resource]), [
                'limit' => $limit,
                'current' => $usage,
            ]);
        }

        return $next($request);
    }

    /**
     * Deny access with appropriate response type.
     */
    protected function denyAccess(Request $request, string $message, array $data = []): Response
    {
        $upgradeUrl = route('tenant.admin.billing.index');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'upgrade_url' => $upgradeUrl,
                ...$data,
            ], 403);
        }

        return redirect()
            ->route('tenant.admin.billing.index')
            ->with('error', $message);
    }
}
