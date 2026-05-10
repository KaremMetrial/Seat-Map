<?php

namespace App\Core\Geometry;

/**
 * Concrete implementation of procedural layout generation
 */
class ProceduralLayoutGenerator implements LayoutGeneratorInterface
{
    /**
     * Generate a grid layout of elements
     *
     * @param int $rows Number of rows
     * @param int $cols Number of columns
     * @param float $spacing Spacing between elements (in same units as coordinates)
     * @param array $options Additional options
     * @return array Array of element data ready for storage
     */
    public function generateGrid(int $rows, int $cols, float $spacing, array $options = []): array
    {
        $startAt = $options['startAt'] ?? ['x' => 0, 'y' => 0];
        $elementType = $options['elementType'] ?? 'seat';
        $baseData = $options['baseData'] ?? [];
        $stagger = $options['stagger'] ?? false;

        $elements = [];

        for ($row = 0; $row < $rows; $row++) {
            $yOffset = $row * $spacing;
            if ($stagger && $row % 2 === 1) {
                $yOffset += $spacing / 2; // Stagger every other row
            }

            for ($col = 0; $col < $cols; $col++) {
                $xOffset = $col * $spacing;

                $element = [
                    'element_type' => $elementType,
                    'x' => $startAt['x'] + $xOffset,
                    'y' => $startAt['y'] + $yOffset,
                    'z' => 0,
                    'width' => $options['width'] ?? 0,
                    'height' => $options['height'] ?? 0,
                    'data_json' => array_merge($baseData, [
                        'row' => chr(65 + $row), // A, B, C, ...
                        'seat_number' => $col + 1,
                    ]),
                    'style_json' => $options['style'] ?? [],
                ];

                $elements[] = $element;
            }
        }

        return $elements;
    }

    /**
     * Generate elements along a curve/arc
     *
     * @param float $radius Radius of the curve
     * @param float $startAngle Start angle in degrees (0 = right, 90 = down)
     * @param float $endAngle End angle in degrees
     * @param int $count Number of elements to generate
     * @param array $options Additional options
     * @return array Array of element data ready for storage
     */
    public function generateCurve(float $radius, float $startAngle, float $endAngle, int $count, array $options = []): array
    {
        $center = $options['center'] ?? ['x' => 0, 'y' => 0];
        $elementType = $options['elementType'] ?? 'seat';
        $baseData = $options['baseData'] ?? [];
        $rotateToFaceCenter = $options['rotateToFaceCenter'] ?? false;

        $elements = [];
        $angleStep = ($endAngle - $startAngle) / max(1, $count - 1);

        for ($i = 0; $i < $count; $i++) {
            $angle = $startAngle + ($i * $angleStep);
            $angleRad = deg2rad($angle);

            $x = $center['x'] + ($radius * cos($angleRad));
            $y = $center['y'] + ($radius * sin($angleRad));

            $rotation = 0;
            if ($rotateToFaceCenter) {
                // Rotate to face the center: angle + 90 degrees (if 0 is right and we want to face inward)
                $rotation = $angle + 90;
            }

            $element = [
                'element_type' => $elementType,
                'x' => $x,
                'y' => $y,
                'z' => 0,
                'width' => $options['width'] ?? 0,
                'height' => $options['height'] ?? 0,
                'rotation' => $rotation,
                'data_json' => array_merge($baseData, [
                    'row' => 'Curve', // For curve, we might not have rows, but we can put a label
                    'seat_number' => $i + 1,
                ]),
                'style_json' => $options['style'] ?? [],
            ];

            $elements[] = $element;
        }

        return $elements;
    }

