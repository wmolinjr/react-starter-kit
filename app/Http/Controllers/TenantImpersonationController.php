<?php

namespace App\Http\Controllers;

use App\Models\ImpersonationToken;
use Illuminate\Http\Request;

class TenantImpersonationController extends Controller
{
    /**
     * Consumir token de impersonation e iniciar sessão
     */
    public function consume(string $token)
    {
        // Buscar token
        $impersonationToken = ImpersonationToken::where('token', $token)
            ->with(['tenant', 'user'])
            ->first();

        // Validar que token existe
        if (!$impersonationToken) {
            abort(404, 'Invalid impersonation token.');
        }

        // Validar que token não está expirado nem consumido
        if (!$impersonationToken->isValid()) {
            abort(403, 'Impersonation token has expired or has already been used.');
        }

        // Validar que estamos no domínio correto do tenant
        if (tenancy()->tenant?->id !== $impersonationToken->tenant_id) {
            abort(403, 'Token does not match current tenant.');
        }

        // Marcar sessão como impersonation
        session()->put('impersonating_tenant', $impersonationToken->tenant_id);

        // Se houver usuário específico, fazer login
        if ($impersonationToken->user_id) {
            session()->put('impersonating_user', $impersonationToken->user_id);
            auth()->login($impersonationToken->user);
        }

        // Marcar token como consumido
        $impersonationToken->consume();

        // Redirecionar para dashboard
        return redirect('/dashboard')
            ->with('success', 'Successfully impersonating tenant: ' . $impersonationToken->tenant->name);
    }
}
