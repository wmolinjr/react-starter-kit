<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Boot the trait.
     */
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (auth()->check() && auth()->user()->current_tenant_id) {
                $builder->where(static::getTenantColumnName(), auth()->user()->current_tenant_id);
            }
        });

        static::creating(function (Model $model) {
            if (auth()->check() && auth()->user()->current_tenant_id) {
                $model->setAttribute(static::getTenantColumnName(), auth()->user()->current_tenant_id);
            }
        });
    }

    /**
     * Get the tenant relationship.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, static::getTenantColumnName());
    }

    /**
     * Get the tenant column name.
     */
    public static function getTenantColumnName(): string
    {
        return 'tenant_id';
    }

    /**
     * Scope query without the tenant scope.
     */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }

    /**
     * Scope query for a specific tenant.
     */
    public function scopeForTenant(Builder $query, Tenant|int $tenant): Builder
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $query->withoutGlobalScope('tenant')
            ->where(static::getTenantColumnName(), $tenantId);
    }
}
