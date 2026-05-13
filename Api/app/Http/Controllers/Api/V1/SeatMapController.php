<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EventElementResource;
use App\Models\Event;
use App\Models\EventElement;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
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
     */
    public function show(Event $event, Request $request): JsonResponse
    {
        Log::info('SeatMapController:show', [
            'event_id' => $event->id,
            'user_id' => $request->user()?->id,
            'viewport' => $request->only(['x', 'y', 'width', 'height']),
        ]);

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
            'data' => [
                'event' => [
                    'id' => $event->id,
                    'title' => $event->title,
                    'start_at' => $event->start_at,
                    'canvas' => [
                        'width' => $event->template->canvas_width,
                        'height' => $event->template->canvas_height,
                        'background_image' => $event->template->background_image,
                        'background_color' => $event->template->background_color,
                    ],
                ],
                'elements' => EventElementResource::collection($elements),
                'zones' => $event->template->zones,
            ],
        ]);
    }

    /**
     * GET /api/v1/events/{event}/available
     *
     * Returns only bookable, available elements.
     * Uses subquery exclusions at DB level — no in-memory filtering.
     */
    public function available(Event $event): JsonResponse
    {
        Log::info('SeatMapController:available', [
            'event_id' => $event->id,
        ]);

        $event->loadMissing('template');

        // Filter at DB level — avoids loading all elements into memory first
        $elements = $event->eventElements()
            ->where('is_bookable', true)
            ->whereNotIn('id', fn ($q) => $q
                ->select('event_element_id')
                ->from('booking_items')
                ->where('status', 'booked')
            )
            ->whereNotIn('id', fn ($q) => $q
                ->select('event_element_id')
                ->from('element_locks')
                ->where('expires_at', '>', Carbon::now())
            )
            ->orderBy('z_index')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_available' => $elements->count(),
                'elements' => EventElementResource::collection($elements),
            ],
        ]);
    }
}