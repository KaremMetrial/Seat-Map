<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\VenueTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    /**
     * GET /api/v1/events
     * List all events.
     */
    public function index(): JsonResponse
    {
        $events = Event::with('template')
            ->orderBy('start_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $events,
        ]);
    }

    /**
     * POST /api/v1/events
     * Create a new event.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => 'required|exists:venue_templates,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_at' => 'required|date|after:now',
            'end_at' => 'required|date|after:start_at',
            'booking_open_at' => 'nullable|date',
            'booking_close_at' => 'nullable|date|after:booking_open_at',
            'base_price' => 'nullable|numeric|min:0',
            'metadata' => 'nullable|array',
        ]);

        $validated['status'] = 'draft';

        $event = Event::create($validated);

        return response()->json([
            'success' => true,
            'data' => $event,
        ], 201);
    }

    /**
     * GET /api/v1/events/{event}
     * Get event details.
     */
    public function show(Event $event): JsonResponse
    {
        $event->load('template.venue');

        return response()->json([
            'success' => true,
            'data' => $event,
        ]);
    }

    /**
     * PUT /api/v1/events/{event}
     * Update an event.
     */
    public function update(Request $request, Event $event): JsonResponse
    {
        if ($event->status === 'published') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update a published event',
            ], 422);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'start_at' => 'sometimes|date',
            'end_at' => 'sometimes|date|after:start_at',
            'booking_open_at' => 'nullable|date',
            'booking_close_at' => 'nullable|date',
            'base_price' => 'nullable|numeric|min:0',
        ]);

        $event->update($validated);

        return response()->json([
            'success' => true,
            'data' => $event,
        ]);
    }

    /**
     * POST /api/v1/events/{event}/publish
     * Publish an event (creates element snapshot).
     */
    public function publish(Event $event): JsonResponse
    {
        if ($event->status === 'published') {
            return response()->json([
                'success' => false,
                'message' => 'Event is already published',
            ], 422);
        }

        if (!$event->template) {
            return response()->json([
                'success' => false,
                'message' => 'Event must have a template assigned',
            ], 422);
        }

        try {
            $event->publish();

            return response()->json([
                'success' => true,
                'message' => 'Event published successfully',
                'data' => $event->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to publish event: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * DELETE /api/v1/events/{event}
     * Soft delete an event.
     */
    public function destroy(Event $event): JsonResponse
    {
        if ($event->status === 'published') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a published event',
            ], 422);
        }

        $event->delete();

        return response()->json([
            'success' => true,
            'message' => 'Event deleted successfully',
        ]);
    }
}
