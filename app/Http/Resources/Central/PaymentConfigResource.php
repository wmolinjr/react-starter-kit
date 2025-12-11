<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payment Config Resource
 *
 * Provides payment configuration for checkout UIs.
 * Does NOT expose credentials - only available methods and gateway mappings.
 *
 * Used by: BillingController for addons/bundles checkout pages.
 */
class PaymentConfigResource extends JsonResource
{
    use HasTypescriptType;

    /**
     * Disable the 'data' wrapper for Inertia compatibility.
     */
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function toArray($request): array
    {
        return [
            'available_methods' => $this->resource['available_methods'] ?? [],
            'default_method' => $this->resource['default_method'] ?? 'card',
            'gateways' => $this->resource['gateways'] ?? [],
            'has_recurring_support' => $this->resource['has_recurring_support'] ?? false,
        ];
    }

    public static function typescriptSchema(): array
    {
        return [
            'available_methods' => "('card' | 'pix' | 'boleto')[]",
            'default_method' => "'card' | 'pix' | 'boleto'",
            'gateways' => "Record<'card' | 'pix' | 'boleto', string>",
            'has_recurring_support' => 'boolean',
        ];
    }
}
