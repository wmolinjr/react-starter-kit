<?php

namespace App\Http\Resources\Tenant;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * UserInvitationResource
 *
 * Pending team invitation.
 *
 * MULTI-DATABASE TENANCY (Option C):
 * - UserInvitation lives in tenant database (isolated per tenant)
 * - invitedBy relationship to Tenant\User
 */
class UserInvitationResource extends BaseResource
{
    use HasTypescriptType;

    /**
     * {@inheritDoc}
     */
    public static function typescriptSchema(): array
    {
        return [
            'id' => 'string',
            'email' => 'string',
            'role' => 'TenantRole',
            'invited_at' => 'string',
            'expires_at' => 'string',
            'is_expired' => 'boolean',
            'expires_in_days' => 'number | null',
            'invited_by' => 'InvitedByUser | undefined',
        ];
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role,
            'invited_at' => $this->formatIso($this->invited_at),
            'expires_at' => $this->formatIso($this->expires_at),

            // Computed
            'is_expired' => $this->isExpired(),
            'expires_in_days' => $this->expires_at?->diffInDays(now()),

            // Relationship
            'invited_by' => $this->whenLoaded('invitedBy', fn () => [
                'id' => $this->invitedBy->id,
                'name' => $this->invitedBy->name,
            ]),
        ];
    }
}
