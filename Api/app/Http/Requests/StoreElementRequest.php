<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Schemas\ElementDataSchema;
use App\Schemas\ElementStyleSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreElementRequest extends FormRequest
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
            'element_type' => [
                'required',
                'string',
                'in:seat,section,table,stage,shape,entrance,text,aisle,corridor,emergency_exit,standing_zone,toilet,zone',
            ],
            'x' => 'required|numeric|min:0|max:10000',
            'y' => 'required|numeric|min:0|max:10000',
            'width' => 'required|numeric|min:1|max:5000',
            'height' => 'required|numeric|min:1|max:5000',
            'rotation' => 'nullable|numeric|min:0|max:360',
            'z_index' => 'nullable|integer|min:0|max:1000',
            'parent_id' => 'nullable|exists:template_elements,id',
            'data_json' => 'nullable|array',
            'style_json' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate data_json based on element type
            if ($this->has('data_json') && is_array($this->data_json)) {
                try {
                    ElementDataSchema::validate(
                        $this->element_type,
                        $this->data_json
                    );
                } catch (ValidationException $e) {
                    foreach ($e->errors() as $field => $messages) {
                        $validator->errors()->add("data_json.{$field}", $messages[0]);
                    }
                }
            }

            // Validate style_json
            if ($this->has('style_json') && is_array($this->style_json)) {
                try {
                    ElementStyleSchema::validate($this->style_json);
                } catch (ValidationException $e) {
                    foreach ($e->errors() as $field => $messages) {
                        $validator->errors()->add("style_json.{$field}", $messages[0]);
                    }
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'element_type.required' => 'The element type is required.',
            'element_type.in' => 'Invalid element type. Supported types: seat, section, table, stage, shape, entrance, text, aisle, corridor, emergency_exit, standing_zone, toilet, zone.',
            'x.required' => 'The X position is required.',
            'y.required' => 'The Y position is required.',
            'width.required' => 'The width is required.',
            'height.required' => 'The height is required.',
            'data_json.array' => 'The data field must be an object.',
            'style_json.array' => 'The style field must be an object.',
        ];
    }
}
