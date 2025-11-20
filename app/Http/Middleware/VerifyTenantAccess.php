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
     * CACHE: Confia inteiramente no cache nativo do Spatie Permission
     * - getRoleNames() usa cache automático do Spatie (sem queries adicionais)
     * - hasRole() usa cache automático do Spatie
     * - Cache é invalidado automaticamente quando roles/permissions mudam
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

        // Check Super Admin first (cached by Spatie)
        setPermissionsTeamId(null);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $isSuperAdmin = $user->hasRole('Super Admin');

        if ($isSuperAdmin) {
            // Se é super admin e tenant está inicializado, seta o team ID para o tenant atual
            if (tenancy()->initialized) {
                setPermissionsTeamId(tenant('id'));
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
        setPermissionsTeamId($tenantId);

        // Unset cached relations so tenant-scoped roles will reload
        $user->unsetRelation('roles')->unsetRelation('permissions');

        // Check if user has ANY role in this tenant
        // getRoleNames() uses Spatie Permission cache - NO database query!
        if ($user->getRoleNames()->isEmpty()) {
            abort(403, 'Você não tem acesso a este tenant.');
        }

        return $next($request);
    }
}
