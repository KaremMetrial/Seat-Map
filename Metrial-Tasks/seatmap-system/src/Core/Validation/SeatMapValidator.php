<?php

namespace App\Core\Validation;

/**
 * Comprehensive seat map validator
 * Validates templates, elements, and business rules
 */
class SeatMapValidator
{
    /**
     * Validate a complete seat map template
     */
    public static function validateTemplate(array $template): array
    {
        $errors = [];

        // Validate required fields
        if (empty($template['name'])) {
            $errors[] = 'Template name is required';
        }

        if (empty($template['width']) || $template['width'] < 100) {
            $errors[] = 'Template width must be at least 100';
        }

        if (empty($template['height']) || $template['height'] < 100) {
            $errors[] = 'Template height must be at least 100';
        }

        // Validate elements if present
        if (!empty($template['elements'])) {
            $elementErrors = self::validateElements($template['elements']);
            $errors = array_merge($errors, $elementErrors);
        }

        return $errors;
    }

    /**
     * Validate a collection of elements
     */
    public static function validateElements(array $elements): array
    {
        $errors = [];

        foreach ($elements as $index => $element) {
            $elementErrors = self::validateElement($element);
            foreach ($elementErrors as $error) {
                $errors[] = "Element {$index}: {$error}";
            }
        }

        return $errors;
    }

    /**
     * Validate a single element
     */
    public static function validateElement(array $element): array
    {
        $errors = [];

        // Required fields
        if (empty($element['element_type'])) {
            $errors[] = 'Element type is required';
        } else {
            $validTypes = ['seat', 'section', 'table', 'stage', 'shape', 'entrance', 'text'];
            if (!in_array($element['element_type'], $validTypes)) {
                $errors[] = "Invalid element type: {$element['element_type']}";
            }
        }

        // Coordinate validation
        if (!isset($element['x']) || !is_numeric($element['x'])) {
            $errors[] = 'X coordinate is required and must be numeric';
        } elseif ($element['x'] < 0 || $element['x'] > 10000) {
            $errors[] = 'X coordinate must be between 0 and 10000';
        }

        if (!isset($element['y']) || !is_numeric($element['y'])) {
            $errors[] = 'Y coordinate is required and must be numeric';
        } elseif ($element['y'] < 0 || $element['y'] > 10000) {
            $errors[] = 'Y coordinate must be between 0 and 10000';
        }

        // Dimension validation
        if (isset($element['width']) && $element['width'] < 0) {
            $errors[] = 'Width must be non-negative';
        }

        if (isset($element['height']) && $element['height'] < 0) {
            $errors[] = 'Height must be non-negative';
        }

        // Type-specific validation
        if ($element['element_type'] === 'seat') {
            $seatErrors = self::validateSeat($element);
            $errors = array_merge($errors, $seatErrors);
        }

        if ($element['element_type'] === 'table') {
            $tableErrors = self::validateTable($element);
            $errors = array_merge($errors, $tableErrors);
        }

        return $errors;
    }

    /**
     * Validate seat-specific fields
     */
    private static function validateSeat(array $seat): array
    {
        $errors = [];

        $data = $seat['data'] ?? $seat['data_json'] ?? [];

        if (empty($data['row']) && empty($data['label'])) {
            $errors[] = 'Seat must have a row or label';
        }

        if (isset($data['seat_type'])) {
            $validTypes = ['regular', 'wheelchair', 'companion'];
            if (!in_array($data['seat_type'], $validTypes)) {
                $errors[] = "Invalid seat type: {$data['seat_type']}";
            }
        }

        return $errors;
    }

    /**
     * Validate table-specific fields
     */
    private static function validateTable(array $table): array
    {
        $errors = [];

        $data = $table['data'] ?? $table['data_json'] ?? [];

        if (isset($data['capacity'])) {
            if ($data['capacity'] < 2 || $data['capacity'] > 20) {
                $errors[] = 'Table capacity must be between 2 and 20';
            }
        }

        if (isset($data['shape'])) {
            $validShapes = ['round', 'rectangular', 'square', 'oval'];
            if (!in_array($data['shape'], $validShapes)) {
                $errors[] = "Invalid table shape: {$data['shape']}";
            }
        }

        return $errors;
    }

    /**
     * Validate element style
     */
    public static function validateStyle(array $style): array
    {
        $errors = [];

        if (isset($style['fill']) && !preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $style['fill'])) {
            $errors[] = 'Fill color must be a valid HEX color';
        }

        if (isset($style['stroke']) && !preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $style['stroke'])) {
            $errors[] = 'Stroke color must be a valid HEX color';
        }

        if (isset($style['stroke_width']) && ($style['stroke_width'] < 0 || $style['stroke_width'] > 10)) {
            $errors[] = 'Stroke width must be between 0 and 10';
        }

        if (isset($style['opacity']) && ($style['opacity'] < 0 || $style['opacity'] > 1)) {
            $errors[] = 'Opacity must be between 0 and 1';
        }

        return $errors;
    }

    /**
     * Validate pricing rules
     */
    public static function validatePricingRules(array $rules): array
    {
        $errors = [];

        foreach ($rules as $index => $rule) {
            if (empty($rule['zone_id'])) {
                $errors[] = "Rule {$index}: Zone ID is required";
            }

            if (!isset($rule['price_modifier']) || !is_numeric($rule['price_modifier'])) {
                $errors[] = "Rule {$index}: Price modifier must be numeric";
            }

            if (isset($rule['modifier_type']) && !in_array($rule['modifier_type'], ['fixed', 'percentage', 'multiplier'])) {
                $errors[] = "Rule {$index}: Invalid modifier type";
            }
        }

        return $errors;
    }
}