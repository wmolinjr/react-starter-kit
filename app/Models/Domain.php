<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Domain extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'tenant_id',
        'domain',
        'is_primary',
        'verification_status',
        'verification_token',
        'verified_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Get the tenant that owns this domain.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope a query to only include verified domains.
     */
    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }

    /**
     * Scope a query to only include primary domains.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope a query to only include pending domains.
     */
    public function scopePending($query)
    {
        return $query->where('verification_status', 'pending');
    }

    /**
     * Generate a unique verification token for DNS validation.
     */
    public function generateVerificationToken(): string
    {
        $token = 'tenant-verify-'.Str::random(32);
        $this->update(['verification_token' => $token]);

        return $token;
    }

    /**
     * Mark this domain as verified.
     */
    public function markAsVerified(): void
    {
        $this->update([
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);
    }

    /**
     * Mark this domain verification as failed.
     */
    public function markAsFailed(): void
    {
        $this->update([
            'verification_status' => 'failed',
        ]);
    }

    /**
     * Set this domain as the primary domain for the tenant.
     * Unsets all other primary domains for the same tenant.
     */
    public function setPrimary(): void
    {
        \DB::transaction(function () {
            // Unset all other primary domains for this tenant
            static::where('tenant_id', $this->tenant_id)
                ->where('id', '!=', $this->id)
                ->update(['is_primary' => false]);

            // Set this domain as primary
            $this->update(['is_primary' => true]);
        });
    }

    /**
     * Check if this domain is verified.
     */
    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    /**
     * Check if this domain is pending verification.
     */
    public function isPending(): bool
    {
        return $this->verification_status === 'pending';
    }

    /**
     * Check if this domain verification has failed.
     */
    public function hasFailed(): bool
    {
        return $this->verification_status === 'failed';
    }
}
