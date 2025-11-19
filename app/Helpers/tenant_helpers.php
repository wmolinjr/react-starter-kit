<?php

use App\Models\Tenant;
use App\Models\User;

if (!function_exists('current_tenant')) {
    /**
     * Obter tenant atual
     */
    function current_tenant(): ?Tenant
    {
        if (!tenancy()->initialized) {
            return null;
        }

        return Tenant::find(tenant('id'));
    }
}

if (!function_exists('current_tenant_id')) {
    /**
     * Obter ID do tenant atual
     */
    function current_tenant_id(): ?int
    {
        return tenancy()->initialized ? tenant('id') : null;
    }
}

if (!function_exists('tenant_url')) {
    /**
     * Gerar URL para o tenant atual
     */
    function tenant_url(string $path = '/'): string
    {
        $tenant = current_tenant();

        if (!$tenant) {
            return url($path);
        }

        return $tenant->url() . $path;
    }
}

if (!function_exists('can_manage_team')) {
    /**
     * Verificar se user pode gerenciar equipe
     */
    function can_manage_team(?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        if (!$user) {
            return false;
        }

        return $user->hasAnyRole(['owner', 'admin']);
    }
}

if (!function_exists('can_manage_billing')) {
    /**
     * Verificar se user pode gerenciar billing
     */
    function can_manage_billing(?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        if (!$user) {
            return false;
        }

        return $user->isOwner();
    }
}