    /**
     * Generate elements along a custom path
     *
     * @param array $pathPoints Array of points [['x'=>0, 'y'=>0], ...] defining the path
     * @param int $count Number of elements to distribute along the path
     * @param array $options Additional options
     * @return array Array of element data ready for storage
     */
    public function generateFromPath(array $pathPoints, int $count, array $options = []): array
    {
        if (count($pathPoints) < 2) {
            return []; // Need at least two points to define a path
        }

        $elementType = $options['elementType'] ?? 'seat';
        $baseData = $options['baseData'] ?? [];
        $perpendicularOffset = $options['perpendicularOffset'] ?? 0;
        $alignToPath = $options['alignToPath'] ?? false;

        $elements = [];

        // We'll distribute the elements along the path by dividing the path into segments
        // For simplicity, we'll use linear interpolation between points and then distribute evenly by distance.
        // First, calculate the total length of the path and the cumulative lengths at each point.
        $cumulativeLengths = [0];
        $totalLength = 0;
        for ($i = 1; $i < count($pathPoints); $i++) {
            $dx = $pathPoints[$i]['x'] - $pathPoints[$i-1]['x'];
            $dy = $pathPoints[$i]['y'] - $pathPoints[$i-1]['y'];
            $segmentLength = sqrt($dx*$dx + $dy*$dy);
            $totalLength += $segmentLength;
            $cumulativeLengths[$i] = $totalLength;
        }

        // Now, for each element, find its position along the path
        for ($i = 0; $i < $count; $i++) {
            // Position along the path as a fraction [0, 1]
            $fraction = $i / max(1, $count - 1);
            $targetDistance = $fraction * $totalLength;

            // Find which segment we are in
            $segmentIndex = 0;
            while ($segmentIndex < count($cumulativeLengths)-1 && $cumulativeLengths[$segmentIndex+1] < $targetDistance) {
                $segmentIndex++;
            }

            // Interpolate within the segment
            $segmentStart = $pathPoints[$segmentIndex];
            $segmentEnd = $pathPoints[$segmentIndex+1];
            $segmentStartDist = $cumulativeLengths[$segmentIndex];
            $segmentEndDist = $cumulativeLengths[$segmentIndex+1];
            $segmentLength = $segmentEndDist - $segmentStartDist;

            if ($segmentLength > 0) {
                $segmentFraction = ($targetDistance - $segmentStartDist) / $segmentLength;
            } else {
                $segmentFraction = 0;
            }

            $x = $segmentStart['x'] + ($segmentFraction * ($segmentEnd['x'] - $segmentStart['x']));
            $y = $segmentStart['y'] + ($segmentFraction * ($segmentEnd['y'] - $segmentStart['y']));

            // Calculate the direction of the segment (for alignment and perpendicular offset)
            $dx = $segmentEnd['x'] - $segmentStart['x'];
            $dy = $segmentEnd['y'] - $segmentStart['y'];
            $segmentLength = sqrt($dx*$dx + $dy*$dy);

            if ($segmentLength > 0) {
                $unitDX = $dx / $segmentLength;
                $unitDY = $dy / $segmentLength;
                // Perpendicular vector (rotate 90 degrees counter-clockwise)
                $perpDX = -$unitDY;
                $perpDY = $unitDX;
            } else {
                $unitDX = 1;
                $unitDY = 0;
                $perpDX = 0;
                $perpDY = 1;
            }

            // Apply perpendicular offset
            $x += $perpDX * $perpendicularOffset;
            $y += $perpDY * $perpendicularOffset;

            $rotation = 0;
            if ($alignToPath && $segmentLength > 0) {
                // Rotate to align with the path direction (in degrees, assuming 0 is right)
                $rotation = rad2deg(atan2($unitDY, $unitDX));
            }

            $element = [
                'element_type' => $elementType,
                'x' => $x,
                'y' => $y,
                'z' => 0,
                'width' => $options['width'] ?? 0,
                'height' => $options['height'] ?? 0,
                'rotation' => $rotation,
                'data_json' => array_merge($baseData, [
                    'row' => 'Path', // For path, we don't have rows in the traditional sense
                    'seat_number' => $i + 1,
                ]),
                'style_json' => $options['style'] ?? [],
            ];

            $elements[] = $element;
        }

        return $elements;
    }

    /**
     * Apply geometric transformations to elements
     *
     * @param array $elements Array of element data
     * @param Transform $transform Transformation to apply
     * @return array Transformed element data
     */
    public function applyTransform(array $elements, Transform $transform): array
    {
        $transformedElements = [];

        foreach ($elements as $element) {
            // Apply transform to the element's position
            [$newX, $newY, $newZ] = $transform->applyToPoint(
                $element['x'] ?? 0,
                $element['y'] ?? 0,
                $element['z'] ?? 0
            );

            // For rotation, we add the transform's rotation (assuming we are rotating around the element's center)
            // In a more advanced system, we might want to rotate around a pivot point.
            $newRotation = ($element['rotation'] ?? 0) + $transform->getRotateZ();

            // For scale, we multiply the width and height by the scale factors (assuming uniform scale in X and Y)
            $newWidth = ($element['width'] ?? 0) * $transform->getScaleX();
            $newHeight = ($element['height'] ?? 0) * $transform->getScaleY();

            $transformedElement = $element;
            $transformedElement['x'] = $newX;
            $transformedElement['y'] = $newY;
            $transformedElement['z'] = $newZ;
            $transformedElement['rotation'] = $newRotation;
            $transformedElement['width'] = $newWidth;
            $transformedElement['height'] = $newHeight;

            $transformedElements[] = $transformedElement;
        }

        return $transformedElements;
    }

    /**
     * Calculate bounds of a set of elements
     *
     * @param array $elements Array of element data
     * @return array Bounds ['minX'=>0, 'maxX'=>100, 'minY'=>0, 'maxY'=>50]
     */
    public function calculateBounds(array $elements): array
    {
        if (empty($elements)) {
            return ['minX' => 0, 'maxX' => 0, 'minY' => 0, 'maxY' => 0];
        }

        $minX = PHP_FLOAT_MAX;
        $maxX = PHP_FLOAT_MIN;
        $minY = PHP_FLOAT_MAX;
        $maxY = PHP_FLOAT_MIN;

        foreach ($elements as $element) {
            $x = $element['x'] ?? 0;
            $y = $element['y'] ?? 0;
            $width = $element['width'] ?? 0;
            $height = $element['height'] ?? 0;

            // Consider the element's bounding box
            $elementMinX = $x;
            $elementMaxX = $x + $width;
            $elementMinY = $y;
            $elementMaxY = $y + $height;

            if ($elementMinX < $minX) $minX = $elementMinX;
            if ($elementMaxX > $maxX) $maxX = $elementMaxX;
            if ($elementMinY < $minY) $minY = $elementMinY;
            if ($elementMaxY > $maxY) $maxY = $elementMaxY;
        }

        return [
            'minX' => $minX,
            'maxX' => $maxX,
            'minY' => $minY,
            'maxY' => $maxY,
        ];
    }
}