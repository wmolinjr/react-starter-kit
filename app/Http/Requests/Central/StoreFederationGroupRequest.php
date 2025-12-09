<?php

namespace App\Http\Requests\Central;

use App\Models\Central\FederationGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFederationGroupRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'master_tenant_id' => 'required|uuid|exists:tenants,id',
            'sync_strategy' => [
                'required',
                'string',
                Rule::in([
                    FederationGroup::STRATEGY_MASTER_WINS,
                    FederationGroup::STRATEGY_LAST_WRITE_WINS,
                    FederationGroup::STRATEGY_MANUAL_REVIEW,
                ]),
            ],
            'settings' => 'nullable|array',
            'settings.sync_fields' => 'nullable|array',
            'settings.sync_fields.*' => 'string',
            'settings.sync_password' => 'nullable|boolean',
            'settings.sync_profile' => 'nullable|boolean',
            'settings.sync_two_factor' => 'nullable|boolean',
            'settings.sync_roles' => 'nullable|boolean',
            'settings.auto_create_on_login' => 'nullable|boolean',
            'settings.auto_federate_new_users' => 'nullable|boolean',
            'settings.require_email_verification' => 'nullable|boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => __('federation.fields.name'),
            'description' => __('federation.fields.description'),
            'master_tenant_id' => __('federation.fields.master_tenant'),
            'sync_strategy' => __('federation.fields.sync_strategy'),
        ];
    }
}
