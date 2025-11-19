<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Project extends Model implements HasMedia
{
    use HasFactory, BelongsToTenant, InteractsWithMedia;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'description',
        'status',
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
}
