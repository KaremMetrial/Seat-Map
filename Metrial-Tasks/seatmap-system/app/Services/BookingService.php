<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\ElementLock;
use App\Models\Event;
use App\Models\EventElement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Booking Service — orchestrates the full booking lifecycle.
 *
 * Flow:
 *   1. Validate event is open for booking
 *   2. Verify all requested elements exist and are bookable
 *   3. Lock elements via SeatLockService (Redis mutex + DB constraint)
 *   4. Calculate pricing
 *   5. Create booking + booking_items in a transaction
 *   6. Link locks to the booking reference
 *
 * Confirm flow (after payment):
 *   7. Mark booking confirmed, delete locks
 *
 * Cancel flow:
 *   8. Mark booking cancelled, cancel items, delete locks
 */
class BookingService
{
    public function __construct(
        private SeatLockService $lockService,
        private PricingService $pricingService,
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
        $event      = Event::findOrFail($data['event_id']);
        $elementIds = $data['element_ids'];
        $lockKey    = (isset($data['lock_key']) && trim((string) $data['lock_key']) !== '')
                        ? $data['lock_key']
                        : Str::uuid()->toString();

        if (! $event->isBookingOpen()) {
            return ['success' => false, 'message' => 'Booking is not open for this event'];
        }

        // Verify all elements exist and are bookable
        $elements = EventElement::whereIn('id', $elementIds)->get();

        if ($elements->count() !== count($elementIds)) {
            return ['success' => false, 'message' => 'Some elements were not found'];
        }

        foreach ($elements as $element) {
            if (! $element->is_bookable) {
                return ['success' => false, 'message' => "Element {$element->id} is not bookable"];
            }
        }

        // Lock elements (Redis mutex + DB unique constraint)
        $lockResult = $this->lockService->lockElements($elementIds, $lockKey);

        if (! $lockResult['success']) {
            return $lockResult;
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

            return ['success' => true, 'booking' => $booking];
        } catch (\Exception $e) {
            DB::rollBack();

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get the full seat map for an event.
     * Uses batch hydration — exactly 4 queries regardless of element count.
     *
     * @return array{event: array, elements: mixed, zones: mixed}
     */
    public function getSeatMap(Event $event): array
    {
        // Eager-load template so canvas dimensions cost 0 extra queries
        $event->loadMissing('template.zones');

        $elements = $event->eventElements()->orderBy('z_index')->get();

        // Resolve booking_status for the whole collection in 2 queries (not 2N)
        EventElement::hydrateBookingStatus($elements);

        return [
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
        ];
    }
}
