<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasTenantUsers
{
    /**
     * Obter apenas users do tenant atual
     */
    public function scopeWithTenantUsers($query)
    {
        return $query->whereHas('user', function ($q) {
            $q->whereHas('tenants', function ($q) {
                $q->where('tenant_id', tenant('id'));
            });
        });
    }

    /**
     * Verificar se user tem acesso a este resource
     */
    public function userHasAccess(User $user): bool
    {
        return $this->tenant_id === tenant('id') && $user->belongsToCurrentTenant();
    }
}
