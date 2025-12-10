<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;

/**
 * Payment Method Resource
 *
 * Used for listing customer payment methods in the billing portal.
 */
class PaymentMethodResource extends BaseResource
{
    use HasTypescriptType;

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'provider' => $this->provider,
            'brand' => $this->brand,
            'last4' => $this->last4,
            'exp_month' => $this->exp_month,
            'exp_year' => $this->exp_year,
            'bank_name' => $this->bank_name,
            'is_default' => $this->is_default,
            'is_verified' => $this->is_verified,
            'is_expired' => $this->isExpired(),
            'display_label' => $this->getDisplayLabel(),
            'expiration_display' => $this->getExpirationDisplay(),
            'created_at' => $this->formatIso($this->created_at),
        ];
    }

    public static function typescriptSchema(): array
    {
        return [
            'id' => 'string',
            'type' => "'card' | 'pix' | 'boleto' | 'bank_transfer'",
            'provider' => 'string',
            'brand' => 'string | null',
            'last4' => 'string | null',
            'exp_month' => 'number | null',
            'exp_year' => 'number | null',
            'bank_name' => 'string | null',
            'is_default' => 'boolean',
            'is_verified' => 'boolean',
            'is_expired' => 'boolean',
            'display_label' => 'string',
            'expiration_display' => 'string | null',
            'created_at' => 'string',
        ];
    }
}
