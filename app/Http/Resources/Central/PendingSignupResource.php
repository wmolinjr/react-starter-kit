<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;

/**
 * PendingSignupResource
 *
 * Transforms PendingSignup model for API responses.
 * Used in the signup wizard to track progress.
 */
class PendingSignupResource extends BaseResource
{
    use HasTypescriptType;

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'locale' => $this->locale,
            'workspace_name' => $this->workspace_name,
            'workspace_slug' => $this->workspace_slug,
            'business_sector' => $this->business_sector?->value,
            'plan_id' => $this->plan_id,
            'billing_period' => $this->billing_period,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'is_completed' => $this->isCompleted(),
            'is_expired' => $this->isExpired(),
            'has_workspace_data' => $this->hasWorkspaceData(),
            'tenant_url' => $this->tenant?->url(),
            'tenant_id' => $this->tenant_id,
            'failure_reason' => $this->failure_reason,
            'expires_at' => $this->formatIso($this->expires_at),
            'created_at' => $this->formatIso($this->created_at),
        ];
    }

    /**
     * TypeScript type definition for auto-generation.
     *
     * @return array<string, string>
     */
    public static function typescriptSchema(): array
    {
        return [
            'id' => 'string',
            'email' => 'string',
            'name' => 'string',
            'locale' => 'string',
            'workspace_name' => 'string | null',
            'workspace_slug' => 'string | null',
            'business_sector' => 'string | null',
            'plan_id' => 'string | null',
            'billing_period' => "'monthly' | 'yearly'",
            'payment_method' => "'card' | 'pix' | 'boleto' | null",
            'status' => "'pending' | 'processing' | 'completed' | 'failed' | 'expired'",
            'is_completed' => 'boolean',
            'is_expired' => 'boolean',
            'has_workspace_data' => 'boolean',
            'tenant_url' => 'string | null',
            'tenant_id' => 'string | null',
            'failure_reason' => 'string | null',
            'expires_at' => 'string | null',
            'created_at' => 'string',
        ];
    }
}
