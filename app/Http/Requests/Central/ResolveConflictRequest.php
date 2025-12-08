<?php

namespace App\Http\Requests\Central;

use App\Models\Central\FederationConflict;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveConflictRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'resolved_value' => 'required',
            'resolution' => [
                'required',
                'string',
                Rule::in([
                    FederationConflict::RESOLUTION_MASTER,
                    FederationConflict::RESOLUTION_SOURCE,
                    FederationConflict::RESOLUTION_MANUAL,
                ]),
            ],
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'resolved_value' => __('federation.fields.resolved_value'),
            'resolution' => __('federation.fields.resolution'),
            'notes' => __('federation.fields.notes'),
        ];
    }
}
