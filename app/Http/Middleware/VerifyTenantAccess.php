<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VerifyTenantAccess
{
    /**
     * Handle an incoming request.
     *
     * Verifica se o usuário autenticado tem acesso ao tenant atual.
     * Super admins (role "Super Admin") têm acesso a todos os tenants.
     *
     * PERFORMANCE OPTIMIZATION:
     * 1. Request cache: Evita verificações duplicadas no mesmo request
     * 2. Redis cache: Cacheia verificação de acesso por 60 segundos
     * 3. Spatie Permission cache: Cache nativo de roles/permissions
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
        $userId = $user->id;

        // OPTIMIZATION 1: Request cache (evita verificações duplicadas no mesmo request)
        $requestCacheKey = "tenant_access_verified_{$userId}";
        if ($request->attributes->has($requestCacheKey)) {
            return $next($request);
        }

        // OPTIMIZATION 2: Check Super Admin first (cached by Spatie)
        setPermissionsTeamId(null);
        $user->unsetRelation('roles')->unsetRelation('permissions');
        $isSuperAdmin = $user->hasRole('Super Admin');

        if ($isSuperAdmin) {
            // Se é super admin e tenant está inicializado, seta o team ID para o tenant atual
            if (tenancy()->initialized) {
                setPermissionsTeamId(tenant('id'));
                $user->unsetRelation('roles')->unsetRelation('permissions');
            }
            $request->attributes->set($requestCacheKey, true);
            return $next($request);
        }

        // Verifica se o tenant está inicializado
        if (! tenancy()->initialized) {
            abort(403, 'Tenant não inicializado.');
        }

        $tenantId = tenant('id');

        // OPTIMIZATION 3: Redis cache (60 seconds) - evita verificação de roles repetidas
        // Se cache não suporta tags (ex: array em testes), faz verificação direta
        $cacheKey = "tenant_access:user_{$userId}:tenant_{$tenantId}";

        $hasAccess = Cache::supportsTags()
            ? Cache::tags(['tenant_access', "tenant_{$tenantId}"])->remember(
                $cacheKey,
                now()->addSeconds(60),
                function () use ($user, $tenantId) {
                    // Set Spatie Permission team ID to current tenant
                    setPermissionsTeamId($tenantId);

                    // Unset cached relations so tenant-scoped roles will reload
                    $user->unsetRelation('roles')->unsetRelation('permissions');

                    // Check if user has ANY role in this tenant
                    // getRoleNames() uses Spatie Permission cache - NO database query!
                    return $user->getRoleNames()->isNotEmpty();
                }
            )
            : (function () use ($user, $tenantId) {
                // Fallback para testes: verificação direta sem cache
                setPermissionsTeamId($tenantId);
                $user->unsetRelation('roles')->unsetRelation('permissions');
                return $user->getRoleNames()->isNotEmpty();
            })();

        if (! $hasAccess) {
            abort(403, 'Você não tem acesso a este tenant.');
        }

        // Ensure team ID is set for subsequent permission checks in the request
        setPermissionsTeamId($tenantId);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        // Mark as verified in request cache
        $request->attributes->set($requestCacheKey, true);

        return $next($request);
    }

    /**
     * Invalida o cache de acesso de um usuário a um tenant específico.
     * Deve ser chamado quando roles de um usuário mudam.
     */
    public static function invalidateUserAccess(int $userId, int $tenantId): void
    {
        // Array cache (usado em testes) não suporta tags
        if (! Cache::supportsTags()) {
            return;
        }

        $cacheKey = "tenant_access:user_{$userId}:tenant_{$tenantId}";
        Cache::tags(['tenant_access', "tenant_{$tenantId}"])->forget($cacheKey);
    }

    /**
     * Invalida o cache de acesso de todos os usuários de um tenant.
     * Deve ser chamado quando roles do tenant mudam (ex: novo membro).
     */
    public static function invalidateTenantAccess(int $tenantId): void
    {
        // Array cache (usado em testes) não suporta tags
        if (! Cache::supportsTags()) {
            return;
        }

        Cache::tags("tenant_{$tenantId}")->flush();
    }
}
