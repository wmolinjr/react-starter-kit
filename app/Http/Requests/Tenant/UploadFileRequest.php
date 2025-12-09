<?php

namespace App\Http\Requests\Tenant;

use App\Enums\PlanLimit;
use Illuminate\Foundation\Http\FormRequest;

class UploadFileRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * File size limit is determined by tenant's plan (FILE_UPLOAD_SIZE limit).
     * The MediaLibraryConfigBootstrapper also enforces this limit at the config level.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Get file upload limit from tenant's plan (in MB)
        // Falls back to default (10MB) if no tenant context
        $limitInMb = $this->getFileUploadLimitInMb();

        // Convert MB to KB for Laravel's 'max' validation rule
        $limitInKb = $limitInMb * 1024;

        return [
            'file' => ['required', 'file', "max:{$limitInKb}"],
            'collection' => ['required', 'in:attachments,images'],
        ];
    }

    /**
     * Get the file upload limit in MB from tenant's plan.
     */
    protected function getFileUploadLimitInMb(): int
    {
        $tenant = tenant();

        if (! $tenant || ! method_exists($tenant, 'getLimit')) {
            return PlanLimit::FILE_UPLOAD_SIZE->defaultValue();
        }

        $limit = $tenant->getLimit(PlanLimit::FILE_UPLOAD_SIZE->value);

        // Use default if limit is 0 or negative
        return $limit > 0 ? $limit : PlanLimit::FILE_UPLOAD_SIZE->defaultValue();
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $limitInMb = $this->getFileUploadLimitInMb();

        return [
            'file.max' => __('validation.file_too_large', ['limit' => $limitInMb]),
        ];
    }
}
