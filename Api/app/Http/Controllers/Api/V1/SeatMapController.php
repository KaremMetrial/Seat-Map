<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EventElementResource;
use App\Models\Event;
use App\Models\EventElement;
use App\Services\SeatCacheService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SeatMapController extends Controller
{
    public function __construct(
        private ?SeatCacheService $cacheService = null,
    ) {}

    /**
     * GET /api/v1/events/{event}/seatmap
     *
     * Returns seat map for an event. Supports two modes:
     *
     * 1. Viewport mode (recommended for 100K+ seats):
     *    Pass x, y, width, height to get only visible seats.
     *    Example: /seatmap?x=100&y=200&width=400&height=300
     *    Returns ~50-200 seats instead of 100K.
     *
     * 2. Full mode (default):
     *    Returns all elements. Use only for small events (< 5K seats).
     *
     * Seat status is resolved from Redis cache when available,
     * falling back to database computation.
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
                'x'      => 'required|numeric|min:0',
                'y'      => 'required|numeric|min:0',
                'width'  => 'required|numeric|min:1|max:10000',
                'height' => 'required|numeric|min:1|max:10000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid viewport parameters',
                    'errors'  => $validator->errors(),
                ], 422);
            }
        }

        $event->loadMissing('template.zones');

        $query = $event->eventElements()->orderBy('z_index');

        // Viewport culling — critical for 100K seat performance
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

        // Resolve booking status — use Redis cache when available
        $this->hydrateStatusFromCache($event->id, $elements);

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
                'elements' => EventElementResource::collection($elements),
                'zones'    => $event->template->zones,
                'meta'     => [
                    'total_returned'   => $elements->count(),
                    'viewport_applied' => $request->filled(['x', 'y', 'width', 'height']),
                    'cache_used'       => $this->cacheService?->hasCache($event->id) ?? false,
                ],
            ],
        ]);
    }

    /**
     * GET /api/v1/events/{event}/available
     *
     * Returns only bookable, available elements.
     * Uses Redis cache for O(1) status lookups when available.
     * Supports viewport filtering for large events.
     */
    public function available(Event $event, Request $request): JsonResponse
    {
        Log::info('SeatMapController:available', [
            'event_id' => $event->id,
            'viewport' => $request->only(['x', 'y', 'width', 'height']),
        ]);

        $event->loadMissing('template');

        // Use cache + viewport when available (fast path for 100K seats)
        if ($this->cacheService && $this->cacheService->hasCache($event->id) && $request->filled(['x', 'y', 'width', 'height'])) {
            $availableIds = $this->cacheService->getAvailableInViewport(
                $event->id,
                (float) $request->input('x'),
                (float) $request->input('y'),
                (float) $request->input('width'),
                (float) $request->input('height'),
            );

            $elements = EventElement::whereIn('id', $availableIds)
                ->orderBy('z_index')
                ->get();

            // Set status from cache
            foreach ($elements as $element) {
                $element->setAttribute('booking_status', 'available');
            }
        } else {
            // Fallback: DB-level filtering (works without cache)
            $query = $event->eventElements()
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
                ->orderBy('z_index');

            // Apply viewport if provided
            if ($request->filled(['x', 'y', 'width', 'height'])) {
                $query
                    ->where('x', '>=', (float) $request->input('x'))
                    ->where('x', '<=', (float) $request->input('x') + (float) $request->input('width'))
                    ->where('y', '>=', (float) $request->input('y'))
                    ->where('y', '<=', (float) $request->input('y') + (float) $request->input('height'));
            }

            $elements = $query->get();
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'total_available' => $elements->count(),
                'elements'        => EventElementResource::collection($elements),
            ],
        ]);
    }

    // ── Private Helpers ──────────────────────────────────────────────────────

    /**
     * Hydrate booking status for a collection of elements.
     * Uses Redis cache when available, falls back to DB computation.
     */
    private function hydrateStatusFromCache(int $eventId, $elements): void
    {
        if ($elements->isEmpty()) {
            return;
        }

        $elementIds = $elements->pluck('id')->toArray();

        // Fast path: Redis cache
        if ($this->cacheService && $this->cacheService->hasCache($eventId)) {
            $statuses = $this->cacheService->getSeatStatuses($eventId, $elementIds);

            foreach ($elements as $element) {
                $element->setAttribute(
                    'booking_status',
                    $statuses[$element->id] ?? 'available'
                );
            }

            return;
        }

        // Fallback: DB computation (original method)
        EventElement::hydrateBookingStatus($elements);
    }
}