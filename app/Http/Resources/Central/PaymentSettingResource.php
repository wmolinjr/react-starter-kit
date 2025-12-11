<?php

namespace App\Http\Resources\Central;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Concerns\HasTypescriptType;

/**
 * PaymentSettingResource
 *
 * Transforms PaymentSetting model for frontend display.
 * Includes masked credential hints for security.
 */
class PaymentSettingResource extends BaseResource
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
        $gatewayEnum = $this->resource->gateway;

        return [
            'id' => $this->resource->id,
            'gateway' => $gatewayEnum->value,
            'display_name' => $this->resource->display_name ?? $gatewayEnum->displayName(),
            'is_enabled' => $this->resource->is_enabled,
            'is_sandbox' => $this->resource->is_sandbox,
            'is_default' => $this->resource->is_default,

            // Configuration
            'enabled_payment_types' => $this->resource->enabled_payment_types ?? [],
            'available_countries' => $this->resource->available_countries ?? [],
            'webhook_urls' => $this->resource->webhook_urls ?? [],

            // Gateway metadata from enum
            'supported_payment_types' => $gatewayEnum->supportedPaymentTypes(),
            'credential_fields' => $gatewayEnum->credentialFields(),
            'docs_url' => $gatewayEnum->docsUrl(),
            'sandbox_url' => $gatewayEnum->sandboxUrl(),

            // Masked credential hints (NEVER send actual credentials)
            'production_credential_hints' => $this->resource->exists
                ? $this->resource->getCredentialHints('production')
                : [],
            'sandbox_credential_hints' => $this->resource->exists
                ? $this->resource->getCredentialHints('sandbox')
                : [],

            // Has credentials flags (to show if configured)
            'has_production_credentials' => $this->hasCredentials('production'),
            'has_sandbox_credentials' => $this->hasCredentials('sandbox'),

            // Test status
            'last_tested_at' => $this->formatIso($this->resource->last_tested_at),
            'last_test_success' => $this->resource->last_test_success,
            'last_test_error' => $this->resource->last_test_error,

            // Timestamps
            'created_at' => $this->formatIso($this->resource->created_at),
            'updated_at' => $this->formatIso($this->resource->updated_at),
        ];
    }

    /**
     * Check if gateway has credentials for a mode.
     */
    protected function hasCredentials(string $mode): bool
    {
        $credentials = $mode === 'production'
            ? $this->resource->production_credentials
            : $this->resource->sandbox_credentials;

        if (empty($credentials)) {
            return false;
        }

        // Check if at least one required field has a value
        $requiredFields = $this->resource->gateway->credentialFields();

        foreach ($requiredFields as $field) {
            if (($field['required'] ?? true) && ! empty($credentials[$field['key']] ?? null)) {
                return true;
            }
        }

        return false;
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
            'gateway' => 'string',
            'display_name' => 'string',
            'is_enabled' => 'boolean',
            'is_sandbox' => 'boolean',
            'is_default' => 'boolean',
            'enabled_payment_types' => 'string[]',
            'available_countries' => 'string[]',
            'webhook_urls' => 'Record<string, string>',
            'supported_payment_types' => 'string[]',
            'credential_fields' => 'CredentialField[]',
            'docs_url' => 'string',
            'sandbox_url' => 'string | null',
            'production_credential_hints' => 'Record<string, string | null>',
            'sandbox_credential_hints' => 'Record<string, string | null>',
            'has_production_credentials' => 'boolean',
            'has_sandbox_credentials' => 'boolean',
            'last_tested_at' => 'string | null',
            'last_test_success' => 'boolean | null',
            'last_test_error' => 'string | null',
            'created_at' => 'string | null',
            'updated_at' => 'string | null',
        ];
    }
}
