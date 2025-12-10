<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class CartCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.type' => ['required', 'in:addon,bundle'],
            'items.*.slug' => ['required', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.billing_period' => ['required', 'in:monthly,yearly,one_time'],
            'payment_method' => ['sometimes', 'string', 'in:card,pix,boleto'],
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
        ];
    }
}
