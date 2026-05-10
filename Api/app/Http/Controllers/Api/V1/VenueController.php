<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Models\VenueTemplate;
use App\Models\TemplateElement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VenueController extends Controller
{
    /**
     * GET /api/v1/venues
     * List all venues.
     */
    public function index(): JsonResponse
    {
        $venues = Venue::with('templates')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $venues,
        ]);
    }

    /**
     * POST /api/v1/venues
     * Create a new venue.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'venue_type' => 'nullable|in:cinema,stadium,theater,custom',
            'default_width' => 'nullable|integer|min:100|max:10000',
            'default_height' => 'nullable|integer|min:100|max:10000',
            'metadata' => 'nullable|array',
        ]);

        $venue = Venue::create($validated);

        return response()->json([
            'success' => true,
            'data' => $venue,
        ], 201);
    }

    /**
     * GET /api/v1/venues/{venue}
     * Get venue details with templates.
     */
    public function show(Venue $venue): JsonResponse
    {
        $venue->load('templates');

        return response()->json([
            'success' => true,
            'data' => $venue,
        ]);
    }

    /**
     * PUT /api/v1/venues/{venue}
     * Update a venue.
     */
    public function update(Request $request, Venue $venue): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'venue_type' => 'nullable|in:cinema,stadium,theater,custom',
            'is_active' => 'nullable|boolean',
        ]);

        $venue->update($validated);

        return response()->json([
            'success' => true,
            'data' => $venue,
        ]);
    }

    /**
     * DELETE /api/v1/venues/{venue}
     * Soft delete a venue.
     */
    public function destroy(Venue $venue): JsonResponse
    {
        $venue->delete();

        return response()->json([
            'success' => true,
            'message' => 'Venue deleted successfully',
        ]);
    }
}
