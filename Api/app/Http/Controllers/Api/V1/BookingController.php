<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\SeatLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(
        private BookingService $bookingService,
        private SeatLockService $lockService,
    ) {}

    /**
     * POST /api/v1/bookings/lock
     * Lock seats temporarily before payment.
     */
    public function lock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id'      => 'required|exists:events,id',
            'element_ids'   => 'required|array|min:1',
            'element_ids.*' => 'integer|exists:event_elements,id',
            'lock_key'      => 'nullable|string|max:255',
        ]);

        $lockKey = $validated['lock_key'] ?? \Illuminate\Support\Str::uuid()->toString();

        $result = $this->lockService->lockElements(
            $validated['element_ids'],
            $lockKey,
        );

        return response()->json($result, $result['success'] ? 200 : 409);
    }

    /**
     * POST /api/v1/bookings
     * Create a booking with locked seats.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id'       => 'required|exists:events,id',
            'element_ids'    => 'required|array|min:1',
            'element_ids.*'  => 'integer|exists:event_elements,id',
            'customer_name'  => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'nullable|string|max:50',
            'lock_key'       => 'nullable|string|max:255',
            'currency'       => 'nullable|string|size:3',
        ]);

        $result = $this->bookingService->createBooking($validated);

        return response()->json($result, $result['success'] ? 201 : 422);
    }

    /**
     * POST /api/v1/bookings/{booking}/confirm
     * Confirm booking after successful payment.
     */
    public function confirm(Request $request, string $bookingReference): JsonResponse
    {
        $validated = $request->validate([
            'payment_intent_id' => 'nullable|string|max:255',
        ]);

        $result = $this->bookingService->confirmBooking(
            $bookingReference,
            $validated['payment_intent_id'] ?? null,
        );

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * DELETE /api/v1/bookings/{booking}
     * Cancel a booking.
     */
    public function destroy(string $bookingReference): JsonResponse
    {
        $result = $this->bookingService->cancelBooking($bookingReference);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * GET /api/v1/bookings/{booking}
     * Get booking details.
     */
    public function show(string $bookingReference): JsonResponse
    {
        $booking = Booking::where('booking_reference', $bookingReference)
            ->with(['event', 'items'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => [
                'booking' => $booking,
                'items'   => $booking->items,
            ],
        ]);
    }
}
