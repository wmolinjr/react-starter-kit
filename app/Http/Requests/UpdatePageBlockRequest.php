<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePageBlockRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('page'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $blockType = $this->input('block_type', $this->route('block')->block_type);

        return [
            'block_type' => ['sometimes', 'string', Rule::in([
                'hero',
                'text',
                'image',
                'gallery',
                'cta',
                'features',
                'testimonials',
            ])],
            'content' => ['sometimes', 'array'],
            'config' => ['nullable', 'array'],

            // Hero block specific validation
            ...($blockType === 'hero' ? $this->heroRules() : []),

            // Text block specific validation
            ...($blockType === 'text' ? $this->textRules() : []),

            // Image block specific validation
            ...($blockType === 'image' ? $this->imageRules() : []),

            // Gallery block specific validation
            ...($blockType === 'gallery' ? $this->galleryRules() : []),

            // CTA block specific validation
            ...($blockType === 'cta' ? $this->ctaRules() : []),

            // Features block specific validation
            ...($blockType === 'features' ? $this->featuresRules() : []),

            // Testimonials block specific validation
            ...($blockType === 'testimonials' ? $this->testimonialsRules() : []),
        ];
    }

    protected function heroRules(): array
    {
        return [
            'content.title' => ['sometimes', 'string', 'max:255'],
            'content.subtitle' => ['nullable', 'string', 'max:500'],
            'content.cta_text' => ['nullable', 'string', 'max:100'],
            'content.cta_url' => ['nullable', 'string', 'max:500'],
            'content.image_url' => ['nullable', 'string', 'max:500'],
            'config.alignment' => ['nullable', 'string', Rule::in(['left', 'center', 'right'])],
            'config.background_color' => ['nullable', 'string', 'max:50'],
            'config.text_color' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function textRules(): array
    {
        return [
            'content.content' => ['sometimes', 'string'],
            'config.alignment' => ['nullable', 'string', Rule::in(['left', 'center', 'right', 'justify'])],
            'config.text_color' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function imageRules(): array
    {
        return [
            'content.url' => ['sometimes', 'string', 'max:500'],
            'content.alt' => ['nullable', 'string', 'max:255'],
            'content.caption' => ['nullable', 'string', 'max:500'],
            'config.alignment' => ['nullable', 'string', Rule::in(['left', 'center', 'right'])],
            'config.width' => ['nullable', 'string', Rule::in(['small', 'medium', 'large', 'full'])],
        ];
    }

    protected function galleryRules(): array
    {
        return [
            'content.images' => ['sometimes', 'array', 'min:1'],
            'content.images.*.url' => ['required', 'string', 'max:500'],
            'content.images.*.alt' => ['nullable', 'string', 'max:255'],
            'content.images.*.caption' => ['nullable', 'string', 'max:500'],
            'config.columns' => ['nullable', 'integer', 'min:1', 'max:6'],
            'config.gap' => ['nullable', 'string', Rule::in(['small', 'medium', 'large'])],
        ];
    }

    protected function ctaRules(): array
    {
        return [
            'content.title' => ['sometimes', 'string', 'max:255'],
            'content.description' => ['nullable', 'string', 'max:500'],
            'content.button_text' => ['sometimes', 'string', 'max:100'],
            'content.button_url' => ['sometimes', 'string', 'max:500'],
            'config.alignment' => ['nullable', 'string', Rule::in(['left', 'center', 'right'])],
            'config.background_color' => ['nullable', 'string', 'max:50'],
            'config.button_style' => ['nullable', 'string', Rule::in(['primary', 'secondary', 'outline'])],
        ];
    }

    protected function featuresRules(): array
    {
        return [
            'content.title' => ['nullable', 'string', 'max:255'],
            'content.features' => ['sometimes', 'array', 'min:1'],
            'content.features.*.title' => ['required', 'string', 'max:255'],
            'content.features.*.description' => ['nullable', 'string', 'max:500'],
            'content.features.*.icon' => ['nullable', 'string', 'max:100'],
            'config.columns' => ['nullable', 'integer', 'min:1', 'max:4'],
            'config.alignment' => ['nullable', 'string', Rule::in(['left', 'center', 'right'])],
        ];
    }

    protected function testimonialsRules(): array
    {
        return [
            'content.title' => ['nullable', 'string', 'max:255'],
            'content.testimonials' => ['sometimes', 'array', 'min:1'],
            'content.testimonials.*.quote' => ['required', 'string'],
            'content.testimonials.*.author' => ['required', 'string', 'max:255'],
            'content.testimonials.*.role' => ['nullable', 'string', 'max:255'],
            'content.testimonials.*.avatar' => ['nullable', 'string', 'max:500'],
            'config.layout' => ['nullable', 'string', Rule::in(['grid', 'carousel', 'list'])],
            'config.columns' => ['nullable', 'integer', 'min:1', 'max:3'],
        ];
    }
}
