<?php

namespace App\Core\Rendering;

/**
 * SVG Renderer for seat maps
 * Generates scalable vector graphics suitable for web display
 */
class SVGRenderer implements RendererContract
{
    private int $width = 800;
    private int $height = 600;
    private array $elements = [];
    private array $viewport = ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 600];

    public function renderSeatMap(array $elements, array $options = []): string
    {
        $this->elements = $elements;
        $this->width = $options['width'] ?? $this->width;
        $this->height = $options['height'] ?? $this->height;
        $backgroundColor = $options['backgroundColor'] ?? '#ffffff';
        $showLabels = $options['showLabels'] ?? true;
        $interactive = $options['interactive'] ?? false;

        $svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $svg .= '<svg xmlns="http://www.w3.org/2000/svg" ';
        $svg .= 'xmlns:xlink="http://www.w3.org/1999/xlink" ';
        $svg .= 'width="' . $this->width . '" ';
        $svg .= 'height="' . $this->height . '" ';
        $svg .= 'viewBox="0 0 ' . $this->width . ' ' . $this->height . '" ';
        
        if ($interactive) {
            $svg .= 'class="seatmap-svg" ';
        }
        
        $svg .= '>' . "\n";
        
        // Background
        $svg .= '  <rect width="' . $this->width . '" height="' . $this->height . '" ';
        $svg .= 'fill="' . $backgroundColor . '" />' . "\n";

        // Render each element
        foreach ($this->elements as $element) {
            $svg .= $this->renderElementToSVG($element, $showLabels, $interactive);
        }

        $svg .= '</svg>' . "\n";

        return $svg;
    }

    private function renderElementToSVG(array $element, bool $showLabels, bool $interactive): string
    {
        $type = $element['element_type'] ?? 'unknown';
        $x = $element['x'] ?? 0;
        $y = $element['y'] ?? 0;
        $width = $element['width'] ?? 0;
        $height = $element['height'] ?? 0;
        $rotation = $element['rotation'] ?? 0;
        $data = $element['data_json'] ?? [];
        $style = $element['style_json'] ?? [];

        $fill = $style['fill'] ?? $this->getDefaultFill($type);
        $stroke = $style['stroke'] ?? $this->getDefaultStroke($type);
        $strokeWidth = $style['stroke_width'] ?? 1;
        $opacity = $style['opacity'] ?? 1;
        $rx = $style['rx'] ?? 0;
        $ry = $style['ry'] ?? 0;

        $label = $data['label'] ?? '';
        $seatRow = $data['row'] ?? '';
        $seatNumber = $data['seat_number'] ?? '';

        $elementId = $element['id'] ?? uniqid('el_');
        $classes = ['seatmap-element', 'seatmap-' . $type];
        
        if ($type === 'seat') {
            $classes[] = 'seatmap-seat';
            if ($data['seat_type'] ?? null) {
                $classes[] = 'seatmap-seat-' . $data['seat_type'];
            }
        }

        $transform = '';
        if ($rotation != 0) {
            $centerX = $x + $width / 2;
            $centerY = $y + $height / 2;
            $transform = 'transform="rotate(' . $rotation . ' ' . $centerX . ' ' . $centerY . ')"';
        }

        $interactiveAttrs = '';
        if ($interactive) {
            $classes[] = 'seatmap-interactive';
            $interactiveAttrs = 'data-element-id="' . $elementId . '" ';
            $interactiveAttrs .= 'data-element-type="' . $type . '" ';
            $interactiveAttrs .= 'data-label="' . htmlspecialchars($label) . '" ';
        }

        $svg = '';

        // Element group
        $svg .= '  <g id="' . $elementId . '" class="' . implode(' ', $classes) . '" ';
        $svg .= $interactiveAttrs . $transform . '>' . "\n";

        // Main shape
        switch ($type) {
            case 'seat':
                // Draw seat as rounded rectangle
                $svg .= '    <rect x="' . $x . '" y="' . $y . '" ';
                $svg .= 'width="' . $width . '" height="' . $height . '" ';
                $svg .= 'rx="' . max($rx, 3) . '" ry="' . max($ry, 3) . '" ';
                $svg .= 'fill="' . $fill . '" ';
                $svg .= 'stroke="' . $stroke . '" ';
                $svg .= 'stroke-width="' . $strokeWidth . '" ';
                $svg .= 'opacity="' . $opacity . '" ';
                $svg .= '/>' . "\n";
                break;

            case 'section':
                // Draw section as rectangle with optional curve indicator
                $svg .= '    <rect x="' . $x . '" y="' . $y . '" ';
                $svg .= 'width="' . $width . '" height="' . $height . '" ';
                $svg .= 'rx="' . $rx . '" ry="' . $ry . '" ';
                $svg .= 'fill="' . $fill . '" ';
                $svg .= 'stroke="' . $stroke . '" ';
                $svg .= 'stroke-width="' . $strokeWidth . '" ';
                $svg .= 'opacity="' . $opacity . '" ';
                $svg .= '/>' . "\n";
                
                // Add curve indicator if present
                if (isset($data['curve'])) {
                    $curve = $data['curve'];
                    $svg .= '    <path d="M' . ($x + $width/2) . ' ' . ($y + 10) . ' ';
                    $svg .= 'Q' . ($x + $width/2) . ' ' . ($y + 5) . ' ';
                    $svg .= ($x + $width/2 - 10) . ' ' . ($y + 15) . '" ';
                    $svg .= 'fill="none" stroke="#666" stroke-width="1" opacity="0.5"/>' . "\n";
                }
                break;

            case 'table':
                // Draw table based on shape
                $tableShape = $data['shape'] ?? 'rectangular';
                if ($tableShape === 'round' || $tableShape === 'oval') {
                    $svg .= '    <ellipse cx="' . ($x + $width/2) . '" cy="' . ($y + $height/2) . '" ';
                    $svg .= 'rx="' . ($width/2) . '" ry="' . ($height/2) . '" ';
                    $svg .= 'fill="' . $fill . '" ';
                    $svg .= 'stroke="' . $stroke . '" ';
                    $svg .= 'stroke-width="' . $strokeWidth . '" ';
                    $svg .= 'opacity="' . $opacity . '" ';
                    $svg .= '/>' . "\n";
                } else {
                    $svg .= '    <rect x="' . $x . '" y="' . $y . '" ';
                    $svg .= 'width="' . $width . '" height="' . $height . '" ';
                    $svg .= 'rx="' . $rx . '" ry="' . $ry . '" ';
                    $svg .= 'fill="' . $fill . '" ';
                    $svg .= 'stroke="' . $stroke . '" ';
                    $svg .= 'stroke-width="' . $strokeWidth . '" ';
                    $svg .= 'opacity="' . $opacity . '" ';
                    $svg .= '/>' . "\n";
                }
                break;

            case 'stage':
                // Draw stage
                $svg .= '    <rect x="' . $x . '" y="' . $y . '" ';
                $svg .= 'width="' . $width . '" height="' . $height . '" ';
                $svg .= 'fill="' . $fill . '" ';
                $svg .= 'stroke="' . $stroke . '" ';
                $svg .= 'stroke-width="' . $strokeWidth . '" ';
                $svg .= 'opacity="' . $opacity . '" ';
                $svg .= '/>' . "\n";
                
                // Add curtain indicator
                if ($data['has_curtain'] ?? false) {
                    $svg .= '    <line x1="' . $x . '" y1="' . $y . '" ';
                    $svg .= 'x2="' . ($x + $width) . '" y2="' . $y . '" ';
                    $svg .= 'stroke="#333" stroke-width="2" stroke-dasharray="5,5"/>' . "\n";
                }
                break;

            case 'entrance':
                // Draw entrance as wide rectangle with arrow
                $svg .= '    <rect x="' . $x . '" y="' . $y . '" ';
                $svg .= 'width="' . $width . '" height="' . $height . '" ';
                $svg .= 'fill="' . $fill . '" ';
                $svg .= 'stroke="' . $stroke . '" ';
                $svg .= 'stroke-width="' . $strokeWidth . '" ';
                $svg .= 'opacity="' . $opacity . '" ';
                $svg .= '/>' . "\n";
                
                // Add arrow
                $svg .= '    <polygon points="';
                $svg .= ($x + $width/2) . ',' . ($y + 5) . ' ';
                $svg .= ($x + $width/2 - 5) . ',' . ($y + $height - 5) . ' ';
                $svg .= ($x + $width/2 + 5) . ',' . ($y + $height - 5);
                $svg .= '" fill="' . $stroke . '" />' . "\n";
                break;

            case 'text':
                // Draw text element
                $fontSize = $data['font_size'] ?? 14;
                $fontWeight = ($data['is_title'] ?? false) ? 'bold' : 'normal';
                $svg .= '    <text x="' . ($x + 5) . '" y="' . ($y + $fontSize) . '" ';
                $svg .= 'font-size="' . $fontSize . '" ';
                $svg .= 'font-weight="' . $fontWeight . '" ';
                $svg .= 'fill="' . $fill . '" ';
                $svg .= 'opacity="' . $opacity . '">';
                $svg .= htmlspecialchars($data['content'] ?? '');
                $svg .= '</text>' . "\n";
                break;

            default:
                // Generic rectangle for unknown types
                $svg .= '    <rect x="' . $x . '" y="' . $y . '" ';
                $svg .= 'width="' . $width . '" height="' . $height . '" ';
                $svg .= 'fill="' . $fill . '" ';
                $svg .= 'stroke="' . $stroke . '" ';
                $svg .= 'stroke-width="' . $strokeWidth . '" ';
                $svg .= 'opacity="' . $opacity . '" ';
                $svg .= '/>' . "\n";
        }

        // Add label if requested
        if ($showLabels && $label) {
            $svg .= '    <text x="' . ($x + $width/2) . '" y="' . ($y + $height/2 + 4) . '" ';
            $svg .= 'text-anchor="middle" ';
            $svg .= 'font-size="10" ';
            $svg .= 'fill="#333" ';
            $svg .= 'font-weight="bold">';
            $svg .= htmlspecialchars($label);
            $svg .= '</text>' . "\n";
        } elseif ($showLabels && ($seatRow || $seatNumber)) {
            $svg .= '    <text x="' . ($x + $width/2) . '" y="' . ($y + $height/2 + 4) . '" ';
            $svg .= 'text-anchor="middle" ';
            $svg .= 'font-size="9" ';
            $svg .= 'fill="#666">';
            $svg .= htmlspecialchars($seatRow . $seatNumber);
            $svg .= '</text>' . "\n";
        }

        $svg .= '  </g>' . "\n";

        return $svg;
    }

    private function getDefaultFill(string $type): string
    {
        $fills = [
            'seat' => '#4a90e2',
            'section' => '#e8f4f8',
            'table' => '#8b4513',
            'stage' => '#666666',
            'entrance' => '#5cb85c',
            'text' => 'transparent',
            'shape' => '#f0f0f0',
        ];
        return $fills[$type] ?? '#cccccc';
    }

    private function getDefaultStroke(string $type): string
    {
        $strokes = [
            'seat' => '#2c5aa0',
            'section' => '#4a90e2',
            'table' => '#5d3a1a',
            'stage' => '#333333',
            'entrance' => '#3d8b40',
            'text' => 'transparent',
            'shape' => '#999999',
        ];
        return $strokes[$type] ?? '#999999';
    }

    public function renderElement(array $element, array $options = []): string
    {
        return $this->renderElementToSVG($element, $options['showLabels'] ?? true, false);
    }

    public function getInteractionData(float $x, float $y): ?array
    {
        // Check elements in reverse order (top-most first)
        foreach (array_reverse($this->elements) as $element) {
            $elX = $element['x'] ?? 0;
            $elY = $element['y'] ?? 0;
            $width = $element['width'] ?? 0;
            $height = $element['height'] ?? 0;

            if ($x >= $elX && $x <= $elX + $width && $y >= $elY && $y <= $elY + $height) {
                return [
                    'id' => $element['id'] ?? null,
                    'type' => $element['element_type'],
                    'data' => $element['data_json'] ?? [],
                    'x' => $elX,
                    'y' => $elY,
                ];
            }
        }
        return null;
    }

    public function setViewport(int $width, int $height): void
    {
        $this->width = $width;
        $this->height = $height;
        $this->viewport = ['x' => 0, 'y' => 0, 'width' => $width, 'height' => $height];
    }

    public function setCamera(array $position, array $target, array $up = ['x' => 0, 'y' => 1, 'z' => 0]): void
    {
        // SVG is 2D, camera settings are ignored but kept for interface compatibility
    }

    public function clear(): void
    {
        $this->elements = [];
    }
}