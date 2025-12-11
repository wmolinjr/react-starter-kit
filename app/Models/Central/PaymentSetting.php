<?php

namespace App\Models\Central;

use App\Enums\PaymentGateway;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Payment Gateway Settings Model
 *
 * Stores payment gateway configurations with encrypted credentials.
 * Supports separate sandbox and production credentials with easy toggle.
 *
 * @property string $id
 * @property string $gateway
 * @property string $display_name
 * @property bool $is_enabled
 * @property bool $is_sandbox
 * @property bool $is_default
 * @property array|null $production_credentials
 * @property array|null $sandbox_credentials
 * @property array $enabled_payment_types
 * @property array $available_countries
 * @property array|null $webhook_urls
 * @property \Carbon\Carbon|null $last_tested_at
 * @property bool|null $last_test_success
 * @property string|null $last_test_error
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PaymentSetting extends Model
{
    use CentralConnection, HasUuids;

    protected $fillable = [
        'gateway',
        'display_name',
        'is_enabled',
        'is_sandbox',
        'is_default',
        'production_credentials',
        'sandbox_credentials',
        'enabled_payment_types',
        'available_countries',
        'webhook_urls',
        'last_tested_at',
        'last_test_success',
        'last_test_error',
    ];

    /**
     * Hidden from serialization for security
     */
    protected $hidden = [
        'production_credentials',
        'sandbox_credentials',
    ];

    protected function casts(): array
    {
        return [
            'gateway' => PaymentGateway::class,
            'is_enabled' => 'boolean',
            'is_sandbox' => 'boolean',
            'is_default' => 'boolean',
            'production_credentials' => 'encrypted:array',
            'sandbox_credentials' => 'encrypted:array',
            'enabled_payment_types' => 'array',
            'available_countries' => 'array',
            'webhook_urls' => 'array',
            'last_tested_at' => 'datetime',
            'last_test_success' => 'boolean',
        ];
    }

    /**
     * Get the active credentials based on sandbox mode
     */
    public function getActiveCredentials(): array
    {
        return $this->is_sandbox
            ? ($this->sandbox_credentials ?? [])
            : ($this->production_credentials ?? []);
    }

    /**
     * Get a specific credential value
     */
    public function getCredential(string $key, ?string $default = null): ?string
    {
        return $this->getActiveCredentials()[$key] ?? $default;
    }

    /**
     * Check if has valid credentials for current mode
     */
    public function hasValidCredentials(): bool
    {
        $credentials = $this->getActiveCredentials();
        $requiredFields = $this->gateway->credentialFields();

        foreach ($requiredFields as $field) {
            if (($field['required'] ?? true) && empty($credentials[$field['key']] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get masked hints for credentials (for display in UI)
     */
    public function getCredentialHints(string $mode = 'active'): array
    {
        $credentials = match ($mode) {
            'production' => $this->production_credentials ?? [],
            'sandbox' => $this->sandbox_credentials ?? [],
            default => $this->getActiveCredentials(),
        };

        return collect($credentials)->map(function ($value, $key) {
            if (empty($value)) {
                return null;
            }

            $length = strlen($value);

            if ($length <= 8) {
                return str_repeat('*', $length);
            }

            // Show first 4 and last 4 characters
            return substr($value, 0, 4).'...'.substr($value, -4);
        })->toArray();
    }

    /**
     * Check if gateway supports a payment type
     */
    public function supportsPaymentType(string $type): bool
    {
        return in_array($type, $this->enabled_payment_types ?? []);
    }

    /**
     * Check if gateway is available in a country
     */
    public function isAvailableInCountry(string $countryCode): bool
    {
        $countries = $this->available_countries ?? [];

        return empty($countries) || in_array(strtoupper($countryCode), $countries);
    }

    /**
     * Mark test as successful
     */
    public function markTestSuccess(): void
    {
        $this->update([
            'last_tested_at' => now(),
            'last_test_success' => true,
            'last_test_error' => null,
        ]);
    }

    /**
     * Mark test as failed
     */
    public function markTestFailed(string $error): void
    {
        $this->update([
            'last_tested_at' => now(),
            'last_test_success' => false,
            'last_test_error' => $error,
        ]);
    }

    /**
     * Scope to get enabled gateways
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Scope to get the default gateway
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to get gateways available in a country
     */
    public function scopeAvailableIn($query, string $countryCode)
    {
        $countryCode = strtoupper($countryCode);

        return $query->where(function ($q) use ($countryCode) {
            $q->whereJsonContains('available_countries', $countryCode)
                ->orWhereJsonLength('available_countries', 0);
        });
    }

    /**
     * Scope to get gateways supporting a payment type
     */
    public function scopeSupporting($query, string $paymentType)
    {
        return $query->whereJsonContains('enabled_payment_types', $paymentType);
    }

    /**
     * Get gateways by payment type and country
     */
    public static function getAvailableGateways(string $paymentType, string $countryCode): \Illuminate\Database\Eloquent\Collection
    {
        return static::enabled()
            ->supporting($paymentType)
            ->availableIn($countryCode)
            ->get();
    }
}
