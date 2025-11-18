<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageTemplate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'thumbnail',
        'blocks',
        'category',
    ];

    protected $casts = [
        'blocks' => 'array',
    ];

    // Relationship
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // Scopes
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    // Helper methods
    public function createPageFromTemplate(string $title, string $slug): Page
    {
        $page = Page::create([
            'tenant_id' => $this->tenant_id,
            'title' => $title,
            'slug' => $slug,
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        foreach ($this->blocks as $index => $blockData) {
            $page->blocks()->create([
                'block_type' => $blockData['block_type'],
                'content' => $blockData['content'],
                'config' => $blockData['config'] ?? null,
                'order' => $index,
            ]);
        }

        return $page->load('blocks');
    }
}
