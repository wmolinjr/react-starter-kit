<?php

namespace App\Http\Requests\Central\Signup;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates payment processing data (Step 3 of signup wizard).
 */
class ProcessPaymentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'in:card,pix,boleto'],
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
            'payment_method.required' => __('signup.errors.payment_method_required'),
            'payment_method.in' => __('signup.errors.payment_method_invalid'),
        ];
    }
}
