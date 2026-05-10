<?php

namespace App\Core\Validation;

use App\Core\Geometry\ProceduralLayoutGenerator;
use App\Core\Geometry\Transform;

/**
 * Enhanced spatial validation with indexing
 */
class SpatialValidator
{
    private const GRID_CELL_SIZE = 100; // Size of each grid cell for spatial indexing

    /**
     * Check for spatial conflicts with spatial indexing optimization
     *
     * @param array $newElement New element to check
     * @param array $existingElements Existing elements
     * @param array $options Options including buffer_zones
     * @return array ['valid' => bool, 'conflicts' => array, 'message' => string]
     */
    public static function checkSpatialConflict(array $newElement, array $existingElements, array $options = []): array
    {
        $bufferZones = $options['buffer_zones'] ?? 0;
        $conflicts = [];

        // Build spatial index for existing elements
        $spatialIndex = self::buildSpatialIndex($existingElements);

        // Get potential conflict cells for new element
        $conflictCells = self::getElementCells($newElement, $bufferZones);

        // Only check elements in conflicting cells
        $candidates = [];
        foreach ($conflictCells as $cellKey) {
            if (isset($spatialIndex[$cellKey])) {
                $candidates = array_merge($candidates, $spatialIndex[$cellKey]);
            }
        }

        // Remove duplicates
        $candidates = array_unique($candidates, SORT_REGULAR);

        // Check actual conflicts
        $newRect = self::getElementRect($newElement, $bufferZones);

        foreach ($candidates as $elementId) {
            $existing = self::findElementById($existingElements, $elementId);
            if (!$existing) {
                continue;
            }

            // Skip if same element (when updating)
            $excludeIds = $options['exclude_ids'] ?? [];
            if (in_array($existing['id'] ?? null, $excludeIds, true)) {
                continue;
            }

            $existingRect = self::getElementRect($existing);

            if (self::rectanglesOverlap($newRect, $existingRect)) {
                $conflicts[] = [
                    'id' => $existing['id'] ?? null,
                    'type' => $existing['element_type'] ?? 'unknown',
                    'label' => $existing['data']['label'] ?? "Element {$existing['id']}",
                    'position' => ['x' => $existing['x'], 'y' => $existing['y']],
                ];
            }
        }

        return [
            'valid' => empty($conflicts),
            'conflicts' => $conflicts,
            'message' => empty($conflicts) ? '' : 'Spatial conflict detected with ' . count($conflicts) . ' element(s)',
        ];
    }

    /**
     * Build spatial index for fast collision detection
     */
    private static function buildSpatialIndex(array $elements): array
    {
        $index = [];

        foreach ($elements as $element) {
            $cells = self::getElementCells($element);
            foreach ($cells as $cellKey) {
                if (!isset($index[$cellKey])) {
                    $index[$cellKey] = [];
                }
                $index[$cellKey][] = $element['id'] ?? null;
            }
        }

        return $index;
    }

    /**
     * Get grid cells that an element occupies
     */
    private static function getElementCells(array $element, float $buffer = 0): array
    {
        $rect = self::getElementRect($element, $buffer);
        $cells = [];

        $minCellX = floor($rect['x1'] / self::GRID_CELL_SIZE);
        $maxCellX = floor($rect['x2'] / self::GRID_CELL_SIZE);
        $minCellY = floor($rect['y1'] / self::GRID_CELL_SIZE);
        $maxCellY = floor($rect['y2'] / self::GRID_CELL_SIZE);

        for ($x = $minCellX; $x <= $maxCellX; $x++) {
            for ($y = $minCellY; $y <= $maxCellY; $y++) {
                $cells[] = "{$x},{$y}";
            }
        }

        return $cells;
    }

    /**
     * Get element bounding rectangle
     */
    private static function getElementRect(array $element, float $buffer = 0): array
    {
        return [
            'x1' => ($element['x'] ?? 0) - $buffer,
            'y1' => ($element['y'] ?? 0) - $buffer,
            'x2' => ($element['x'] ?? 0) + ($element['width'] ?? 0) + $buffer,
            'y2' => ($element['y'] ?? 0) + ($element['height'] ?? 0) + $buffer,
        ];
    }

    /**
     * Check if two rectangles overlap
     */
    private static function rectanglesOverlap(array $a, array $b): bool
    {
        return !($a['x2'] <= $b['x1'] ||
                 $a['x1'] >= $b['x2'] ||
                 $a['y2'] <= $b['y1'] ||
                 $a['y1'] >= $b['y2']);
    }

    /**
     * Find element by ID
     */
    private static function findElementById(array $elements, $id): ?array
    {
        foreach ($elements as $element) {
            if (($element['id'] ?? null) === $id) {
                return $element;
            }
        }
        return null;
    }

