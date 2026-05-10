<?php

namespace App\Core\Geometry;

/**
 * Interface for procedural layout generation
 */
interface LayoutGeneratorInterface
{
    /**
     * Generate a grid layout of elements
     *
     * @param int $rows Number of rows
     * @param int $cols Number of columns
     * @param float $spacing Spacing between elements (in same units as coordinates)
     * @param array $options Additional options:
     *        - startAt: ['x'=>0, 'y'=>0] Starting position
     *        - elementType: Type of element to generate (default: 'seat')
     *        - baseData: Base data for all elements
     *        - stagger: Boolean for staggered grid
     * @return array Array of element data ready for storage
     */
    public function generateGrid(int $rows, int $cols, float $spacing, array $options = []): array;

    /**
     * Generate elements along a curve/arc
     *
     * @param float $radius Radius of the curve
     * @param float $startAngle Start angle in degrees (0 = right, 90 = down)
     * @param float $endAngle End angle in degrees
     * @param int $count Number of elements to generate
     * @param array $options Additional options:
     *        - center: ['x'=>0, 'y'=>0] Center point
     *        - elementType: Type of element to generate
     *        - baseData: Base data for all elements
     *        - rotateToFaceCenter: Boolean to rotate elements to face center
     * @return array Array of element data ready for storage
     */
    public function generateCurve(float $radius, float $startAngle, float $endAngle, int $count, array $options = []): array;

    /**
     * Generate elements along a custom path
     *
     * @param array $pathPoints Array of points [['x'=>0, 'y'=>0], ...] defining the path
     * @param int $count Number of elements to distribute along the path
     * @param array $options Additional options:
     *        - elementType: Type of element to generate
     *        - baseData: Base data for all elements
     *        - perpendicularOffset: Offset distance perpendicular to path
     *        - alignToPath: Boolean to rotate elements to align with path direction
     * @return array Array of element data ready for storage
     */
    public function generateFromPath(array $pathPoints, int $count, array $options = []): array;

    /**
     * Apply geometric transformations to elements
     *
     * @param array $elements Array of element data
     * @param Transform $transform Transformation to apply
     * @return array Transformed element data
     */
    public function applyTransform(array $elements, Transform $transform): array;

    /**
     * Calculate bounds of a set of elements
     *
     * @param array $elements Array of element data
     * @return array Bounds ['minX'=>0, 'maxX'=>100, 'minY'=>0, 'maxY'=>50]
     */
    public function calculateBounds(array $elements): array;
}