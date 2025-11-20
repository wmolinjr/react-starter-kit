<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTenantAccess
{
    /**
     * Handle an incoming request.
     *
     * Verifica se o usuário autenticado tem acesso ao tenant atual.
     * Super admins (role "Super Admin") têm acesso a todos os tenants.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Se não está autenticado, deixa o middleware 'auth' lidar
        if (! $request->user()) {
            return $next($request);
        }

        $user = $request->user();

        // Super admins (global role) têm acesso a todos os tenants
        // Verifica a role sem tenant_id (global)
        setPermissionsTeamId(null);
        $isSuperAdmin = $user->hasRole('Super Admin');

        if ($isSuperAdmin) {
            // Se é super admin e tenant está inicializado, seta o team ID para o tenant atual
            // para que as verificações de permissão funcionem corretamente
            if (tenancy()->initialized) {
                setPermissionsTeamId(tenant('id'));
            }
            return $next($request);
        }

        // Verifica se o tenant está inicializado
        if (! tenancy()->initialized) {
            abort(403, 'Tenant não inicializado.');
        }

        $tenantId = tenant('id');

        // Set Spatie Permission team ID to current tenant
        // This ensures role/permission lookups are scoped to the current tenant
        setPermissionsTeamId($tenantId);

        // Verifica se o usuário pertence ao tenant atual
        $belongsToTenant = $user->tenants()
            ->where('tenant_id', $tenantId)
            ->exists();

        if (! $belongsToTenant) {
            abort(403, 'Você não tem acesso a este tenant.');
        }

        return $next($request);
    }
}
