<?php

namespace App\Http\Requests\Tenant;

use App\Services\Central\PaymentSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CartCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // Get available payment methods from PaymentSettings
        $paymentSettingsService = app(PaymentSettingsService::class);
        $paymentConfig = $paymentSettingsService->getAvailablePaymentMethods();
        $availableMethods = $paymentConfig['available_methods'] ?? ['card'];

        return [
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.type' => ['required', 'in:addon,bundle'],
            'items.*.slug' => ['required', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.billing_period' => ['required', 'in:monthly,yearly,one_time'],
            'payment_method' => ['sometimes', 'string', Rule::in($availableMethods)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => __('validation.cart_empty'),
            'items.min' => __('validation.cart_empty'),
            'payment_method.in' => __('validation.payment_method_unavailable'),
        ];
    }
}
