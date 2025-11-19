<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventActionsWhileImpersonating
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar se está impersonando um usuário
        if (session()->has('impersonating_user')) {
            // Prevenir ações sensíveis durante impersonation

            // Billing operations
            if ($request->routeIs('billing.*')) {
                abort(403, 'Billing operations are not allowed during impersonation.');
            }

            // Team management (remover usuários, mudar roles)
            if ($request->routeIs('team.remove') || $request->routeIs('team.update-role')) {
                abort(403, 'Team management operations are not allowed during impersonation.');
            }

            // Senha e 2FA
            if ($request->routeIs('settings.password.*') || $request->routeIs('settings.two-factor.*')) {
                abort(403, 'Security settings cannot be modified during impersonation.');
            }
        }

        return $next($request);
    }
}
