<?php

namespace App\Jobs;

use App\Services\SeatCacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queue job to rebuild the Redis seat cache for an event.
 *
 * Triggered by:
 * - Event published (snapshot created)
 * - Template elements modified (admin changes layout)
 * - Manual cache refresh command
 *
 * For 100K seats, this runs in background — doesn't block the HTTP request.
 */
class RebuildSeatCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300; // 5 minutes for large events
    public array $backoff = [30, 60, 120]; // Retry delays

    public function __construct(
        public int $eventId
    ) {}

    public function handle(SeatCacheService $cache): void
    {
        Log::info('RebuildSeatCache: starting', ['event_id' => $this->eventId]);

        $start = microtime(true);
        $count = $cache->warmCache($this->eventId);
        $duration = round(microtime(true) - $start, 2);

        Log::info('RebuildSeatCache: completed', [
            'event_id' => $this->eventId,
            'seats_cached' => $count,
            'duration_seconds' => $duration,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RebuildSeatCache: failed', [
            'event_id' => $this->eventId,
            'error' => $exception->getMessage(),
        ]);
    }
}