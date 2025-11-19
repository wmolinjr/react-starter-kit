<?php

namespace App\Traits;

use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Boot trait - adiciona global scope
     */
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        // Automaticamente define tenant_id ao criar
        static::creating(function ($model) {
            if (tenancy()->initialized && !$model->tenant_id) {
                $model->tenant_id = tenant('id');
            }
        });
    }

    /**
     * Model pertence a um tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
