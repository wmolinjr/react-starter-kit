<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TenantInvitation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'tenant_id',
        'email',
        'role',
        'token',
        'invited_by',
        'accepted_at',
        'expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invitation) {
            if (empty($invitation->token)) {
                $invitation->token = Str::random(32);
            }

            // Set expiration to 7 days from now if not set
            if (empty($invitation->expires_at)) {
                $invitation->expires_at = now()->addDays(7);
            }
        });
    }

    /**
     * Get the tenant this invitation belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user who sent the invitation.
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Scope a query to only include pending invitations.
     */
    public function scopePending($query)
    {
        return $query->whereNull('accepted_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Scope a query to only include accepted invitations.
     */
    public function scopeAccepted($query)
    {
        return $query->whereNotNull('accepted_at');
    }

    /**
     * Scope a query to only include expired invitations.
     */
    public function scopeExpired($query)
    {
        return $query->whereNull('accepted_at')
            ->where('expires_at', '<=', now());
    }

    /**
     * Check if the invitation is pending.
     */
    public function isPending(): bool
    {
        return is_null($this->accepted_at) && $this->expires_at > now();
    }

    /**
     * Check if the invitation has been accepted.
     */
    public function isAccepted(): bool
    {
        return ! is_null($this->accepted_at);
    }

    /**
     * Check if the invitation has expired.
     */
    public function isExpired(): bool
    {
        return is_null($this->accepted_at) && $this->expires_at <= now();
    }

    /**
     * Accept the invitation and add the user to the tenant.
     */
    public function accept(User $user): void
    {
        if ($this->isExpired()) {
            throw new \Exception('This invitation has expired');
        }

        if ($this->isAccepted()) {
            throw new \Exception('This invitation has already been accepted');
        }

        \DB::transaction(function () use ($user) {
            // Add user to tenant
            $this->tenant->users()->syncWithoutDetaching([
                $user->id => ['role' => $this->role],
            ]);

            // Mark invitation as accepted
            $this->update(['accepted_at' => now()]);
        });
    }

    /**
     * Resend the invitation (generate new token and extend expiration).
     */
    public function resend(): void
    {
        $this->update([
            'token' => Str::random(32),
            'expires_at' => now()->addDays(7),
        ]);
    }
}
