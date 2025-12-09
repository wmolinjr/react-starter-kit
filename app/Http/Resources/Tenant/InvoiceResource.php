<?php

namespace App\Http\Resources\Tenant;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * InvoiceResource
 *
 * Invoice summary for billing overview.
 */
class InvoiceResource extends BaseResource
{
    use HasTypescriptType;

    /**
     * {@inheritDoc}
     */
    public static function typescriptSchema(): array
    {
        return [
            'id' => 'string',
            'date' => 'string',
            'total' => 'string',
            'download_url' => 'string',
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
            'id' => $this->resource['id'],
            'date' => $this->resource['date'],
            'total' => $this->resource['total'],
            'download_url' => $this->resource['download_url'],
        ];
    }
}
