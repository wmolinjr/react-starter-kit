<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * AddonSubscriptionResource
 *
 * Represents an addon subscription with tenant info.
 * Used in addon management listing views.
 */
class AddonSubscriptionResource extends BaseResource
{
    use HasTypescriptType;

    /**
     * {@inheritDoc}
     */
    public static function typescriptSchema(): array
    {
        return [
            'id' => 'string',
            'addon_slug' => 'string',
            'addon_type' => 'AddonType',
            'name' => 'string',
            'description' => 'string | null',
            'quantity' => 'number',
            'price' => 'number',
            'currency' => 'string',
            'total_price' => 'number',
            'formatted_price' => 'string',
            'formatted_total_price' => 'string',
            'billing_period' => 'BillingPeriod',
            'billing_period_label' => 'string',
            'status' => 'AddonStatus',
            'status_label' => 'string',
            'started_at' => 'string | null',
            'expires_at' => 'string | null',
            'canceled_at' => 'string | null',
            'is_active' => 'boolean',
            'is_recurring' => 'boolean',
            'is_metered' => 'boolean',
            'metered_usage' => 'number | null',
            'provider' => 'string | null',
            'provider_item_id' => 'string | null',
            'tenant' => 'AddonSubscriptionTenant | null',
            'created_at' => 'string',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public static function typescriptAdditionalTypes(): array
    {
        return [
            'AddonSubscriptionTenant' => [
                'id' => 'string',
                'name' => 'string',
            ],
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
            'addon_slug' => $this->addon_slug,
            'addon_type' => $this->addon_type,
            'name' => $this->name,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'currency' => $this->currency ?? 'brl',
            'total_price' => $this->total_price,
            'formatted_price' => $this->formatted_price,
            'formatted_total_price' => $this->formatted_total_price,
            'billing_period' => $this->billing_period,
            'billing_period_label' => $this->billing_period?->label() ?? 'Unknown',
            'status' => $this->status,
            'status_label' => $this->status?->label() ?? 'Unknown',
            'started_at' => $this->formatIso($this->started_at),
            'expires_at' => $this->formatIso($this->expires_at),
            'canceled_at' => $this->formatIso($this->canceled_at),
            'is_active' => $this->isActive(),
            'is_recurring' => $this->isRecurring(),
            'is_metered' => $this->isMetered(),
            'metered_usage' => $this->when($this->isMetered(), $this->metered_usage),
            'provider' => $this->provider,
            'provider_item_id' => $this->provider_item_id,
            'tenant' => $this->when(
                $this->relationLoaded('tenant') && $this->tenant,
                fn () => [
                    'id' => $this->tenant->id,
                    'name' => $this->tenant->name,
                ]
            ),
            'created_at' => $this->formatIso($this->created_at),
        ];
    }
}
