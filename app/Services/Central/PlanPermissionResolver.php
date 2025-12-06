<?php

namespace App\Services\Central;

use App\Enums\TenantPermission;
use App\Models\Central\Tenant;
use Laravel\Pennant\Feature;

/**
 * PlanPermissionResolver
 *
 * Resolves all permissions a tenant should have based on their plan
 * and active features. This is the central service for determining
 * what permissions are available to a tenant.
 *
 * ARCHITECTURE:
 * - Uses Laravel Pennant to check feature availability (respects overrides)
 * - Expands wildcards like "roles:*" to actual permissions
 * - Returns a flat array of permission names
 */
class PlanPermissionResolver
{
    /**
     * Resolve todas as permissions que um tenant deve ter
     * baseado em seu plano e features ativas.
     */
    public function resolve(Tenant $tenant): array
    {
        $plan = $tenant->plan;

        if (! $plan) {
            return [];
        }

        $permissions = [];
        $permissionMap = $plan->permission_map ?? [];

        // Itera sobre o permission_map e verifica quais features estão ativas
        foreach ($permissionMap as $feature => $featurePermissions) {
            // Usa Pennant para verificar se feature está ativa
            // Isso respeita: trial, overrides, e plan.features
            if ($this->isFeatureActive($tenant, $feature)) {
                $permissions = array_merge(
                    $permissions,
                    $this->expandWildcards($featurePermissions)
                );
            }
        }

        return array_unique($permissions);
    }

    /**
     * Resolve permissions para um plano específico
     * (sem verificar feature flags - útil para preview/comparação)
     */
    public function resolveForPlan($plan): array
    {
        if (! $plan) {
            return [];
        }

        $permissions = [];
        $permissionMap = $plan->permission_map ?? [];

        foreach ($permissionMap as $feature => $featurePermissions) {
            // Verifica diretamente no plan.features (sem Pennant)
            if ($plan->hasFeature($feature)) {
                $permissions = array_merge(
                    $permissions,
                    $this->expandWildcards($featurePermissions)
                );
            }
        }

        return array_unique($permissions);
    }

    /**
     * Verifica se uma feature está ativa para o tenant.
     * Usa Pennant que já considera: trial, overrides, e plan.features
     */
    protected function isFeatureActive(Tenant $tenant, string $feature): bool
    {
        // Billing é especial - disponível se existe plano
        if ($feature === 'billing') {
            return $tenant->plan !== null;
        }

        // Para todas as features, usa Pennant
        try {
            return Feature::for($tenant)->active($feature);
        } catch (\Exception $e) {
            // Se feature não existe no Pennant, verifica direto no plano
            return $tenant->plan?->hasFeature($feature) ?? false;
        }
    }

    /**
     * Expande wildcards como roles:* para
     * todas as actions: view, create, edit, delete
     */
    public function expandWildcards(array $permissions): array
    {
        $expanded = [];

        foreach ($permissions as $permission) {
            if (str_ends_with($permission, ':*')) {
                $expanded = array_merge($expanded, $this->expandWildcard($permission));
            } else {
                $expanded[] = $permission;
            }
        }

        return array_unique($expanded);
    }

    /**
     * Expande um único wildcard permission.
     * Uses TenantPermission enum as single source of truth.
     */
    protected function expandWildcard(string $permission): array
    {
        // roles:* → roles
        $base = substr($permission, 0, -2);

        // Extrai categoria: roles:* → roles
        $category = TenantPermission::extractCategory($permission);

        // Usa actions do enum (single source of truth)
        $actions = TenantPermission::actionsFor($category);

        return array_map(fn ($action) => "{$base}:{$action}", $actions);
    }

    /**
     * Retorna permissions que o tenant PERDEU após mudança de plano.
     */
    public function getRemovedPermissions(Tenant $tenant, array $newPermissions): array
    {
        $currentPermissions = $tenant->getPlanEnabledPermissions();

        return array_diff($currentPermissions, $newPermissions);
    }

    /**
     * Retorna permissions que o tenant GANHOU após mudança de plano.
     */
    public function getAddedPermissions(Tenant $tenant, array $newPermissions): array
    {
        $currentPermissions = $tenant->getPlanEnabledPermissions();

        return array_diff($newPermissions, $currentPermissions);
    }

    /**
     * Compara dois planos e retorna as diferenças de permissions.
     */
    public function comparePlans($oldPlan, $newPlan): array
    {
        $oldPermissions = $oldPlan ? $this->resolveForPlan($oldPlan) : [];
        $newPermissions = $newPlan ? $this->resolveForPlan($newPlan) : [];

        return [
            'added' => array_values(array_diff($newPermissions, $oldPermissions)),
            'removed' => array_values(array_diff($oldPermissions, $newPermissions)),
            'unchanged' => array_values(array_intersect($oldPermissions, $newPermissions)),
        ];
    }

    /**
     * Verifica se a mudança de plano é um downgrade
     * (perde permissions).
     */
    public function isDowngrade($oldPlan, $newPlan): bool
    {
        $comparison = $this->comparePlans($oldPlan, $newPlan);

        return count($comparison['removed']) > 0;
    }

    /**
     * Retorna todas as categorias disponíveis com suas actions.
     * Uses TenantPermission enum as single source of truth.
     */
    public function getAvailableCategories(): array
    {
        return TenantPermission::actionsByCategory();
    }

    /**
     * Agrupa permissions por categoria.
     * Uses TenantPermission enum as single source of truth.
     */
    public function groupByCategory(array $permissions): array
    {
        $grouped = [];

        foreach ($permissions as $permission) {
            $category = TenantPermission::extractCategory($permission);
            $grouped[$category][] = $permission;
        }

        ksort($grouped);

        return $grouped;
    }
}
