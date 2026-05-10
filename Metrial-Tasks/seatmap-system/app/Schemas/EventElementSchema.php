<?php

namespace App\Schemas;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * مخطط التحقق من صحة عناصر الحدث (Event Elements)
 * يضمن سلامة البيانات المنقولة من القالب
 */
class EventElementSchema
{
    /**
     * التحقق من صحة عنصر الحدث بالكامل
     *
     * @param array $data
     * @return array
     * @throws ValidationException
     */
    public static function validate(array $data): array
    {
        $rules = [
            'event_id' => 'required|integer|exists:events,id',
            'template_element_id' => 'nullable|integer',
            'element_type' => 'required|in:seat,section,table,stage,shape,entrance,text',
            'x' => 'required|numeric|min:0|max:10000',
            'y' => 'required|numeric|min:0|max:10000',
            'width' => 'nullable|numeric|min:0|max:1000',
            'height' => 'nullable|numeric|min:0|max:1000',
            'rotation' => 'nullable|numeric|min:0|max:360',
            'z_index' => 'nullable|integer|min:0',
            'parent_id' => 'nullable|integer',
            'data_json' => 'nullable|json',
            'style_json' => 'nullable|json',
            'is_bookable' => 'required|boolean',
            'zone_id' => 'nullable|integer|exists:template_zones,id',
            'booked_price' => 'nullable|numeric|min:0',
        ];

        $validator = Validator::make($data, $rules, [
            'required' => 'حقل :attribute مطلوب',
            'json' => 'حقل :attribute يجب أن يكون JSON صالحاً',
            'exists' => 'المرجع :attribute غير موجود',
            'in' => 'قيمة :attribute غير صحيحة',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // التحقق الإضافي لـ JSON
        if (isset($data['data_json'])) {
            $decoded = json_decode($data['data_json'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw ValidationException::withMessages([
                    'data_json' => 'تنسيق JSON غير صالح في البيانات'
                ]);
            }
            
            // التحقق من صحة الهيكل حسب نوع العنصر
            if (isset($data['element_type'])) {
                try {
                    TemplateElementSchema::validate($data['element_type'], $decoded);
                } catch (ValidationException $e) {
                    throw ValidationException::withMessages([
                        'data_json' => 'بيانات العنصر غير صالحة: ' . implode(', ', $e->errors()['*'] ?? [])
                    ]);
                }
            }
        }

        if (isset($data['style_json'])) {
            $decoded = json_decode($data['style_json'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw ValidationException::withMessages([
                    'style_json' => 'تنسيق JSON غير صالح في الأنماط'
                ]);
            }
            
            try {
                TemplateElementSchema::validateStyle($decoded);
            } catch (ValidationException $e) {
                throw ValidationException::withMessages([
                    'style_json' => 'أنماط العنصر غير صالحة'
                ]);
            }
        }

        return $validator->validated();
    }

    /**
     * التحقق من صحة مصفوفة العناصر (لللقطة)
     *
     * @param array $elements
     * @return array
     * @throws ValidationException
     */
    public static function validateBatch(array $elements): array
    {
        $validated = [];
        $errors = [];

        foreach ($elements as $index => $element) {
            try {
                $validated[] = self::validate($element);
            } catch (ValidationException $e) {
                $errors["element_{$index}"] = $e->errors();
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return $validated;
    }
}
