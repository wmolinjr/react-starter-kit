<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\BaseResource;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Tests\TestCase;

class BaseResourceTest extends TestCase
{
    public function test_format_iso_returns_null_for_null_date(): void
    {
        $resource = new class(null) extends BaseResource
        {
            public function toArray(Request $request): array
            {
                return ['date' => $this->formatIso(null)];
            }
        };

        $result = $resource->resolve(request());

        $this->assertNull($result['date']);
    }

    public function test_format_iso_returns_iso_string(): void
    {
        $date = Carbon::parse('2024-01-15 10:30:00');

        $resource = new class((object) ['date' => $date]) extends BaseResource
        {
            public function toArray(Request $request): array
            {
                return ['date' => $this->formatIso($this->resource->date)];
            }
        };

        $result = $resource->resolve(request());

        $this->assertStringContainsString('2024-01-15', $result['date']);
    }

    public function test_format_date_returns_formatted_string(): void
    {
        $date = Carbon::parse('2024-01-15 10:30:00');

        $resource = new class((object) ['date' => $date]) extends BaseResource
        {
            public function toArray(Request $request): array
            {
                return ['date' => $this->formatDate($this->resource->date, 'Y-m-d')];
            }
        };

        $result = $resource->resolve(request());

        $this->assertEquals('2024-01-15', $result['date']);
    }

    public function test_format_date_only_returns_date_without_time(): void
    {
        $date = Carbon::parse('2024-01-15 10:30:00');

        $resource = new class((object) ['date' => $date]) extends BaseResource
        {
            public function toArray(Request $request): array
            {
                return ['date' => $this->formatDateOnly($this->resource->date)];
            }
        };

        $result = $resource->resolve(request());

        $this->assertEquals('2024-01-15', $result['date']);
    }

    public function test_format_currency_formats_cents_correctly(): void
    {
        $resource = new class(null) extends BaseResource
        {
            public function toArray(Request $request): array
            {
                return [
                    'price' => $this->formatCurrency(9900, 'BRL'),
                ];
            }
        };

        $result = $resource->resolve(request());

        $this->assertEquals('99,00 BRL', $result['price']);
    }
}
