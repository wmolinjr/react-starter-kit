<?php

namespace App\Http\Requests\Central;

use App\Services\Central\PlanService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $planService = app(PlanService::class);

        return array_merge([
            'name' => 'required|array',
            'name.*' => 'nullable|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('plans')->ignore($this->route('plan'))],
            'description' => 'nullable|array',
            'description.*' => 'nullable|string',
            'price' => 'required|integer|min:0',
            'currency' => 'nullable|string|max:3',
            'billing_period' => 'required|in:monthly,yearly',
            'features' => 'nullable|array',
            'limits' => 'nullable|array',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'badge' => 'nullable|string|max:50',
            'icon' => 'nullable|string|max:50',
            'icon_color' => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer',
            'addon_ids' => 'nullable|array',
        ], $planService->getLimitValidationRules());
    }
}
