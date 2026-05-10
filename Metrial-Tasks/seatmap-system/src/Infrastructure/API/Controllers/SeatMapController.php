<?php

namespace App\Infrastructure\API\Controllers;

use App\Core\Geometry\ProceduralLayoutGenerator;
use App\Core\Geometry\Transform;
use App\Core\Rendering\SVGRenderer;
use App\Core\DataTransfer\SeatMapDTO;
use App\Infrastructure\Persistence\SeatMapRepository;
use App\Core\Validation\SpatialValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * API Controller for Seat Map operations
 */
class SeatMapController
{
    private ProceduralLayoutGenerator $layoutGenerator;
    private SVGRenderer $svgRenderer;
    private SeatMapRepository $repository;

    public function __construct()
    {
        $this->layoutGenerator = new ProceduralLayoutGenerator();
        $this->svgRenderer = new SVGRenderer();
        $this->repository = new SeatMapRepository();
    }

    /**
     * Generate a grid layout
     */
    public function generateGrid(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rows' => 'required|integer|min:1|max:100',
            'cols' => 'required|integer|min:1|max:100',
            'spacing' => 'required|numeric|min:10|max:500',
            'element_type' => 'sometimes|in:seat,table,chair',
            'start_x' => 'sometimes|numeric',
            'start_y' => 'sometimes|numeric',
            'stagger' => 'sometimes|boolean',
        ]);

        $elements = $this->layoutGenerator->generateGrid(
            $validated['rows'],
            $validated['cols'],
            $validated['spacing'],
            [
                'startAt' => [
                    'x' => $validated['start_x'] ?? 0,
                    'y' => $validated['start_y'] ?? 0,
                ],
                'elementType' => $validated['element_type'] ?? 'seat',
                'stagger' => $validated['stagger'] ?? false,
                'width' => $validated['spacing'] * 0.8,
                'height' => $validated['spacing'] * 0.8,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'elements' => $elements,
                'count' => count($elements),
            ],
        ]);
    }

    /**
     * Generate a curved layout
     */
    public function generateCurve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'radius' => 'required|numeric|min:10|max:1000',
            'start_angle' => 'required|numeric|min:0|max:360',
            'end_angle' => 'required|numeric|min:0|max:360|gt:start_angle',
            'count' => 'required|integer|min:1|max:500',
            'element_type' => 'sometimes|in:seat,table,chair',
            'center_x' => 'sometimes|numeric',
            'center_y' => 'sometimes|numeric',
            'rotate_to_center' => 'sometimes|boolean',
        ]);

        $elements = $this->layoutGenerator->generateCurve(
            $validated['radius'],
            $validated['start_angle'],
            $validated['end_angle'],
            $validated['count'],
            [
                'center' => [
                    'x' => $validated['center_x'] ?? 0,
                    'y' => $validated['center_y'] ?? 0,
                ],
                'elementType' => $validated['element_type'] ?? 'seat',
                'rotateToFaceCenter' => $validated['rotate_to_center'] ?? false,
                'width' => $validated['radius'] * 0.1,
                'height' => $validated['radius'] * 0.1,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'elements' => $elements,
                'count' => count($elements),
            ],
        ]);
    }

    /**
     * Generate from custom path
     */
    public function generateFromPath(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'required|array|min:2',
            'path.*.x' => 'required|numeric',
            'path.*.y' => 'required|numeric',
            'count' => 'required|integer|min:1|max:500',
            'element_type' => 'sometimes|in:seat,table,chair',
            'perpendicular_offset' => 'sometimes|numeric',
            'align_to_path' => 'sometimes|boolean',
        ]);

        $elements = $this->layoutGenerator->generateFromPath(
            $validated['path'],
            $validated['count'],
            [
                'elementType' => $validated['element_type'] ?? 'seat',
                'perpendicularOffset' => $validated['perpendicular_offset'] ?? 0,
                'alignToPath' => $validated['align_to_path'] ?? false,
                'width' => 30,
                'height' => 30,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'elements' => $elements,
                'count' => count($elements),
            ],
        ]);
    }

    /**
     * Apply transformation to elements
     */
    public function applyTransform(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'elements' => 'required|array',
            'translate_x' => 'sometimes|numeric',
            'translate_y' => 'sometimes|numeric',
            'translate_z' => 'sometimes|numeric',
            'rotate_x' => 'sometimes|numeric',
            'rotate_y' => 'sometimes|numeric',
            'rotate_z' => 'sometimes|numeric',
            'scale_x' => 'sometimes|numeric|min:0.1',
            'scale_y' => 'sometimes|numeric|min:0.1',
            'scale_z' => 'sometimes|numeric|min:0.1',
        ]);

        $transform = new Transform(
            $validated['translate_x'] ?? 0,
            $validated['translate_y'] ?? 0,
            $validated['translate_z'] ?? 0,
            $validated['rotate_x'] ?? 0,
            $validated['rotate_y'] ?? 0,
            $validated['rotate_z'] ?? 0,
            $validated['scale_x'] ?? 1,
            $validated['scale_y'] ?? 1,
            $validated['scale_z'] ?? 1
        );

        $transformed = $this->layoutGenerator->applyTransform($validated['elements'], $transform);

        return response()->json([
            'success' => true,
            'data' => [
                'elements' => $transformed,
            ],
        ]);
    }

    /**
     * Render seat map as SVG
     */
    public function renderSVG(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'elements' => 'required|array',
            'width' => 'sometimes|integer|min:100|max:5000',
            'height' => 'sometimes|integer|min:100|max:5000',
            'background_color' => 'sometimes|string|regex:/^#[A-Fa-f0-9]{6}$/',
            'show_labels' => 'sometimes|boolean',
            'interactive' => 'sometimes|boolean',
        ]);

        $svg = $this->svgRenderer->renderSeatMap($validated['elements'], [
            'width' => $validated['width'] ?? 800,
            'height' => $validated['height'] ?? 600,
            'backgroundColor' => $validated['background_color'] ?? '#ffffff',
            'showLabels' => $validated['show_labels'] ?? true,
            'interactive' => $validated['interactive'] ?? false,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'svg' => $svg,
            ],
        ]);
    }

    /**
     * Check spatial conflicts
     */
    public function checkConflicts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'new_element' => 'required|array',
            'existing_elements' => 'required|array',
            'buffer_zones' => 'sometimes|numeric|min:0',
        ]);

        $result = SpatialValidator::checkSpatialConflict(
            $validated['new_element'],
            $validated['existing_elements'],
            ['buffer_zones' => $validated['buffer_zones'] ?? 0]
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Validate clearance zones
     */
    public function validateClearance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'element' => 'required|array',
            'all_elements' => 'required|array',
            'entrance_buffer' => 'sometimes|numeric|min:0',
            'aisle_min_width' => 'sometimes|numeric|min:0',
        ]);

        $violations = SpatialValidator::validateClearanceZones(
            $validated['element'],
            $validated['all_elements'],
            [
                'entrance_buffer' => $validated['entrance_buffer'] ?? 50,
                'aisle_min_width' => $validated['aisle_min_width'] ?? 120,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'violations' => $violations,
                'valid' => empty($violations),
            ],
        ]);
    }

    /**
     * Validate accessibility proximity
     */
    public function validateAccessibility(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'element' => 'required|array',
            'all_elements' => 'required|array',
            'scale_factor' => 'required|numeric|min:0.01',
            'toilet_max_distance' => 'sometimes|numeric|min:0',
            'muster_max_distance' => 'sometimes|numeric|min:0',
        ]);

        $violations = SpatialValidator::validateAccessibilityProximity(
            $validated['element'],
            $validated['all_elements'],
            $validated['scale_factor'],
            [
                'toilet_max_distance_m' => $validated['toilet_max_distance'] ?? 30,
                'muster_max_distance_m' => $validated['muster_max_distance'] ?? 50,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => [
                'violations' => $violations,
                'valid' => empty($violations),
            ],
        ]);
    }

    /**
     * Create a new seat map template
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'width' => 'sometimes|integer|min:100|max:5000',
            'height' => 'sometimes|integer|min:100|max:5000',
            'elements' => 'sometimes|array',
            'metadata' => 'sometimes|array',
        ]);

        $seatMap = new SeatMapDTO([
            'id' => uniqid('sm_'),
            'name' => $validated['name'],
            'width' => $validated['width'] ?? 800,
            'height' => $validated['height'] ?? 600,
            'elements' => $validated['elements'] ?? [],
            'metadata' => $validated['metadata'] ?? [],
            'version' => '1.0',
        ]);

        $success = $this->repository->save($seatMap);

        if (!$success) {
            throw ValidationException::withMessages(['error' => 'Failed to create seat map']);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $seatMap->getId(),
                'name' => $seatMap->getName(),
            ],
        ], 201);
    }

    /**
     * Get a seat map template
     */
    public function show(string $id): JsonResponse
    {
        $seatMap = $this->repository->findById($id);

        if (!$seatMap) {
            return response()->json([
                'success' => false,
                'message' => 'Seat map not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $seatMap->toArray(),
        ]);
    }

    /**
     * Update a seat map template
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $seatMap = $this->repository->findById($id);

        if (!$seatMap) {
            return response()->json([
                'success' => false,
                'message' => 'Seat map not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'width' => 'sometimes|integer|min:100|max:5000',
            'height' => 'sometimes|integer|min:100|max:5000',
            'elements' => 'sometimes|array',
            'metadata' => 'sometimes|array',
        ]);

        $updatedSeatMap = new SeatMapDTO([
            'id' => $seatMap->getId(),
            'name' => $validated['name'] ?? $seatMap->getName(),
            'width' => $validated['width'] ?? $seatMap->getWidth(),
            'height' => $validated['height'] ?? $seatMap->getHeight(),
            'elements' => $validated['elements'] ?? $seatMap->getElements(),
            'metadata' => $validated['metadata'] ?? $seatMap->getMetadata(),
            'zones' => $seatMap->getZones(),
            'pricing_tiers' => $seatMap->getPricingTiers(),
            'version' => $seatMap->getVersion(),
        ]);

        $success = $this->repository->save($updatedSeatMap);

        if (!$success) {
            throw ValidationException::withMessages(['error' => 'Failed to update seat map']);
        }

        return response()->json([
            'success' => true,
            'data' => $updatedSeatMap->toArray(),
        ]);
    }

    /**
     * Delete a seat map template
     */
    public function delete(string $id): JsonResponse
    {
        $success = $this->repository->delete($id);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete seat map',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Seat map deleted successfully',
        ]);
    }

    /**
     * List all seat map templates
     */
    public function index(): JsonResponse
    {
        $templates = $this->repository->getAll();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }
}