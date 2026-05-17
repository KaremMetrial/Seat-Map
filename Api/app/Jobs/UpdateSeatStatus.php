<?php

namespace App\Jobs;

use App\Events\SeatStatusChanged;
use App\Services\SeatCacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queue job to update seat status in cache and broadcast to socket clients.
 *
 * Triggered by:
 * - Booking created (seats → booked)
 * - Booking confirmed (seats → confirmed)
 * - Booking cancelled (seats → available)
 * - Lock acquired (seats → locked)
 * - Lock released (seats → available)
 *
 * This is a fast job — updates Redis hash + fires event. Runs in < 100ms.
 */
class UpdateSeatStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(
        public int $eventId,
        public array $seats,  // [['element_id' => 101, 'status' => 'booked'], ...]
        public string $type = 'seat-batch-update'
    ) {}

    public function handle(SeatCacheService $cache): void
    {
        // 1. Update Redis cache
        $cache->setSeatStatuses($this->eventId, $this->seats);

        // 2. Broadcast to socket clients
        event(new SeatStatusChanged($this->eventId, $this->seats, $this->type));

        Log::info('UpdateSeatStatus: completed', [
            'event_id' => $this->eventId,
            'seat_count' => count($this->seats),
            'type' => $this->type,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('UpdateSeatStatus: failed', [
            'event_id' => $this->eventId,
            'error' => $exception->getMessage(),
        ]);
    }
}