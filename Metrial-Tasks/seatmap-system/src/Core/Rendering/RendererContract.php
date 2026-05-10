<?php

namespace App\Core\Rendering;

/**
 * Interface for rendering seat maps to different outputs (SVG, Canvas, WebGL)
 */
interface RendererContract
{
    /**
     * Render a complete seat map
     *
     * @param array $elements Array of element data
     * @param array $options Rendering options:
     *        - width: Viewport width
     *        - height: Viewport height
     *        - backgroundColor: Background color
     *        - showLabels: Whether to show element labels
     *        - interactive: Whether to enable interactivity (hover/click)
     * @return string Rendered output (SVG string, canvas data, etc.)
     */
    public function renderSeatMap(array $elements, array $options = []): string;

    /**
     * Render a single element
     *
     * @param array $element Element data
     * @param array $options Element-specific rendering options
     * @return string Rendered element
     */
    public function renderElement(array $element, array $options = []): string;

    /**
     * Get interaction data for a point (for hover/click detection)
     *
     * @param float $x X coordinate in viewport space
     * @param float $y Y coordinate in viewport space
     * @return array|null Information about the element at this point, or null if none
     */
    public function getInteractionData(float $x, float $y): ?array;

    /**
     * Set the viewport size
     *
     * @param int $width Viewport width
     * @param int $height Viewport height
     * @return void
     */
    public function setViewport(int $width, int $height): void;

    /**
     * Set camera parameters (for 3D renderers)
     *
     * @param array $position Camera position ['x'=>0, 'y'=>0, 'z'=>10]
     * @param array $target Camera target ['x'=>0, 'y'=>0, 'z'=>0]
     * @param array $up Camera up vector ['x'=>0, 'y'=>1, 'z'=>0]
     * @return void
     */
    public function setCamera(array $position, array $target, array $up = ['x' => 0, 'y' => 1, 'z' => 0]): void;

    /**
     * Clear the renderer state
     *
     * @return void
     */
    public function clear(): void;
}