<?php

namespace App\Http\Resources\Tenant;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;
use Illuminate\Http\Request;

/**
 * InvoiceDetailResource
 *
 * Detailed invoice information for invoices page.
 */
class InvoiceDetailResource extends BaseResource
{
    use HasTypescriptType;

    /**
     * {@inheritDoc}
     */
    public static function typescriptSchema(): array
    {
        return [
            'id' => 'string',
            'number' => 'string | null',
            'date' => 'string',
            'date_formatted' => 'string',
            'due_date' => 'string | null',
            'total' => 'string',
            'status' => 'string',
            'paid' => 'boolean',
            'download_url' => 'string',
            'lines' => '{ description: string; quantity: number; amount: string }[]',
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
            'number' => $this->resource['number'],
            'date' => $this->resource['date'],
            'date_formatted' => $this->resource['date_formatted'],
            'due_date' => $this->resource['due_date'],
            'total' => $this->resource['total'],
            'status' => $this->resource['status'],
            'paid' => $this->resource['paid'],
            'download_url' => $this->resource['download_url'],
            'lines' => $this->resource['lines'],
        ];
    }
}
