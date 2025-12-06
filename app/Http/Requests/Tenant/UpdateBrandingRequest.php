<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'logo' => 'nullable|image|max:2048',
            'primary_color' => 'nullable|regex:/^#[0-9A-F]{6}$/i',
            'secondary_color' => 'nullable|regex:/^#[0-9A-F]{6}$/i',
            'custom_css' => 'nullable|string|max:10000',
        ];
    }
}
