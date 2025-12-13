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
        $rules = [
            'payment_method' => ['required', 'in:card,pix,boleto'],
        ];

        // CPF/CNPJ is required for PIX and Boleto payments
        if (in_array($this->input('payment_method'), ['pix', 'boleto'])) {
            $rules['cpf_cnpj'] = [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $digits = preg_replace('/\D/', '', $value);
                    if (strlen($digits) !== 11 && strlen($digits) !== 14) {
                        $fail(__('billing.form.invalid_cpf_cnpj'));
                    }
                },
            ];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cpf_cnpj.required' => __('billing.form.cpf_cnpj_required'),
        ];
    }
}
