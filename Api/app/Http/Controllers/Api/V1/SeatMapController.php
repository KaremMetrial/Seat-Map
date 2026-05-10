<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventElement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SeatMapController extends Controller
{
    /**
     * GET /api/v1/events/{event}/seatmap
     *
     * Returns the full seat map for an event.
     * Accepts optional viewport parameters (x, y, width, height) to limit
     * the elements returned — useful for large stadiums where the client
     * only renders the visible area.
     *
     * Status is resolved via EventElement::hydrateBookingStatus() — exactly
     * 2 bulk queries regardless of how many elements are returned.
     */
    public function show(Event $event, Request $request): JsonResponse
    {
        // Validate viewport parameters if provided
        if ($request->filled(['x', 'y', 'width', 'height'])) {
            $validator = Validator::make($request->all(), [
                'x' => 'required|numeric|min:0',
                'y' => 'required|numeric|min:0',
                'width' => 'required|numeric|min:1|max:10000',
                'height' => 'required|numeric|min:1|max:10000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid viewport parameters',
                    'errors' => $validator->errors(),
                ], 422);
            }
        }

        // Eager-load template so canvas dimensions cost 0 extra queries
        $event->loadMissing('template.zones');

        $query = $event->eventElements()->orderBy('z_index');

        // Optional viewport culling — only apply when all four params are present
        if ($request->filled(['x', 'y', 'width', 'height'])) {
            $vx = (float) $request->input('x');
            $vy = (float) $request->input('y');
            $vw = (float) $request->input('width');
            $vh = (float) $request->input('height');

            $query
                ->where('x', '>=', $vx)
                ->where('x', '<=', $vx + $vw)
                ->where('y', '>=', $vy)
                ->where('y', '<=', $vy + $vh);
        }

        $elements = $query->get();

        // Resolve booking_status for the whole collection in 2 queries (not 2N)
        EventElement::hydrateBookingStatus($elements);

        return response()->json([
            'success' => true,
            'data'    => [
                'event' => [
                    'id'       => $event->id,
                    'title'    => $event->title,
                    'start_at' => $event->start_at,
                    'canvas'   => [
                        'width'            => $event->template->canvas_width,
                        'height'           => $event->template->canvas_height,
                        'background_image' => $event->template->background_image,
                        'background_color' => $event->template->background_color,
                    ],
                ],
                'elements' => $elements->map(fn (EventElement $el) => [
                    'id'          => $el->id,
                    'type'        => $el->element_type,
                    'x'           => (float) $el->x,
                    'y'           => (float) $el->y,
                    'width'       => (float) $el->width,
                    'height'      => (float) $el->height,
                    'rotation'    => (float) $el->rotation,
                    'z_index'     => $el->z_index,
                    'parent_id'   => $el->parent_id,
                    'data'        => $el->data_json,
                    'style'       => $el->style_json,
                    'is_bookable' => $el->is_bookable,
                    'zone_id'     => $el->zone_id,
                    'status'      => $el->booking_status,
                ]),
                'zones' => $event->template->zones,
            ],
        ]);
    }

    /**
     * GET /api/v1/events/{event}/available
     *
     * Returns only bookable, available elements.
     * Uses subquery exclusions — zero per-element queries.
     */
    public function available(Event $event): JsonResponse
    {
        $event->loadMissing('template');

        // Use the scope so status is resolved in the SELECT — 0 extra queries
        $elements = $event->eventElements()
            ->withBookingStatus()
            ->where('is_bookable', true)
            ->orderBy('z_index')
            ->get()
            ->filter(fn (EventElement $el) => $el->booking_status === 'available')
            ->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'total_available' => $elements->count(),
                'elements'        => $elements->map(fn (EventElement $el) => [
                    'id'     => $el->id,
                    'type'   => $el->element_type,
                    'x'      => (float) $el->x,
                    'y'      => (float) $el->y,
                    'width'  => (float) $el->width,
                    'height' => (float) $el->height,
                    'data'   => $el->data_json,
                    'status' => 'available',
                ]),
            ],
        ]);
    }
}
