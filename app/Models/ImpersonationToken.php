<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImpersonationToken extends Model
{
    protected $fillable = [
        'token',
        'tenant_id',
        'user_id',
        'expires_at',
        'consumed_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    /**
     * Tenant being impersonated
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * User being impersonated (nullable)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if token is valid (not expired and not consumed)
     */
    public function isValid(): bool
    {
        return $this->consumed_at === null
            && $this->expires_at->isFuture();
    }

    /**
     * Mark token as consumed
     */
    public function consume(): void
    {
        $this->update(['consumed_at' => now()]);
    }
}
