<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreZoneRequest;
use App\Models\TemplateZone;
use App\Models\VenueTemplate;
use App\Services\TemplateCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    public function __construct(
        private TemplateCacheService $templateCache,
    ) {}

    /**
     * GET /api/v1/templates/{template}/zones
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
            'data'    => $zones,
        ]);
    }

    /**
     * POST /api/v1/templates/{template}/zones
     *
     * Creates a zone and invalidates cache for affected events.
     */
    public function store(StoreZoneRequest $request, VenueTemplate $template): JsonResponse
    {
        $validated = $request->validated();
        $validated['template_id'] = $template->id;
        $validated['priority'] = $validated['priority'] ?? $template->zones()->max('priority') + 1;

        $zone = TemplateZone::create($validated);

        // Invalidate cache for published events using this template
        $invalidated = $this->templateCache->invalidateTemplateCache($template->id);

        return response()->json([
            'success' => true,
            'data'    => $zone,
            'meta'    => [
                'cache_invalidated'          => $invalidated > 0,
                'affected_published_events'  => $invalidated,
            ],
        ], 201);
    }

    /**
     * GET /api/v1/zones/{zone}
     */
    public function show(TemplateZone $zone): JsonResponse
    {
        $zone->load(['template', 'elements']);

        return response()->json([
            'success' => true,
            'data'    => $zone,
        ]);
    }

    /**
     * PUT /api/v1/zones/{zone}
     *
     * Updates a zone and invalidates cache for affected events.
     */
    public function update(StoreZoneRequest $request, TemplateZone $zone): JsonResponse
    {
        $validated = $request->validated();
        $zone->update($validated);

        // Invalidate cache for published events using this template
        $invalidated = $this->templateCache->invalidateTemplateCache($zone->template_id);

        return response()->json([
            'success' => true,
            'data'    => $zone->fresh(),
            'meta'    => [
                'cache_invalidated'          => $invalidated > 0,
                'affected_published_events'  => $invalidated,
            ],
        ]);
    }

    /**
     * DELETE /api/v1/zones/{zone}
     *
     * Deletes a zone and invalidates cache for affected events.
     */
    public function destroy(TemplateZone $zone): JsonResponse
    {
        $templateId = $zone->template_id;
        $zone->elements()->detach();
        $zone->delete();

        // Invalidate cache for published events using this template
        $invalidated = $this->templateCache->invalidateTemplateCache($templateId);

        return response()->json([
            'success' => true,
            'message' => 'Zone deleted successfully',
            'meta'    => [
                'cache_invalidated'          => $invalidated > 0,
                'affected_published_events'  => $invalidated,
            ],
        ]);
    }

    /**
     * POST /api/v1/zones/{zone}/assign-elements
     *
     * Assigns elements to a zone and invalidates cache.
     */
    public function assignElements(Request $request, TemplateZone $zone): JsonResponse
    {
        $validated = $request->validate([
            'element_ids'      => 'required|array',
            'element_ids.*'    => 'exists:template_elements,id',
            'price_modifier'   => 'nullable|numeric',
            'modifier_type'    => 'nullable|string|in:fixed,percent',
        ]);

        $priceModifier = $validated['price_modifier'] ?? 0;
        $modifierType = $validated['modifier_type'] ?? 'fixed';

        $zone->elements()->syncWithoutDetaching(
            collect($validated['element_ids'])
                ->mapWithKeys(fn ($id) => [$id => [
                    'price_modifier' => $priceModifier,
                    'modifier_type'  => $modifierType,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]])
                ->toArray()
        );

        // Invalidate cache for published events using this template
        $invalidated = $this->templateCache->invalidateTemplateCache($zone->template_id);

        return response()->json([
            'success' => true,
            'message' => count($validated['element_ids']) . ' elements assigned to zone',
            'data'    => $zone->load('elements'),
            'meta'    => [
                'cache_invalidated'          => $invalidated > 0,
                'affected_published_events'  => $invalidated,
            ],
        ]);
    }

    /**
     * POST /api/v1/zones/{zone}/remove-elements
     *
     * Removes elements from a zone and invalidates cache.
     */
    public function removeElements(Request $request, TemplateZone $zone): JsonResponse
    {
        $validated = $request->validate([
            'element_ids'   => 'required|array',
            'element_ids.*' => 'exists:template_elements,id',
        ]);

        $zone->elements()->detach($validated['element_ids']);

        // Invalidate cache for published events using this template
        $invalidated = $this->templateCache->invalidateTemplateCache($zone->template_id);

        return response()->json([
            'success' => true,
            'message' => count($validated['element_ids']) . ' elements removed from zone',
            'meta'    => [
                'cache_invalidated'          => $invalidated > 0,
                'affected_published_events'  => $invalidated,
            ],
        ]);
    }

    /**
     * GET /api/v1/zones/{zone}/elements
     */
    public function elements(TemplateZone $zone): JsonResponse
    {
        $elements = $zone->elements()
            ->orderBy('z_index')
            ->get()
            ->map(fn ($el) => [
                'id'           => $el->id,
                'element_type' => $el->element_type,
                'x'            => $el->x,
                'y'            => $el->y,
                'width'        => $el->width,
                'height'       => $el->height,
                'data'         => $el->data_json,
                'style'        => $el->style_json,
                'pivot'        => [
                    'price_modifier' => $el->pivot->price_modifier,
                    'modifier_type'  => $el->pivot->modifier_type,
                ],
            ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'zone'     => $zone,
                'elements' => $elements,
                'count'    => $elements->count(),
            ],
        ]);
    }

    /**
     * POST /api/v1/templates/{template}/zones/create-defaults
     *
     * Creates default zones and invalidates cache.
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

        // Invalidate cache for published events using this template
        $invalidated = $this->templateCache->invalidateTemplateCache($template->id);

        return response()->json([
            'success' => true,
            'message' => count($created) . ' default zones created',
            'data'    => $created,
            'meta'    => [
                'cache_invalidated'          => $invalidated > 0,
                'affected_published_events'  => $invalidated,
            ],
        ], 201);
    }
}