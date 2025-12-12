<?php

namespace App\Http\Requests\Central\Signup;

use App\Models\Central\Customer;
use App\Models\Central\PendingSignup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validates account creation data (Step 1 of signup wizard).
 */
class StoreAccountRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                function ($attribute, $value, $fail) {
                    // Check if email exists in customers table
                    if (Customer::where('email', $value)->exists()) {
                        $fail(__('validation.unique', ['attribute' => $attribute]));
                    }
                    // Check if email exists in pending signups (not expired)
                    if (PendingSignup::where('email', $value)
                        ->whereIn('status', ['pending', 'processing'])
                        ->where(function ($q) {
                            $q->whereNull('expires_at')
                                ->orWhere('expires_at', '>', now());
                        })
                        ->exists()
                    ) {
                        $fail(__('validation.unique', ['attribute' => $attribute]));
                    }
                },
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
            'locale' => ['nullable', 'string', 'max:10'],
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
            'email.unique' => __('signup.errors.email_already_registered'),
        ];
    }
}
