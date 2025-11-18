<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia as HasMediaInterface;
use Spatie\MediaLibrary\InteractsWithMedia;

class PageBlock extends Model implements HasMediaInterface
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'page_id',
        'block_type',
        'content',
        'order',
        'config',
    ];

    protected $casts = [
        'content' => 'array',
        'config' => 'array',
        'order' => 'integer',
    ];

    // Relationship
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    // Helper methods
    public function moveUp(): bool
    {
        if ($this->order === 0) {
            return false;
        }

        $previousBlock = static::where('page_id', $this->page_id)
            ->where('order', $this->order - 1)
            ->first();

        if ($previousBlock) {
            $previousBlock->update(['order' => $this->order]);
        }

        return $this->update(['order' => $this->order - 1]);
    }

    public function moveDown(): bool
    {
        $nextBlock = static::where('page_id', $this->page_id)
            ->where('order', $this->order + 1)
            ->first();

        if (!$nextBlock) {
            return false;
        }

        $nextBlock->update(['order' => $this->order]);

        return $this->update(['order' => $this->order + 1]);
    }

    public function duplicate(): self
    {
        $maxOrder = static::where('page_id', $this->page_id)->max('order');

        return static::create([
            'page_id' => $this->page_id,
            'block_type' => $this->block_type,
            'content' => $this->content,
            'config' => $this->config,
            'order' => $maxOrder + 1,
        ]);
    }
}
