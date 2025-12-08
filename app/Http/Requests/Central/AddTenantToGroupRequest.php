<?php

namespace App\Http\Requests\Central;

use Illuminate\Foundation\Http\FormRequest;

class AddTenantToGroupRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|uuid|exists:tenants,id',
            'settings' => 'nullable|array',
            'settings.default_role' => 'nullable|string|max:50',
            'settings.auto_accept_users' => 'nullable|boolean',
            'settings.require_approval' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'tenant_id' => __('federation.fields.tenant'),
            'settings.default_role' => __('federation.fields.default_role'),
        ];
    }
}
