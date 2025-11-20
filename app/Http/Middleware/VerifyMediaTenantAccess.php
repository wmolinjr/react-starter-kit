<?php

namespace App\Http\Middleware;

use App\Models\Media;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMediaTenantAccess
{
    /**
     * Verifica se o media pertence ao tenant atual
     *
     * SEGURANÇA: Camada adicional além do BelongsToTenant global scope.
     * Protege contra bypass do global scope e garante isolamento explícito.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Obter media do route parameter
        $media = $request->route('media');

        // Se não é uma instância de Media, deixar passar (outro middleware vai lidar)
        if (! $media instanceof Media) {
            return $next($request);
        }

        // Verificar se media pertence ao tenant atual
        // Nota: BelongsToTenant global scope já faz isso, mas validação explícita
        // adiciona camada extra de segurança
        if ($media->tenant_id !== current_tenant_id()) {
            abort(404, 'Media not found or access denied.');
        }

        return $next($request);
    }
}
