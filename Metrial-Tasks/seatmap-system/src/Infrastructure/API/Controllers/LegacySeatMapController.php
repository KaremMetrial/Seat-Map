<?php

namespace App\Infrastructure\API\Controllers;

use App\Core\Validation\SeatMapValidator;
use App\Core\Validation\SpatialValidator;
use App\Core\Geometry\ProceduralLayoutGenerator;
use App\Core\Geometry\Transform;
use App\Core\Rendering\SVGRenderer;
use App\Core\DataTransfer\SeatMapDTO;
use App\Infrastructure\Persistence\SeatMapRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Legacy API Controller - Compatible with existing Metrial Task system
 * Provides backward compatibility while adding new features
 */
class LegacySeatMapController extends SeatMapController
{
    /**
     * Generate seats with legacy format (backward compatible)
     */
    public function generateSeatsLegacy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rows' => 'required|integer|min:1|max:100',
            'cols' => 'required|integer|min:1|max:100',
            'spacing' => 'required|numeric|min:10|max:500',
            'template_id' => 'sometimes|exists:venue_templates,id',
        ]);

        $generator = new ProceduralLayoutGenerator();

        $elements = $generator->generateGrid(
            $validated['rows'],
            $validated['cols'],
            $validated['spacing'],
            [
                'elementType' => 'seat',
                'width' => $validated['spacing'] * 0.8,
                'height' => $validated['spacing'] * 0.8,
                'baseData' => [
                    'seat_type' => 'regular',
                    'accessibility' => [
                        'wheelchair' => false,
                        'hearing_assistance' => false,
                        'visual_assistance' => false,
                    ],
                ],
                'style' => [
                    'fill' => '#4a90e2',
                    'stroke' => '#2c5aa0',
                    'stroke_width' => 1,
                    'opacity' => 1,
                ],
            ]
        );

        // Legacy format response
        return response()->json([
            'success' => true,
            'data' => [
                'elements' => $elements,
                'count' => count($elements),
                'template_id' => $validated['template_id'] ?? null,
                'generated_at' => now()->toDateTimeString(),
            ],
            'meta' => [
                'version' => '2.0',
                'legacy_compatible' => true,
            ],
        ]);
    }

    /**
     * Validate template elements (legacy endpoint)
     */
    public function validateElementsLegacy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'elements' => 'required|array',
            'template_id' => 'sometimes|exists:venue_templates,id',
        ]);

        $validator = new SeatMapValidator();
        $errors = $validator->validateElements($validated['elements']);

        // Check spatial conflicts
        $conflicts = [];
        if (count($validated['elements']) > 1) {
            for ($i = 0; $i < count($validated['elements']); $i++) {
                $otherElements = $validated['elements'];
                array_splice($otherElements, $i, 1);
                
                $result = SpatialValidator::checkSpatialConflict(
                    $validated['elements'][$i],
                    $otherElements,
                    ['buffer_zones' => 0]
                );
                
                if (!$result['valid']) {
                    $conflicts = array_merge($conflicts, $result['conflicts']);
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'valid' => empty($errors) && empty($conflicts),
                'errors' => $errors,
                'conflicts' => $conflicts,
                'element_count' => count($validated['elements']),
            ],
        ]);
    }

    /**
     * Import existing template elements
     */
    public function importTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => 'required|exists:venue_templates,id',
            'include_inactive' => 'sometimes|boolean',
        ]);

        $repository = new SeatMapRepository();
        $seatMap = $repository->findById($validated['template_id']);

        if (!$seatMap) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found',
            ], 404);
        }

        // Convert to legacy format
        $elements = array_map(function ($element) {
            if ($element instanceof \App\Core\DataTransfer\ElementDTO) {
                $element = $element->toArray();
            }
            
            return [
                'id' => $element['id'],
                'element_type' => $element['element_type'],
                'x' => $element['x'],
                'y' => $element['y'],
                'z' => $element['z'],
                'width' => $element['width'],
                'height' => $element['height'],
                'rotation' => $element['rotation'],
                'z_index' => $element['z_index'],
                'data' => $element['data'],
                'style' => $element['style'],
            ];
        }, $seatMap['elements'] ?? $seatMap->getElements());

        return response()->json([
            'success' => true,
            'data' => [
                'template' => [
                    'id' => $seatMap->getId(),
                    'name' => $seatMap->getName(),
                    'width' => $seatMap->getWidth(),
                    'height' => $seatMap->getHeight(),
                ],
                'elements' => $elements,
                'count' => count($elements),
            ],
        ]);
    }

    /**
     * Export template with enhanced metadata
     */
    public function exportTemplate(Request $request, string $id): JsonResponse
    {
        $repository = new SeatMapRepository();
        $seatMap = $repository->findById($id);

        if (!$seatMap) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found',
            ], 404);
        }

        $format = $request->query('format', 'json');

        if ($format === 'svg') {
            $renderer = new SVGRenderer();
            $svg = $renderer->renderSeatMap($seatMap->getElements(), [
                'width' => $seatMap->getWidth(),
                'height' => $seatMap->getHeight(),
                'showLabels' => true,
                'interactive' => false,
            ]);

            return response($svg)
                ->header('Content-Type', 'image/svg+xml')
                ->header('Content-Disposition', 'attachment; filename="' . $seatMap->getName() . '.svg"');
        }

        // Default JSON export
        $export = [
            'metadata' => [
                'exported_at' => now()->toDateTimeString(),
                'version' => '2.0',
                'format' => 'enhanced',
            ],
            'template' => $seatMap->toArray(),
        ];

        return response()->json($export);
    }

    /**
     * Bulk create elements with validation
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => 'required|exists:venue_templates,id',
            'elements' => 'required|array|min:1',
            'validate_conflicts' => 'sometimes|boolean',
        ]);

        $validator = new SeatMapValidator();
        $errors = [];

        // Validate all elements
        foreach ($validated['elements'] as $index => $element) {
            $elementErrors = $validator->validateElement($element);
            if (!empty($elementErrors)) {
                $errors["element_{$index}"] = $elementErrors;
            }
        }

        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errors,
            ], 422);
        }

        // Check spatial conflicts if requested
        if ($validated['validate_conflicts'] ?? false) {
            $conflicts = [];
            for ($i = 0; $i < count($validated['elements']); $i++) {
                $otherElements = $validated['elements'];
                array_splice($otherElements, $i, 1);
                
                $result = SpatialValidator::checkSpatialConflict(
                    $validated['elements'][$i],
                    $otherElements,
                    ['buffer_zones' => 0]
                );
                
                if (!$result['valid']) {
                    $conflicts = array_merge($conflicts, $result['conflicts']);
                }
            }

            if (!empty($conflicts)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Spatial conflicts detected',
                    'conflicts' => $conflicts,
                ], 422);
            }
        }

        // Save elements
        $repository = new SeatMapRepository();
        $seatMap = $repository->findById($validated['template_id']);
        
        $existingElements = $seatMap->getElements();
        $newElements = array_merge($existingElements, $validated['elements']);
        
        $updatedSeatMap = new SeatMapDTO([
            'id' => $seatMap->getId(),
            'name' => $seatMap->getName(),
            'width' => $seatMap->getWidth(),
            'height' => $seatMap->getHeight(),
            'elements' => $newElements,
            'metadata' => $seatMap->getMetadata(),
            'zones' => $seatMap->getZones(),
            'pricing_tiers' => $seatMap->getPricingTiers(),
            'version' => $seatMap->getVersion(),
        ]);

        $success = $repository->save($updatedSeatMap);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save elements',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'created' => count($validated['elements']),
                'total_elements' => count($newElements),
                'template_id' => $seatMap->getId(),
            ],
        ], 201);
    }
}

// Helper function for backward compatibility
if (!function_exists('generate_seat_layout')) {
    /**
     * Legacy helper function for seat layout generation
     * 
     * @deprecated Use SeatMapController::generateGrid instead
     */
    function generate_seat_layout(int $rows, int $cols, float $spacing, array $options = []): array
    {
        $generator = new ProceduralLayoutGenerator();
        return $generator->generateGrid($rows, $cols, $spacing, $options);
    }
}

if (!function_exists('validate_seatmap_conflicts')) {
    /**
     * Legacy helper function for conflict validation
     * 
     * @deprecated Use SpatialValidator::checkSpatialConflict instead
     */
    function validate_seatmap_conflicts(array $element, array $existing, array $options = []): array
    {
        return SpatialValidator::checkSpatialConflict($element, $existing, $options);
    }
}
