<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'canvas_width' => 'nullable|integer|min:100|max:10000',
            'canvas_height' => 'nullable|integer|min:100|max:10000',
            'background_color' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'background_image' => 'nullable|string|url|max:500',
            'grid_size' => 'nullable|integer|min:5|max:100',
            'show_grid' => 'nullable|boolean',
            'scale_factor' => 'nullable|numeric|min:0.001|max:10',
            'units' => 'nullable|string|in:meters,feet',
            'is_default' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The template name is required.',
            'canvas_width.min' => 'Canvas width must be at least 100 pixels.',
            'canvas_height.min' => 'Canvas height must be at least 100 pixels.',
            'background_color.regex' => 'Background color must be a valid hex color (e.g., #1a1a2e).',
            'scale_factor.min' => 'Scale factor must be at least 0.001.',
            'units.in' => 'Units must be either "meters" or "feet".',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'canvas_width' => $this->canvas_width ?? 800,
            'canvas_height' => $this->canvas_height ?? 600,
            'background_color' => $this->background_color ?? '#1a1a2e',
            'grid_size' => $this->grid_size ?? 10,
            'show_grid' => $this->show_grid ?? true,
            'scale_factor' => $this->scale_factor ?? 0.05,
            'units' => $this->units ?? 'meters',
            'is_default' => $this->is_default ?? false,
        ]);
    }
}
