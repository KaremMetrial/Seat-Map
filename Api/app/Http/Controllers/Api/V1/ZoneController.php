<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TemplateZone;
use App\Models\VenueTemplate;
use App\Http\Requests\StoreZoneRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    /**
     * GET /api/v1/templates/{template}/zones
     * List all zones in a template.
     */
    public function index(VenueTemplate $template): JsonResponse
    {
        $zones = $template->zones()
            ->withCount('elements')
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $zones,
        ]);
    }

    /**
     * POST /api/v1/templates/{template}/zones
     * Create a new zone.
     */
    public function store(StoreZoneRequest $request, VenueTemplate $template): JsonResponse
    {
        $validated = $request->validated();
        $validated['template_id'] = $template->id;
        $validated['priority'] = $validated['priority'] ?? $template->zones()->max('priority') + 1;

        $zone = TemplateZone::create($validated);

        return response()->json([
            'success' => true,
            'data' => $zone,
        ], 201);
    }

    /**
     * GET /api/v1/zones/{zone}
     * Get zone details with elements.
     */
    public function show(TemplateZone $zone): JsonResponse
    {
        $zone->load(['template', 'elements']);

        return response()->json([
            'success' => true,
            'data' => $zone,
        ]);
    }

    /**
     * PUT /api/v1/zones/{zone}
     * Update a zone.
     */
    public function update(StoreZoneRequest $request, TemplateZone $zone): JsonResponse
    {
        $validated = $request->validated();
        $zone->update($validated);

        return response()->json([
            'success' => true,
            'data' => $zone->fresh(),
        ]);
    }

    /**
     * DELETE /api/v1/zones/{zone}
     * Delete a zone.
     */
    public function destroy(TemplateZone $zone): JsonResponse
    {
        // Remove element associations
        $zone->elements()->detach();

        $zone->delete();

        return response()->json([
            'success' => true,
            'message' => 'Zone deleted successfully',
        ]);
    }

    /**
     * POST /api/v1/zones/{zone}/assign-elements
     * Assign elements to a zone.
     */
    public function assignElements(Request $request, TemplateZone $zone): JsonResponse
    {
        $validated = $request->validate([
            'element_ids' => 'required|array',
            'element_ids.*' => 'exists:template_elements,id',
            'price_modifier' => 'nullable|numeric',
            'modifier_type' => 'nullable|string|in:fixed,percent',
        ]);

        $priceModifier = $validated['price_modifier'] ?? 0;
        $modifierType = $validated['modifier_type'] ?? 'fixed';

        // Sync elements with pivot data
        $zone->elements()->syncWithoutDetaching(
            collect($validated['element_ids'])
                ->mapWithKeys(fn($id) => [$id => [
                    'price_modifier' => $priceModifier,
                    'modifier_type' => $modifierType,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]])
                ->toArray()
        );

        return response()->json([
            'success' => true,
            'message' => count($validated['element_ids']) . ' elements assigned to zone',
            'data' => $zone->load('elements'),
        ]);
    }

    /**
     * POST /api/v1/zones/{zone}/remove-elements
     * Remove elements from a zone.
     */
    public function removeElements(Request $request, TemplateZone $zone): JsonResponse
    {
        $validated = $request->validate([
            'element_ids' => 'required|array',
            'element_ids.*' => 'exists:template_elements,id',
        ]);

        $zone->elements()->detach($validated['element_ids']);

        return response()->json([
            'success' => true,
            'message' => count($validated['element_ids']) . ' elements removed from zone',
        ]);
    }

    /**
     * GET /api/v1/zones/{zone}/elements
     * Get all elements in a zone.
     */
    public function elements(TemplateZone $zone): JsonResponse
    {
        $elements = $zone->elements()
            ->orderBy('z_index')
            ->get()
            ->map(fn($el) => [
                'id' => $el->id,
                'element_type' => $el->element_type,
                'x' => $el->x,
                'y' => $el->y,
                'width' => $el->width,
                'height' => $el->height,
                'data' => $el->data_json,
                'style' => $el->style_json,
                'pivot' => [
                    'price_modifier' => $el->pivot->price_modifier,
                    'modifier_type' => $el->pivot->modifier_type,
                ],
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'zone' => $zone,
                'elements' => $elements,
                'count' => $elements->count(),
            ],
        ]);
    }

    /**
     * POST /api/v1/templates/{template}/zones/create-defaults
     * Create default zones (VIP, Standard, Economy).
     */
    public function createDefaults(VenueTemplate $template): JsonResponse
    {
        $defaults = [
            [
                'name' => 'VIP',
                'code' => 'VIP',
                'description' => 'Premium seats with best view',
                'color' => '#ffd700',
                'priority' => 1,
                'base_price' => 50.00,
            ],
            [
                'name' => 'Standard',
                'code' => 'STD',
                'description' => 'Regular seating area',
                'color' => '#3b82f6',
                'priority' => 2,
                'base_price' => 0.00,
            ],
            [
                'name' => 'Economy',
                'code' => 'ECO',
                'description' => 'Budget-friendly seats',
                'color' => '#10b981',
                'priority' => 3,
                'base_price' => -10.00,
            ],
        ];

        $created = [];
        foreach ($defaults as $data) {
            $zone = $template->zones()->create(array_merge($data, [
                'is_active' => true,
            ]));
            $created[] = $zone;
        }

        return response()->json([
            'success' => true,
            'message' => count($created) . ' default zones created',
            'data' => $created,
        ], 201);
    }
}
