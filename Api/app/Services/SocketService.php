<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\BookingCreated;
use App\Events\SeatStatusChanged;
use App\Events\SeatsLocked;

/**
 * Socket Service — broadcasts real-time events using Laravel's event system.
 *
 * Uses Laravel Events with ShouldBroadcast to automatically publish
 * to Redis (or other broadcast drivers like Pusher/Reverb).
 */
class SocketService
{
    /**
     * Broadcast seat status change to all clients viewing this event.
     */
    public function broadcastSeatUpdate(int $eventId, int $elementId, string $status): void
    {
        event(new SeatStatusChanged(
            $eventId,
            [['element_id' => $elementId, 'status' => $status]],
            'seat-update'
        ));
    }

    /**
     * Broadcast multiple seat updates at once.
     */
    public function broadcastSeatBatchUpdate(int $eventId, array $seats): void
    {
        event(new SeatStatusChanged(
            $eventId,
            $seats,
            'seat-batch-update'
        ));
    }

    /**
     * Broadcast booking created event.
     */
    public function broadcastBookingCreated(int $eventId, string $bookingReference, array $elementIds): void
    {
        event(new BookingCreated($eventId, $bookingReference, $elementIds));
    }

    /**
     * Broadcast lock acquired (seat temporarily reserved).
     */
    public function broadcastSeatLocked(int $eventId, array $elementIds, string $lockKey): void
    {
        event(new SeatsLocked($eventId, $elementIds, $lockKey));
    }
}