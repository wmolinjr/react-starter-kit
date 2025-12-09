<?php

namespace App\Http\Resources\Tenant;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * SubscriptionResource
 *
 * Subscription information for billing pages.
 */
class SubscriptionResource extends BaseResource
{
    use HasTypescriptType;

    /**
     * {@inheritDoc}
     */
    public static function typescriptSchema(): array
    {
        return [
            'name' => 'string',
            'status' => 'string',
            'trial_ends_at' => 'string | null',
            'ends_at' => 'string | null',
            'on_trial' => 'boolean',
            'on_grace_period' => 'boolean',
            'canceled' => 'boolean',
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
            'name' => $this->resource['name'],
            'status' => $this->resource['status'],
            'trial_ends_at' => $this->resource['trial_ends_at'],
            'ends_at' => $this->resource['ends_at'],
            'on_trial' => $this->resource['on_trial'],
            'on_grace_period' => $this->resource['on_grace_period'],
            'canceled' => $this->resource['canceled'],
        ];
    }
}