    /**
     * Validate clearance zones
     */
    public static function validateClearanceZones(array $element, array $allElements, array $constraints = []): array
    {
        $violations = [];
        $entranceBuffer = $constraints['entrance_buffer'] ?? 50;
        $aisleMinWidth = $constraints['aisle_min_width'] ?? 120;

        // Use spatial index for faster lookup
        $spatialIndex = self::buildSpatialIndex($allElements);
        $elementCells = self::getElementCells($element, $entranceBuffer);

        $candidates = [];
        foreach ($elementCells as $cellKey) {
            if (isset($spatialIndex[$cellKey])) {
                $candidates = array_merge($candidates, $spatialIndex[$cellKey]);
            }
        }
        $candidates = array_unique($candidates, SORT_REGULAR);

        if (in_array($element['element_type'], ['entrance', 'emergency_exit'], true)) {
            $bufferRect = [
                'x1' => $element['x'] - $entranceBuffer,
                'y1' => $element['y'] - $entranceBuffer,
                'x2' => $element['x'] + ($element['width'] ?? 0) + $entranceBuffer,
                'y2' => $element['y'] + ($element['height'] ?? 0) + $entranceBuffer,
            ];

            foreach ($candidates as $elementId) {
                $other = self::findElementById($allElements, $elementId);
                if (!$other || $other['id'] === ($element['id'] ?? null)) {
                    continue;
                }

                $otherRect = self::getElementRect($other);

                if (self::rectanglesOverlap($bufferRect, $otherRect) &&
                    in_array($other['element_type'], ['seat', 'table', 'section'], true)) {
                    $violations[] = "Element '{$element['data']['label']}' has obstructed clearance zone by {$other['element_type']} '{$other['data']['label']}'";
                }
            }
        }

        if (in_array($element['element_type'], ['aisle', 'corridor'], true)) {
            $width = $element['width'] ?? 0;
            if ($width < $aisleMinWidth) {
                $violations[] = "Aisle '{$element['data']['label']}': width {$width}cm < minimum required {$aisleMinWidth}cm";
            }
        }

        return $violations;
    }

    /**
     * Validate accessibility proximity with spatial optimization
     */
    public static function validateAccessibilityProximity(
        array $element,
        array $allElements,
        float $scaleFactor,
        array $constraints = []
    ): array {
        $violations = [];
        $toiletMaxDist = $constraints['toilet_max_distance_m'] ?? 30;
        $musterMaxDist = $constraints['muster_max_distance_m'] ?? 50;

        $seatType = $element['data']['seat_type'] ?? null;
        $isAccessible = in_array($seatType, ['wheelchair', 'companion'], true);

        if (!($element['element_type'] === 'seat' && $isAccessible)) {
            return $violations;
        }

        $elX = $element['x'] + ($element['width'] / 2);
        $elY = $element['y'] + ($element['height'] / 2);

        // Use spatial index to limit distance checks
        $spatialIndex = self::buildSpatialIndex($allElements);
        $searchRadius = max($toiletMaxDist, $musterMaxDist) / $scaleFactor;
        $searchCells = self::getElementCells([
            'x' => $elX - $searchRadius,
            'y' => $elY - $searchRadius,
            'width' => $searchRadius * 2,
            'height' => $searchRadius * 2,
        ], 0);

        $candidates = [];
        foreach ($searchCells as $cellKey) {
            if (isset($spatialIndex[$cellKey])) {
                $candidates = array_merge($candidates, $spatialIndex[$cellKey]);
            }
        }
        $candidates = array_unique($candidates, SORT_REGULAR);

        $nearestToilet = null;
        $minToiletDist = PHP_INT_MAX;
        $nearestMuster = null;
        $minMusterDist = PHP_INT_MAX;

        foreach ($candidates as $elementId) {
            $other = self::findElementById($allElements, $elementId);
            if (!$other || $other['id'] === ($element['id'] ?? null)) {
                continue;
            }

            $otherX = $other['x'] + ($other['width'] / 2);
            $otherY = $other['y'] + ($other['height'] / 2);
            $dx = $elX - $otherX;
            $dy = $elY - $otherY;
            $distCanvas = sqrt($dx*$dx + $dy*$dy);
            $distMeters = $distCanvas * $scaleFactor;

            if ($other['element_type'] === 'toilet' && ($other['data']['accessible'] ?? false)) {
                if ($distMeters < $minToiletDist) {
                    $minToiletDist = $distMeters;
                    $nearestToilet = $other;
                }
            }

            if ($other['element_type'] === 'zone' && ($other['data']['zone_type'] ?? '') === 'muster_station') {
                if ($distMeters < $minMusterDist) {
                    $minMusterDist = $distMeters;
                    $nearestMuster = $other;
                }
            }
        }

        if ($nearestToilet === null) {
            $violations[] = "Wheelchair seat '{$element['data']['label']}': No accessible toilet found within {$toiletMaxDist}m";
        } elseif ($minToiletDist > $toiletMaxDist) {
            $violations[] = "Wheelchair seat '{$element['data']['label']}': Nearest accessible toilet is {$minToiletDist}m away (max: {$toiletMaxDist}m)";
        }

        if ($nearestMuster === null) {
            $violations[] = "Wheelchair seat '{$element['data']['label']}': No muster station found within {$musterMaxDist}m";
        } elseif ($minMusterDist > $musterMaxDist) {
            $violations[] = "Wheelchair seat '{$element['data']['label']}': Nearest muster station is {$minMusterDist}m away (max: {$musterMaxDist}m)";
        }

        return $violations;
    }
}