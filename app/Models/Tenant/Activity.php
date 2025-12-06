<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Activity Model
 *
 * MULTI-DATABASE TENANCY:
 * - Lives in tenant database (no tenant_id column needed)
 * - Isolation is at database level, not row level
 * - Uses UUID for consistency across all models
 */
class Activity extends SpatieActivity
{
    use HasUuids;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'properties' => 'collection',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'log_name',
        'description',
        'subject_type',
        'subject_id',
        'causer_type',
        'causer_id',
        'properties',
        'event',
        'batch_uuid',
    ];
}
