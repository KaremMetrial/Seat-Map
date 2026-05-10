<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Schemas\ElementDataSchema;
use App\Schemas\ElementStyleSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class BulkStoreElementsRequest extends FormRequest
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
            'elements' => 'required|array|min:1|max:1000',
            'elements.*.element_type' => [
                'required',
                'string',
                'in:seat,section,table,stage,shape,entrance,text,aisle,corridor,emergency_exit,standing_zone,toilet,zone',
            ],
            'elements.*.x' => 'required|numeric|min:0|max:10000',
            'elements.*.y' => 'required|numeric|min:0|max:10000',
            'elements.*.width' => 'required|numeric|min:1|max:5000',
            'elements.*.height' => 'required|numeric|min:1|max:5000',
            'elements.*.rotation' => 'nullable|numeric|min:0|max:360',
            'elements.*.z_index' => 'nullable|integer|min:0|max:1000',
            'elements.*.parent_id' => 'nullable|integer',
            'elements.*.data_json' => 'nullable|array',
            'elements.*.style_json' => 'nullable|array',
            'elements.*.is_active' => 'nullable|boolean',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ($this->elements as $index => $element) {
                // Validate data_json based on element type
                if (isset($element['data_json']) && is_array($element['data_json'])) {
                    try {
                        ElementDataSchema::validate(
                            $element['element_type'],
                            $element['data_json']
                        );
                    } catch (ValidationException $e) {
                        foreach ($e->errors() as $field => $messages) {
                            $validator->errors()->add(
                                "elements.{$index}.data_json.{$field}",
                                $messages[0]
                            );
                        }
                    }
                }

                // Validate style_json
                if (isset($element['style_json']) && is_array($element['style_json'])) {
                    try {
                        ElementStyleSchema::validate($element['style_json']);
                    } catch (ValidationException $e) {
                        foreach ($e->errors() as $field => $messages) {
                            $validator->errors()->add(
                                "elements.{$index}.style_json.{$field}",
                                $messages[0]
                            );
                        }
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
            'elements.required' => 'The elements array is required.',
            'elements.array' => 'The elements must be provided as an array.',
            'elements.min' => 'At least one element is required.',
            'elements.max' => 'Cannot create more than 1000 elements at once.',
            'elements.*.element_type.required' => 'Each element must have a type.',
            'elements.*.element_type.in' => 'Invalid element type.',
            'elements.*.x.required' => 'Each element must have an X position.',
            'elements.*.y.required' => 'Each element must have a Y position.',
            'elements.*.width.required' => 'Each element must have a width.',
            'elements.*.height.required' => 'Each element must have a height.',
        ];
    }
}
