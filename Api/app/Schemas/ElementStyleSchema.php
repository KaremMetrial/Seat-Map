<?php

declare(strict_types=1);

namespace App\Schemas;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * JSON Style Schema for Template Elements
 * 
 * Validates style_json fields for visual rendering.
 */
class ElementStyleSchema
{
    /**
     * Validation rules for style properties
     */
    private const RULES = [
        // Fill and stroke colors
        'fill' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
        'stroke' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
        'strokeWidth' => 'nullable|numeric|min:0|max:20',
        'stroke_width' => 'nullable|numeric|min:0|max:20',
        
        // Opacity
        'opacity' => 'nullable|numeric|min:0|max:1',
        'fill_opacity' => 'nullable|numeric|min:0|max:1',
        'stroke_opacity' => 'nullable|numeric|min:0|max:1',
        
        // Border radius
        'rx' => 'nullable|numeric|min:0',
        'ry' => 'nullable|numeric|min:0',
        'border_radius' => 'nullable|numeric|min:0|max:100',
        
        // Typography
        'font_family' => 'nullable|string|max:100',
        'font_size' => 'nullable|integer|min:8|max:200',
        'font_weight' => 'nullable|in:normal,bold,100,200,300,400,500,600,700,800,900',
        'text_align' => 'nullable|in:left,center,right',
        
        // Effects
        'shadow' => 'nullable|array',
        'shadow.color' => 'nullable|string|regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
        'shadow.blur' => 'nullable|numeric|min:0|max:50',
        'shadow.offset_x' => 'nullable|numeric',
        'shadow.offset_y' => 'nullable|numeric',
        
        // Transform
        'transform_origin' => 'nullable|string',
        
        // Cursor
        'cursor' => 'nullable|in:default,pointer,crosshair,move,text,not-allowed',
    ];

    /**
     * Validate style data
     *
     * @param array|null $style
     * @return array Validated data
     * @throws ValidationException
     */
    public static function validate(?array $style): array
    {
        if ($style === null) {
            return [];
        }

        $validator = Validator::make($style, self::RULES, [
            'regex' => 'The :attribute must be a valid hex color (e.g., #FF0000 or #F00).',
            'numeric' => 'The :attribute must be a number.',
            'integer' => 'The :attribute must be an integer.',
            'in' => 'The selected :attribute is invalid.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Get default style for an element type
     *
     * @param string $elementType
     * @return array
     */
    public static function getDefaultStyle(string $elementType): array
    {
        $defaults = [
            'seat' => [
                'fill' => '#10b981',
                'stroke' => '#ffffff',
                'strokeWidth' => 1,
                'opacity' => 1,
            ],
            'table' => [
                'fill' => '#3b82f6',
                'stroke' => '#1e40af',
                'strokeWidth' => 2,
                'border_radius' => 4,
            ],
            'stage' => [
                'fill' => '#64748b',
                'stroke' => '#94a3b8',
                'strokeWidth' => 2,
            ],
            'entrance' => [
                'fill' => '#ef4444',
                'stroke' => '#fca5a5',
                'strokeWidth' => 2,
            ],
            'emergency_exit' => [
                'fill' => '#dc2626',
                'stroke' => '#fecaca',
                'strokeWidth' => 3,
            ],
            'aisle' => [
                'fill' => '#f5f5f5',
                'stroke' => '#d4d4d4',
                'strokeWidth' => 1,
            ],
            'standing_zone' => [
                'fill' => '#fbbf24',
                'stroke' => '#f59e0b',
                'strokeWidth' => 2,
                'opacity' => 0.5,
            ],
            'section' => [
                'fill' => '#6366f1',
                'stroke' => '#4f46e5',
                'strokeWidth' => 2,
            ],
            'text' => [
                'font_family' => 'Arial, sans-serif',
                'font_size' => 14,
                'font_weight' => 'normal',
                'fill' => '#000000',
            ],
            'shape' => [
                'fill' => '#94a3b8',
                'stroke' => '#64748b',
                'strokeWidth' => 1,
                'opacity' => 0.8,
            ],
            'toilet' => [
                'fill' => '#06b6d4',
                'stroke' => '#0891b2',
                'strokeWidth' => 1,
            ],
            'corridor' => [
                'fill' => '#e5e7eb',
                'stroke' => '#d1d5db',
                'strokeWidth' => 1,
            ],
            'zone' => [
                'fill' => '#8b5cf6',
                'stroke' => '#7c3aed',
                'strokeWidth' => 2,
                'opacity' => 0.3,
            ],
        ];

        return $defaults[$elementType] ?? [
            'fill' => '#64748b',
            'stroke' => '#475569',
            'strokeWidth' => 1,
        ];
    }
}
