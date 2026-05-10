<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Models\VenueTemplate;
use App\Http\Requests\StoreTemplateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    /**
     * GET /api/v1/venues/{venue}/templates
     * List all templates for a venue.
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
            'data' => $templates,
        ]);
    }

    /**
     * POST /api/v1/venues/{venue}/templates
     * Create a new template.
     */
    public function store(StoreTemplateRequest $request, Venue $venue): JsonResponse
    {
        $validated = $request->validated();
        $validated['venue_id'] = $venue->id;

        // If this is set as default, unset other defaults
        if ($validated['is_default'] ?? false) {
            $venue->templates()->update(['is_default' => false]);
        }

        $template = VenueTemplate::create($validated);

        return response()->json([
            'success' => true,
            'data' => $template,
        ], 201);
    }

    /**
     * GET /api/v1/templates/{template}
     * Get template with elements and zones.
     */
    public function show(VenueTemplate $template): JsonResponse
    {
        $template->load(['elements' => fn($q) => $q->orderBy('z_index'), 'zones']);

        return response()->json([
            'success' => true,
            'data' => [
                'template' => $template,
                'elements_tree' => $template->getElementsTree(),
                'elements_count' => $template->elements()->count(),
                'zones_count' => $template->zones()->count(),
            ],
        ]);
    }

    /**
     * PUT /api/v1/templates/{template}
     * Update a template.
     */
    public function update(StoreTemplateRequest $request, VenueTemplate $template): JsonResponse
    {
        $validated = $request->validated();

        // If this is set as default, unset other defaults
        if ($validated['is_default'] ?? false) {
            $template->venue->templates()
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);
        }

        $template->update($validated);

        return response()->json([
            'success' => true,
            'data' => $template,
        ]);
    }

    /**
     * DELETE /api/v1/templates/{template}
     * Soft delete a template.
     */
    public function destroy(VenueTemplate $template): JsonResponse
    {
        // Check if template is used by any published events
        $usedByEvents = $template->events()
            ->where('status', 'published')
            ->exists();

        if ($usedByEvents) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete template used by published events',
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
     * Duplicate a template with all its elements and zones.
     */
    public function duplicate(VenueTemplate $template): JsonResponse
    {
        $newTemplate = $template->replicate();
        $newTemplate->name = $template->name . ' (Copy)';
        $newTemplate->slug = null; // Will be auto-generated
        $newTemplate->is_default = false;
        $newTemplate->save();

        // Duplicate elements
        foreach ($template->elements as $element) {
            $newElement = $element->replicate();
            $newElement->template_id = $newTemplate->id;
            $newElement->save();
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
            'data' => $newTemplate->load('elements', 'zones'),
        ], 201);
    }
}
