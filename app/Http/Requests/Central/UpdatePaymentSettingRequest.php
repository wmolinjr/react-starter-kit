<?php

namespace App\Http\Requests\Central;

use App\Enums\PaymentGateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentSettingRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $gateway = $this->route('gateway');
        $gatewayEnum = PaymentGateway::tryFrom($gateway);
        $supportedPaymentTypes = $gatewayEnum?->supportedPaymentTypes() ?? [];

        return [
            'is_enabled' => ['sometimes', 'boolean'],
            'is_sandbox' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'enabled_payment_types' => ['sometimes', 'array'],
            'enabled_payment_types.*' => [
                'string',
                Rule::in($supportedPaymentTypes),
            ],
            'available_countries' => ['sometimes', 'array'],
            'available_countries.*' => ['string', 'size:2'], // ISO country codes

            // Production credentials
            'production_credentials' => ['sometimes', 'array'],
            'production_credentials.*' => ['nullable', 'string', 'max:500'],

            // Sandbox credentials
            'sandbox_credentials' => ['sometimes', 'array'],
            'sandbox_credentials.*' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'enabled_payment_types.*.in' => __('payment_settings.validation.invalid_payment_type'),
            'available_countries.*.size' => __('payment_settings.validation.invalid_country_code'),
        ];
    }
}
