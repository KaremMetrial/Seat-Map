<?php

declare(strict_types=1);

namespace App\Schemas;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * JSON Data Schema for Template Elements
 * 
 * Validates element-specific data_json fields based on element type.
 * Prevents data injection and ensures structural integrity.
 */
class ElementDataSchema
{
    /**
     * Validation schemas by element type
     */
    private const SCHEMAS = [
        'seat' => [
            'label' => 'required|string|max:20',
            'row' => 'nullable|string|max:10',
            'seat_number' => 'nullable|string|max:10',
            'seat_type' => 'nullable|in:regular,vip,wheelchair,companion',
            'accessibility' => 'nullable|array',
            'accessibility.wheelchair' => 'nullable|boolean',
            'accessibility.hearing_assistance' => 'nullable|boolean',
            'accessibility.visual_assistance' => 'nullable|boolean',
        ],
        'section' => [
            'label' => 'required|string|max:50',
            'capacity' => 'nullable|integer|min:1',
            'curve' => 'nullable|array',
            'curve.center_x' => 'nullable|numeric',
            'curve.center_y' => 'nullable|numeric',
            'curve.radius' => 'nullable|numeric|min:0',
            'curve.start_angle' => 'nullable|numeric',
            'curve.end_angle' => 'nullable|numeric',
        ],
        'table' => [
            'label' => 'required|string|max:50',
            'capacity' => 'required|integer|min:2|max:20',
            'shape' => 'nullable|in:round,rectangular,square,oval',
            'has_power' => 'nullable|boolean',
        ],
        'stage' => [
            'label' => 'nullable|string|max:50',
            'has_curtain' => 'nullable|boolean',
        ],
        'shape' => [
            'label' => 'nullable|string|max:50',
            'shape_type' => 'nullable|in:rectangle,circle,ellipse,polygon',
        ],
        'entrance' => [
            'label' => 'nullable|string|max:50',
            'is_main' => 'nullable|boolean',
        ],
        'emergency_exit' => [
            'label' => 'nullable|string|max:50',
            'is_emergency' => 'nullable|boolean',
        ],
        'aisle' => [
            'label' => 'nullable|string|max:50',
            'is_emergency' => 'nullable|boolean',
        ],
        'corridor' => [
            'label' => 'nullable|string|max:50',
            'is_emergency' => 'nullable|boolean',
        ],
        'text' => [
            'content' => 'required|string|max:500',
            'font_size' => 'nullable|integer|min:8|max:72',
            'is_title' => 'nullable|boolean',
        ],
        'toilet' => [
            'label' => 'nullable|string|max:50',
            'accessible' => 'nullable|boolean',
        ],
        'standing_zone' => [
            'label' => 'required|string|max:50',
            'capacity' => 'required|integer|min:1',
        ],
        'zone' => [
            'label' => 'nullable|string|max:50',
            'zone_type' => 'nullable|in:muster_station,emergency_assembly,waiting_area',
        ],
    ];

    /**
     * Validate element data based on type
     *
     * @param string $elementType
     * @param array|null $data
     * @return array Validated data
     * @throws ValidationException
     */
    public static function validate(string $elementType, ?array $data): array
    {
        if ($data === null) {
            return [];
        }

        if (!isset(self::SCHEMAS[$elementType])) {
            // For unknown element types, allow any data structure
            return $data;
        }

        $rules = self::SCHEMAS[$elementType];

        $validator = Validator::make($data, $rules, [
            'required' => 'The :attribute field is required.',
            'string' => 'The :attribute must be a string.',
            'integer' => 'The :attribute must be an integer.',
            'numeric' => 'The :attribute must be a number.',
            'boolean' => 'The :attribute must be true or false.',
            'array' => 'The :attribute must be an array.',
            'max' => 'The :attribute may not be greater than :max.',
            'min' => 'The :attribute must be at least :min.',
            'in' => 'The selected :attribute is invalid.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Get all supported element types
     *
     * @return array
     */
    public static function getSupportedTypes(): array
    {
        return array_keys(self::SCHEMAS);
    }

    /**
     * Check if element type is supported
     *
     * @param string $type
     * @return bool
     */
    public static function isSupportedType(string $type): bool
    {
        return isset(self::SCHEMAS[$type]);
    }
}
