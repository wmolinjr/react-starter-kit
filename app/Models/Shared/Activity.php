<?php

namespace App\Models\Shared;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Activity Model
 *
 * SHARED MODEL:
 * - Works in both central and tenant contexts
 * - Central activities: Stored in central database (admin actions on tenants, plans, etc.)
 * - Tenant activities: Stored in each tenant's database (user actions on projects, team, etc.)
 * - Isolation is at the database level - no type column needed
 * - Uses UUID for consistency across all models
 *
 * @property string $id UUID primary key
 * @property string|null $log_name
 * @property string $description
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $causer_type
 * @property string|null $causer_id
 * @property string|null $event
 * @property \Illuminate\Support\Collection|null $properties
 * @property string|null $batch_uuid
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
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
