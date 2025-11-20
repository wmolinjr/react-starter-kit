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
     * PERFORMANCE: Usa Spatie Permission cache (já configurado) em vez de query ao banco.
     * Se o usuário tem qualquer role no tenant (owner, admin, member),
     * significa que ele pertence ao tenant.
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

        // Unset cached relations before switching team context
        // This ensures fresh role check from cache for global scope
        $user->unsetRelation('roles')->unsetRelation('permissions');

        $isSuperAdmin = $user->hasRole('Super Admin');

        if ($isSuperAdmin) {
            // Se é super admin e tenant está inicializado, seta o team ID para o tenant atual
            // para que as verificações de permissão funcionem corretamente
            if (tenancy()->initialized) {
                setPermissionsTeamId(tenant('id'));
                // Unset again for tenant context
                $user->unsetRelation('roles')->unsetRelation('permissions');
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

        // PERFORMANCE: Unset cached model relations so tenant-scoped roles will reload
        // This uses Spatie Permission's existing cache (already configured in TenancyServiceProvider)
        // No additional cache management needed!
        $user->unsetRelation('roles')->unsetRelation('permissions');

        // Check if user has ANY role in this tenant (not specific roles)
        // getRoleNames() uses Spatie Permission cache - NO database query!
        // Works with ANY role (owner, admin, member, or future custom roles)
        $hasAnyRole = $user->getRoleNames()->isNotEmpty();

        if (! $hasAnyRole) {
            abort(403, 'Você não tem acesso a este tenant.');
        }

        return $next($request);
    }
}
