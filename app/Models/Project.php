<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Project extends Model implements HasMedia
{
    use HasFactory, BelongsToTenant, InteractsWithMedia, LogsActivity;

    /**
     * Mass assignment protection
     * NOTE: tenant_id is NOT fillable - it's set automatically by BelongsToTenant trait
     */
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'status',
    ];

    /**
     * Attributes that should never be mass assignable
     */
    protected $guarded = [
        'id',
        'tenant_id', // Prevent tenant_id manipulation
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Project criado por um usuário
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Registrar media collections (Spatie)
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->useDisk('tenant_uploads');

        $this->addMediaCollection('images')
            ->useDisk('tenant_uploads')
            ->registerMediaConversions(function () {
                $this->addMediaConversion('thumb')
                    ->width(300)
                    ->height(300);
            });
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }

    /**
     * Activity Log Options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'description', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
