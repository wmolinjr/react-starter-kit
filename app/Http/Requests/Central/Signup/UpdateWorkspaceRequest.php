<?php

namespace App\Http\Requests\Central\Signup;

use App\Enums\BusinessSector;
use App\Models\Central\PendingSignup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates workspace setup data (Step 2 of signup wizard).
 */
class UpdateWorkspaceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $signupId = $this->route('signup')?->id;

        return [
            'workspace_name' => ['required', 'string', 'max:255'],
            'workspace_slug' => [
                'required',
                'string',
                'max:63',
                'alpha_dash',
                // Unique in tenants table
                Rule::unique('tenants', 'slug'),
                // Unique in pending signups (except current)
                function ($attribute, $value, $fail) use ($signupId) {
                    $query = PendingSignup::where('workspace_slug', $value)
                        ->whereIn('status', ['pending', 'processing'])
                        ->where(function ($q) {
                            $q->whereNull('expires_at')
                                ->orWhere('expires_at', '>', now());
                        });

                    if ($signupId) {
                        $query->where('id', '!=', $signupId);
                    }

                    if ($query->exists()) {
                        $fail(__('signup.errors.slug_already_taken'));
                    }
                },
            ],
            'business_sector' => ['required', Rule::enum(BusinessSector::class)],
            'plan_id' => ['required', 'uuid', 'exists:plans,id'],
            'billing_period' => ['required', 'in:monthly,yearly'],
        ];
    }
}
