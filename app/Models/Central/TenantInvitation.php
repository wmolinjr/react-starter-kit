<?php

namespace App\Models\Central;

use App\Models\Tenant\User as TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * TenantInvitation
 *
 * OPTION C: TENANT-ONLY USERS
 * - Stores email instead of user_id (users don't exist in central DB)
 * - User is created in tenant database when invitation is accepted
 * - invited_by_user_id references User in TENANT database (not central)
 */
class TenantInvitation extends Model
{
    use CentralConnection, HasFactory, HasUuids;

    protected $fillable = [
        'tenant_id',
        'email', // Email of invited user (no user_id in central DB)
        'invited_by_user_id', // User ID from TENANT database
        'role',
        'invitation_token',
        'invited_at',
        'accepted_at',
        'expires_at',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
        'accepted_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Tenant that the invitation is for
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user who sent the invitation.
     *
     * OPTION C: invited_by_user_id is from TENANT database.
     * This relationship only works when in tenant context.
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'invited_by_user_id');
    }

    /**
     * Check if invitation is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    /**
     * Check if invitation is accepted
     */
    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    /**
     * Check if invitation is still pending
     */
    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }

    /**
     * Scope for pending invitations
     */
    public function scopePending($query)
    {
        return $query->whereNull('accepted_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Scope for expired invitations
     */
    public function scopeExpired($query)
    {
        return $query->whereNull('accepted_at')
            ->where('expires_at', '<=', now());
    }

    /**
     * Scope for accepted invitations
     */
    public function scopeAccepted($query)
    {
        return $query->whereNotNull('accepted_at');
    }

    /**
     * Find invitation by token
     */
    public static function findByToken(string $token): ?self
    {
        return static::where('invitation_token', $token)
            ->pending()
            ->first();
    }

    /**
     * Scope for invitations by email
     */
    public function scopeForEmail($query, string $email)
    {
        return $query->where('email', strtolower($email));
    }
}
