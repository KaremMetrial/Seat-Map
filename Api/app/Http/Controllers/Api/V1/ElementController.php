<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TemplateElement;
use App\Models\VenueTemplate;
use App\Http\Requests\StoreElementRequest;
use App\Http\Requests\BulkStoreElementsRequest;
use App\Http\Requests\GenerateSeatsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ElementController extends Controller
{
    /**
     * GET /api/v1/templates/{template}/elements
     * List all elements in a template.
     */
    public function index(VenueTemplate $template): JsonResponse
    {
        $elements = $template->elements()
            ->orderBy('z_index')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'elements' => $elements,
                'elements_tree' => $template->getElementsTree(),
            ],
        ]);
    }

    /**
     * POST /api/v1/templates/{template}/elements
     * Create a single element.
     */
    public function store(StoreElementRequest $request, VenueTemplate $template): JsonResponse
    {
        $validated = $request->validated();
        $validated['template_id'] = $template->id;
        $validated['z_index'] = $validated['z_index'] ?? $template->elements()->max('z_index') + 1;

        $element = TemplateElement::create($validated);

        return response()->json([
            'success' => true,
            'data' => $element,
        ], 201);
    }

    /**
     * POST /api/v1/templates/{template}/elements/bulk
     * Create multiple elements at once (for batch seat creation).
     */
    public function bulkStore(BulkStoreElementsRequest $request, VenueTemplate $template): JsonResponse
    {
        $validated = $request->validated();

        $maxZ = $template->elements()->max('z_index') ?? 0;
        $now = now()->toDateTimeString();
        $elements = [];

        foreach ($validated['elements'] as $el) {
            $elements[] = [
                'template_id' => $template->id,
                'element_type' => $el['element_type'],
                'x' => $el['x'],
                'y' => $el['y'],
                'width' => $el['width'],
                'height' => $el['height'],
                'rotation' => $el['rotation'] ?? 0,
                'z_index' => $el['z_index'] ?? ++$maxZ,
                'parent_id' => $el['parent_id'] ?? null,
                'data_json' => isset($el['data_json']) ? json_encode($el['data_json']) : null,
                'style_json' => isset($el['style_json']) ? json_encode($el['style_json']) : null,
                'is_active' => $el['is_active'] ?? true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Bulk insert
        TemplateElement::insert($elements);

        return response()->json([
            'success' => true,
            'message' => count($elements) . ' elements created successfully',
            'data' => [
                'count' => count($elements),
            ],
        ], 201);
    }

    /**
     * POST /api/v1/templates/{template}/elements/generate-seats
     * Generate seats in a grid pattern.
     */
    public function generateSeats(GenerateSeatsRequest $request, VenueTemplate $template): JsonResponse
    {
        $validated = $request->validated();

        $startX = $validated['start_x'];
        $startY = $validated['start_y'];
        $rows = $validated['rows'];
        $seatsPerRow = $validated['seats_per_row'];
        $seatWidth = $validated['seat_width'];
        $seatHeight = $validated['seat_height'];
        $gapX = $validated['gap_x'];
        $gapY = $validated['gap_y'];
        $rowLabelStart = $validated['row_label_start'];
        $zoneId = $validated['zone_id'] ?? null;
        $style = $validated['style'] ?? [
            'fill' => '#10b981',
            'stroke' => '#ffffff',
            'strokeWidth' => 1,
        ];
        $seatType = $validated['seat_type'];

        $now = now()->toDateTimeString();
        $maxZ = $template->elements()->max('z_index') ?? 0;
        $elements = [];

        for ($row = 0; $row < $rows; $row++) {
            $rowLabel = chr(ord($rowLabelStart) + $row);

            for ($seat = 0; $seat < $seatsPerRow; $seat++) {
                $seatNumber = $seat + 1;

                $elements[] = [
                    'template_id' => $template->id,
                    'element_type' => 'seat',
                    'x' => $startX + $seat * ($seatWidth + $gapX),
                    'y' => $startY + $row * ($seatHeight + $gapY),
                    'width' => $seatWidth,
                    'height' => $seatHeight,
                    'rotation' => 0,
                    'z_index' => ++$maxZ,
                    'data_json' => json_encode([
                        'label' => "{$rowLabel}-{$seatNumber}",
                        'row' => $rowLabel,
                        'seat_number' => $seatNumber,
                        'seat_type' => $seatType,
                    ]),
                    'style_json' => json_encode($style),
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        // Bulk insert
        TemplateElement::insert($elements);

        // Link to zone if provided
        if ($zoneId) {
            $elementIds = $template->elements()
                ->latest()
                ->limit(count($elements))
                ->pluck('id');

            $zoneMap = $elementIds->map(fn($id) => [
                'template_element_id' => $id,
                'template_zone_id' => $zoneId,
                'created_at' => $now,
                'updated_at' => $now,
            ])->toArray();

            DB::table('element_zone_map')->insert($zoneMap);
        }

        return response()->json([
            'success' => true,
            'message' => count($elements) . ' seats generated successfully',
            'data' => [
                'count' => count($elements),
                'rows' => $rows,
                'seats_per_row' => $seatsPerRow,
            ],
        ], 201);
    }

    /**
     * GET /api/v1/elements/{element}
     * Get element details.
     */
    public function show(TemplateElement $element): JsonResponse
    {
        $element->load(['template', 'parent', 'children', 'zones']);

        return response()->json([
            'success' => true,
            'data' => $element,
        ]);
    }

    /**
     * PUT /api/v1/elements/{element}
     * Update an element.
     */
    public function update(StoreElementRequest $request, TemplateElement $element): JsonResponse
    {
        $validated = $request->validated();
        $element->update($validated);

        return response()->json([
            'success' => true,
            'data' => $element->fresh(),
        ]);
    }

    /**
     * PUT /api/v1/elements/bulk-update
     * Update multiple elements (position, style, etc.).
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'element_ids' => 'required|array|min:1|max:500',
            'element_ids.*' => 'required|exists:template_elements,id',
            'updates' => 'required|array',
            'updates.x' => 'nullable|numeric',
            'updates.y' => 'nullable|numeric',
            'updates.style_json' => 'nullable|array',
            'updates.is_active' => 'nullable|boolean',
        ]);

        $updates = [];
        if (isset($validated['updates']['x'])) $updates['x'] = $validated['updates']['x'];
        if (isset($validated['updates']['y'])) $updates['y'] = $validated['updates']['y'];
        if (isset($validated['updates']['style_json'])) $updates['style_json'] = json_encode($validated['updates']['style_json']);
        if (isset($validated['updates']['is_active'])) $updates['is_active'] = $validated['updates']['is_active'];

        if (empty($updates)) {
            return response()->json([
                'success' => false,
                'message' => 'No valid updates provided',
            ], 422);
        }

        $count = TemplateElement::whereIn('id', $validated['element_ids'])->update($updates);

        return response()->json([
            'success' => true,
            'message' => "{$count} elements updated",
        ]);
    }

    /**
     * DELETE /api/v1/elements/{element}
     * Delete an element.
     */
    public function destroy(TemplateElement $element): JsonResponse
    {
        $element->delete();

        return response()->json([
            'success' => true,
            'message' => 'Element deleted successfully',
        ]);
    }

    /**
     * POST /api/v1/elements/bulk-delete
     * Delete multiple elements.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'element_ids' => 'required|array|min:1|max:500',
            'element_ids.*' => 'required|exists:template_elements,id',
        ]);

        $count = TemplateElement::whereIn('id', $validated['element_ids'])->delete();

        return response()->json([
            'success' => true,
            'message' => "{$count} elements deleted",
        ]);
    }
}
