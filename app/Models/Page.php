<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia as HasMediaInterface;
use Spatie\MediaLibrary\InteractsWithMedia;

class Page extends Model implements HasMediaInterface
{
    use BelongsToTenant, HasFactory, InteractsWithMedia;

    protected $fillable = [
        'tenant_id',
        'title',
        'slug',
        'content',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'og_image',
        'status',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'content' => 'array',
        'published_at' => 'datetime',
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(PageBlock::class)->orderBy('order');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(PageVersion::class)->orderBy('version_number', 'desc');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', 'archived');
    }

    // Helper methods
    public function isPublished(): bool
    {
        return $this->status === 'published'
            && $this->published_at !== null
            && $this->published_at->isPast();
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function publish(): bool
    {
        return $this->update([
            'status' => 'published',
            'published_at' => $this->published_at ?? now(),
        ]);
    }

    public function unpublish(): bool
    {
        return $this->update([
            'status' => 'draft',
        ]);
    }

    public function archive(): bool
    {
        return $this->update([
            'status' => 'archived',
        ]);
    }

    public function createVersion(): PageVersion
    {
        $latestVersion = $this->versions()->first();
        $versionNumber = $latestVersion ? $latestVersion->version_number + 1 : 1;

        return $this->versions()->create([
            'version_number' => $versionNumber,
            'content' => [
                'title' => $this->title,
                'slug' => $this->slug,
                'content' => $this->content,
                'meta_title' => $this->meta_title,
                'meta_description' => $this->meta_description,
                'blocks' => $this->blocks->toArray(),
            ],
            'created_by' => auth()->id(),
        ]);
    }
}
