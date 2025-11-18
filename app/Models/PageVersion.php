<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageVersion extends Model
{
    protected $fillable = [
        'page_id',
        'version_number',
        'content',
        'created_by',
    ];

    protected $casts = [
        'content' => 'array',
        'version_number' => 'integer',
    ];

    // Relationships
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Helper methods
    public function restore(): bool
    {
        $content = $this->content;

        return $this->page->update([
            'title' => $content['title'] ?? $this->page->title,
            'slug' => $content['slug'] ?? $this->page->slug,
            'content' => $content['content'] ?? $this->page->content,
            'meta_title' => $content['meta_title'] ?? $this->page->meta_title,
            'meta_description' => $content['meta_description'] ?? $this->page->meta_description,
        ]);
    }
}
