<?php

namespace App\Http\Requests\Central\Signup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validates account creation data (Step 1 of signup wizard).
 *
 * Customer-First Flow: Creates Customer immediately, so we only
 * check the customers table for email uniqueness.
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
                'unique:customers,email', // Customer-first: only check customers table
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
            'locale' => ['nullable', 'string', 'max:10'],
        ];
    }
}
