<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateSeatsRequest extends FormRequest
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
            'start_x' => 'required|numeric|min:0|max:10000',
            'start_y' => 'required|numeric|min:0|max:10000',
            'rows' => 'required|integer|min:1|max:100',
            'seats_per_row' => 'required|integer|min:1|max:100',
            'seat_width' => 'required|numeric|min:5|max:200',
            'seat_height' => 'required|numeric|min:5|max:200',
            'gap_x' => 'nullable|numeric|min:0|max:50',
            'gap_y' => 'nullable|numeric|min:0|max:50',
            'row_label_start' => 'nullable|string|max:3',
            'zone_id' => 'nullable|exists:template_zones,id',
            'style' => 'nullable|array',
            'style.fill' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'style.stroke' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
            'style.strokeWidth' => 'nullable|numeric|min:0|max:10',
            'seat_type' => 'nullable|string|in:regular,vip,wheelchair,companion',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'start_x.required' => 'The starting X position is required.',
            'start_y.required' => 'The starting Y position is required.',
            'rows.required' => 'The number of rows is required.',
            'rows.max' => 'Cannot generate more than 100 rows at once.',
            'seats_per_row.required' => 'The seats per row is required.',
            'seats_per_row.max' => 'Cannot generate more than 100 seats per row.',
            'seat_width.required' => 'The seat width is required.',
            'seat_height.required' => 'The seat height is required.',
            'style.fill.regex' => 'The fill color must be a valid hex color (e.g., #10b981).',
            'style.stroke.regex' => 'The stroke color must be a valid hex color.',
            'seat_type.in' => 'Invalid seat type. Supported: regular, vip, wheelchair, companion.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set defaults
        $this->merge([
            'gap_x' => $this->gap_x ?? 5,
            'gap_y' => $this->gap_y ?? 5,
            'row_label_start' => $this->row_label_start ?? 'A',
            'seat_type' => $this->seat_type ?? 'regular',
        ]);
    }
}
