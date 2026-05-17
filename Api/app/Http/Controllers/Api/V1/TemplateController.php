<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTemplateRequest;
use App\Models\TemplateElement;
use App\Models\Venue;
use App\Models\VenueTemplate;
use App\Services\TemplateCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function __construct(
        private TemplateCacheService $templateCache,
    ) {}

    /**
     * GET /api/v1/venues/{venue}/templates
     */
    public function index(Venue $venue): JsonResponse
    {
        $templates = $venue->templates()
            ->withCount('elements')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $templates,
        ]);
    }

    /**
     * POST /api/v1/venues/{venue}/templates
     */
    public function store(StoreTemplateRequest $request, Venue $venue): JsonResponse
    {
        $validated = $request->validated();
        $validated['venue_id'] = $venue->id;

        if ($validated['is_default'] ?? false) {
            $venue->templates()->update(['is_default' => false]);
        }

        $template = VenueTemplate::create($validated);

        return response()->json([
            'success' => true,
            'data'    => $template,
        ], 201);
    }

    /**
     * GET /api/v1/templates/{template}
     */
    public function show(VenueTemplate $template): JsonResponse
    {
        $template->load(['elements' => fn ($q) => $q->orderBy('z_index'), 'zones']);

        return response()->json([
            'success' => true,
            'data'    => [
                'template'       => $template,
                'elements_tree'  => $template->getElementsTree(),
                'elements_count' => $template->elements()->count(),
                'zones_count'    => $template->zones()->count(),
            ],
        ]);
    }

    /**
     * PUT /api/v1/templates/{template}
     *
     * When a template is updated, invalidate cache for all published events
     * that use this template. The cache will be rebuilt in the background
     * via queue jobs.
     */
    public function update(StoreTemplateRequest $request, VenueTemplate $template): JsonResponse
    {
        $validated = $request->validated();

        if ($validated['is_default'] ?? false) {
            $template->venue->templates()
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);
        }

        $template->update($validated);

        // Invalidate cache for all published events using this template
        $invalidated = $this->templateCache->invalidateTemplateCache($template->id);

        return response()->json([
            'success' => true,
            'data'    => $template,
            'meta'    => [
                'cache_invalidated'          => $invalidated > 0,
                'affected_published_events'  => $invalidated,
            ],
        ]);
    }

    /**
     * DELETE /api/v1/templates/{template}
     *
     * Prevents deletion if template is used by published events.
     */
    public function destroy(VenueTemplate $template): JsonResponse
    {
        if ($this->templateCache->templateHasPublishedEvents($template->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete template used by published events. Unpublish or delete those events first.',
            ], 422);
        }

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully',
        ]);
    }

    /**
     * POST /api/v1/templates/{template}/duplicate
     */
    public function duplicate(VenueTemplate $template): JsonResponse
    {
        $newTemplate = $template->replicate();
        $newTemplate->name = $template->name . ' (Copy)';
        $newTemplate->slug = null;
        $newTemplate->is_default = false;
        $newTemplate->save();

        // Duplicate elements using bulk insert
        $elementsData = $template->elements->map(function ($element) use ($newTemplate) {
            $new = $element->replicate();
            $new->template_id = $newTemplate->id;
            return $new->attributesToArray();
        })->toArray();

        if (!empty($elementsData)) {
            TemplateElement::insert($elementsData);
        }

        // Duplicate zones
        $zoneMap = [];
        foreach ($template->zones as $zone) {
            $newZone = $zone->replicate();
            $newZone->template_id = $newTemplate->id;
            $newZone->save();
            $zoneMap[$zone->id] = $newZone->id;
        }

        return response()->json([
            'success' => true,
            'message' => 'Template duplicated successfully',
            'data'    => $newTemplate->load('elements', 'zones'),
        ], 201);
    }
}