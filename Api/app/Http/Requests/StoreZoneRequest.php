<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreZoneRequest extends FormRequest
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
            'code' => [
                'nullable',
                'string',
                'max:10',
                'regex:/^[A-Z0-9]+$/',
            ],
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'priority' => 'nullable|integer|min:1|max:100',
            'base_price' => 'nullable|numeric|min:-1000|max:10000',
            'service_fee' => 'nullable|numeric|min:0|max:1000',
            'capacity' => 'nullable|integer|min:1',
            'max_booking_per_order' => 'nullable|integer|min:1|max:20',
            'settings' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The zone name is required.',
            'code.regex' => 'The zone code must contain only uppercase letters and numbers.',
            'color.regex' => 'The color must be a valid hex color (e.g., #FFD700).',
            'base_price.numeric' => 'The base price modifier must be a number.',
            'max_booking_per_order.max' => 'Maximum 20 tickets per order.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Auto-generate code from name if not provided
        if (!$this->code && $this->name) {
            $code = strtoupper(substr(preg_replace('/[^A-Z0-9]/i', '', $this->name), 0, 10));
            $this->merge(['code' => $code]);
        }

        $this->merge([
            'color' => $this->color ?? '#3b82f6',
            'base_price' => $this->base_price ?? 0,
        ]);
    }
}
