<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\UpdateSeatStatus;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\ElementLock;
use App\Models\Event;
use App\Models\EventElement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Booking Service — orchestrates the full booking lifecycle.
 *
 * Performance optimizations for 100K+ seats:
 * - Seat status stored in Redis cache (O(1) lookups)
 * - Cache updates dispatched as queue jobs (non-blocking)
 * - Socket events dispatched as queue jobs (non-blocking)
 * - DB transaction only wraps DB operations, not cache/socket
 *
 * Flow:
 *   1. Validate event is open for booking
 *   2. Verify all requested elements exist and are bookable
 *   3. Lock elements via SeatLockService
 *   4. Calculate pricing
 *   5. Create booking + booking_items in a transaction
 *   6. Link locks to the booking reference
 *   7. Dispatch cache update + socket broadcast (queued)
 *
 * Confirm flow (after payment):
 *   8. Mark booking confirmed, delete locks
 *   9. Dispatch cache update + socket broadcast (queued)
 *
 * Cancel flow:
 *  10. Mark booking cancelled, cancel items, delete locks
 *  11. Dispatch cache update + socket broadcast (queued)
 */
class BookingService
{
    public function __construct(
        private SeatLockService $lockService,
        private PricingService $pricingService,
        private ?SocketService $socketService = null,
        private ?SeatCacheService $cacheService = null,
    ) {}

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Create a new booking with locked seats.
     *
     * @param  array<string, mixed> $data
     * @return array{success: bool, ...}
     */
    public function createBooking(array $data): array
    {
        Log::info('BookingService:createBooking started', [
            'event_id' => $data['event_id'],
            'element_count' => count($data['element_ids'] ?? []),
            'user_id' => $data['user_id'] ?? 'guest',
            'customer_email' => $data['customer_email'] ?? null,
        ]);

        $event = Event::findOrFail($data['event_id']);
        $elementIds = $data['element_ids'];
        $lockKey    = (isset($data['lock_key']) && trim((string) $data['lock_key']) !== '')
                        ? $data['lock_key']
                        : Str::uuid()->toString();

        if (! $event->isBookingOpen()) {
            return ['success' => false, 'message' => 'Booking is not open for this event'];
        }

        // Verify all elements exist (outside transaction - fast check)
        $elements = EventElement::whereIn('id', $elementIds)->get();

        if ($elements->count() !== count($elementIds)) {
            return ['success' => false, 'message' => 'Some elements were not found'];
        }

        // Lock elements first — this is TOCTOU-safe inside transaction
        $lockResult = $this->lockService->lockElements($elementIds, $lockKey);

        if (! $lockResult['success']) {
            return $lockResult;
        }

        // Validate bookable status AFTER acquiring locks (prevents race condition)
        foreach ($elements as $element) {
            if (! $element->is_bookable) {
                $this->lockService->releaseLocks($lockKey);
                return ['success' => false, 'message' => "Element {$element->id} is not bookable"];
            }
        }

        // Calculate pricing
        $pricing = $this->pricingService->calculatePrice($elementIds, $event);

        DB::beginTransaction();

        try {
            $booking = Booking::create([
                'booking_reference' => 'BK-' . strtoupper(Str::random(8)),
                'internal_reference'=> $lockKey,
                'event_id'          => $event->id,
                'user_id'           => $data['user_id'] ?? null,
                'customer_name'     => $data['customer_name'],
                'customer_email'    => $data['customer_email'],
                'customer_phone'    => $data['customer_phone'] ?? null,
                'subtotal'          => $pricing['subtotal'],
                'service_fee'       => $pricing['service_fee'],
                'tax_amount'        => $pricing['tax'],
                'total_amount'      => $pricing['total'],
                'currency'          => $data['currency'] ?? 'USD',
                'status'            => 'locked',
                'locked_at'         => Carbon::now(),
                'expires_at'        => Carbon::now()->addMinutes(10),
                'metadata'          => ['lock_key' => $lockKey],
            ]);

            foreach ($pricing['items'] as $item) {
                BookingItem::create([
                    'booking_id'       => $booking->id,
                    'event_element_id' => $item['element_id'],
                    'event_id'         => $event->id,
                    'element_type'     => $item['element_type'],
                    'label'            => $item['label'],
                    'unit_price'       => $item['unit_price'],
                    'total_price'      => $item['total_price'],
                    'quantity'         => 1,
                    'status'           => 'booked',
                ]);
            }

            // Link locks to this booking reference
            ElementLock::where('lock_key', $lockKey)
                ->update(['booking_reference' => $booking->booking_reference]);

            DB::commit();

            Log::info('BookingService:createBooking success', [
                'booking_reference' => $booking->booking_reference,
                'element_count' => count($elementIds),
            ]);

            // Update Redis cache + dispatch socket event (non-blocking queue)
            $this->dispatchSeatUpdate(
                $event->id,
                array_map(fn ($id) => ['element_id' => $id, 'status' => 'booked'], $elementIds)
            );

            return [
                'success'    => true,
                'booking'    => $booking,
                'pricing'    => $pricing,
                'lock_key'   => $lockKey,
                'expires_at' => $booking->expires_at,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            $this->lockService->releaseLocks($lockKey);

            Log::error('BookingService:createBooking failed', [
                'event_id' => $data['event_id'],
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Confirm booking after successful payment.
     *
     * @return array{success: bool, ...}
     */
    public function confirmBooking(string $bookingReference, ?string $paymentIntentId = null): array
    {
        $booking = Booking::where('booking_reference', $bookingReference)->firstOrFail();

        if ($booking->status !== 'locked') {
            return ['success' => false, 'message' => 'Booking is not in locked status'];
        }

        if ($booking->isExpired()) {
            return ['success' => false, 'message' => 'Booking has expired'];
        }

        DB::beginTransaction();

        try {
            $booking->status            = 'confirmed';
            $booking->confirmed_at      = Carbon::now();
            $booking->payment_intent_id = $paymentIntentId;
            $booking->expires_at        = null;
            $booking->save();

            ElementLock::where('booking_reference', $bookingReference)->delete();

            $booking->event->updateCapacityCounts();

            DB::commit();

            // Update Redis cache + dispatch socket event (non-blocking queue)
            $seats = $booking->items->map(fn ($item) => [
                'element_id' => $item->event_element_id,
                'status' => 'confirmed'
            ])->toArray();

            $this->dispatchSeatUpdate($booking->event_id, $seats);

            return [
                'success' => true,
                'booking' => $booking->load('items'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Cancel a booking.
     *
     * @return array{success: bool, ...}
     */
    public function cancelBooking(string $bookingReference): array
    {
        $booking = Booking::where('booking_reference', $bookingReference)->firstOrFail();

        if (! in_array($booking->status, ['pending', 'locked', 'confirmed'], true)) {
            return ['success' => false, 'message' => 'Booking cannot be cancelled'];
        }

        DB::beginTransaction();

        try {
            $booking->status       = 'cancelled';
            $booking->cancelled_at = Carbon::now();
            $booking->save();

            BookingItem::where('booking_id', $booking->id)->update(['status' => 'cancelled']);

            ElementLock::where('booking_reference', $bookingReference)->delete();

            // Also release by lock_key for pending bookings that never got a reference
            $lockKey = $booking->metadata['lock_key'] ?? null;
            if ($lockKey) {
                ElementLock::where('lock_key', $lockKey)->delete();
            }

            $booking->event->updateCapacityCounts();

            DB::commit();

            // Update Redis cache + dispatch socket event (non-blocking queue)
            $seats = $booking->items->map(fn ($item) => [
                'element_id' => $item->event_element_id,
                'status' => 'available'
            ])->toArray();

            $this->dispatchSeatUpdate($booking->event_id, $seats);

            return ['success' => true, 'booking' => $booking];
        } catch (\Exception $e) {
            DB::rollBack();

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ── Private Helpers ──────────────────────────────────────────────────────

    /**
     * Dispatch seat status update: update Redis cache + broadcast socket event.
     * Uses queue job for non-blocking execution.
     */
    private function dispatchSeatUpdate(int $eventId, array $seats): void
    {
        if (empty($seats)) {
            return;
        }

        // Update Redis cache immediately (fast, < 10ms)
        $this->cacheService?->setSeatStatuses($eventId, $seats);

        // Also update via queue for socket broadcast (non-blocking)
        UpdateSeatStatus::dispatch($eventId, $seats);

        // Legacy: direct socket service call (kept for backward compatibility)
        $this->socketService?->broadcastSeatBatchUpdate($eventId, $seats);
    }
}