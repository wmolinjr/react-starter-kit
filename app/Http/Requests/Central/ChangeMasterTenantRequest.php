<?php

namespace App\Http\Requests\Central;

use App\Models\Central\FederationGroup;
use Illuminate\Foundation\Http\FormRequest;

class ChangeMasterTenantRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var FederationGroup $group */
        $group = $this->route('group');

        return [
            'new_master_tenant_id' => [
                'required',
                'uuid',
                'exists:tenants,id',
                // Must be a member of the group
                function ($attribute, $value, $fail) use ($group) {
                    if (!$group->hasTenant($value)) {
                        $fail(__('validation.federation.tenant_not_in_group'));
                    }
                },
                // Cannot be the current master
                function ($attribute, $value, $fail) use ($group) {
                    if ($group->master_tenant_id === $value) {
                        $fail(__('validation.federation.already_master'));
                    }
                },
            ],
            'confirm' => 'required|accepted',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'new_master_tenant_id' => __('admin.federation.change_master.new_master'),
            'confirm' => __('admin.federation.change_master.confirm_text'),
        ];
    }
}
