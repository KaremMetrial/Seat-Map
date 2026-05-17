<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EventElement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Seat Cache Service — Redis-based caching for seat availability.
 *
 * Uses Redis Hash for O(1) per-seat lookups:
 *   Key: "event:{eventId}:seats"
 *   Field: element_id
 *   Value: "available" | "booked" | "locked"
 *
 * This replaces the expensive hydrateBookingStatus() pattern which
 * loaded thousands of IDs into PHP memory and looped over them.
 */
class SeatCacheService
{
    private const PREFIX = 'event:';
    private const SUFFIX = ':seats';
    private const TTL = 86400; // 24 hours

    /**
     * Get the Redis key for an event's seat cache.
     */
    private function key(int $eventId): string
    {
        return self::PREFIX . $eventId . self::SUFFIX;
    }

    /**
     * Get status for a single seat. O(1).
     */
    public function getSeatStatus(int $eventId, int $elementId): ?string
    {
        $result = Redis::hget($this->key($eventId), (string) $elementId);
        return $result !== false ? $result : null;
    }

    /**
     * Get statuses for multiple seats. O(n) where n = number of seats requested.
     */
    public function getSeatStatuses(int $eventId, array $elementIds): array
    {
        if (empty($elementIds)) {
            return [];
        }

        $fields = array_map('strval', $elementIds);
        $values = Redis::hmget($this->key($eventId), $fields);

        $result = [];
        foreach ($elementIds as $index => $id) {
            $result[$id] = $values[$index] ?: 'available';
        }

        return $result;
    }

    /**
     * Set status for a single seat. O(1).
     */
    public function setSeatStatus(int $eventId, int $elementId, string $status): void
    {
        $key = $this->key($eventId);
        Redis::hset($key, (string) $elementId, $status);
        Redis::expire($key, self::TTL);
    }

    /**
     * Set statuses for multiple seats in bulk. Uses Redis pipeline for efficiency.
     */
    public function setSeatStatuses(int $eventId, array $seats): void
    {
        if (empty($seats)) {
            return;
        }

        $key = $this->key($eventId);

        // Use Redis pipeline for bulk operations
        Redis::pipeline(function ($pipe) use ($key, $seats) {
            foreach ($seats as $seat) {
                $pipe->hset($key, (string) $seat['element_id'], $seat['status']);
            }
            $pipe->expire($key, self::TTL);
        });
    }

    /**
     * Remove a seat from cache.
     */
    public function removeSeat(int $eventId, int $elementId): void
    {
        Redis::hdel($this->key($eventId), (string) $elementId);
    }

    /**
     * Remove multiple seats from cache.
     */
    public function removeSeats(int $eventId, array $elementIds): void
    {
        if (empty($elementIds)) {
            return;
        }

        $key = $this->key($eventId);
        foreach ($elementIds as $id) {
            Redis::hdel($key, (string) $id);
        }
    }

    /**
     * Clear all cached seats for an event.
     */
    public function clearEvent(int $eventId): void
    {
        Redis::del($this->key($eventId));
    }

    /**
     * Check if cache exists for an event.
     */
    public function hasCache(int $eventId): bool
    {
        return Redis::exists($this->key($eventId)) > 0;
    }

    /**
     * Get count of cached seats for an event.
     */
    public function getCachedCount(int $eventId): int
    {
        return Redis::hlen($this->key($eventId));
    }

    /**
     * Warm the cache for an event — loads all seat statuses from DB into Redis.
     * Uses raw DB query to bypass Eloquent SoftDeletes issue.
     * Called by queue job after event publish or template change.
     */
    public function warmCache(int $eventId): int
    {
        $this->clearEvent($eventId);

        $key = $this->key($eventId);
        $count = 0;
        $batch = [];

        // Use raw DB query to avoid SoftDeletes scope (table may not have deleted_at)
        $elementIds = DB::table('event_elements')
            ->where('event_id', $eventId)
            ->pluck('id');

        foreach ($elementIds as $elementId) {
            $status = $this->computeStatusFromDb($elementId);
            $batch[(string) $elementId] = $status;
            $count++;

            // Execute in batches of 1000 using pipeline
            if ($count % 1000 === 0) {
                Redis::pipeline(function ($pipe) use ($key, $batch) {
                    foreach ($batch as $field => $value) {
                        $pipe->hset($key, $field, $value);
                    }
                });
                $batch = [];
            }
        }

        // Final batch
        if (!empty($batch)) {
            Redis::pipeline(function ($pipe) use ($key, $batch) {
                foreach ($batch as $field => $value) {
                    $pipe->hset($key, $field, $value);
                }
                $pipe->expire($key, self::TTL);
            });
        } else {
            Redis::expire($key, self::TTL);
        }

        return $count;
    }

    /**
     * Compute seat status from database (used during cache warming).
     */
    private function computeStatusFromDb(int $elementId): string
    {
        $booked = \App\Models\BookingItem::where('event_element_id', $elementId)
            ->where('status', 'booked')
            ->exists();

        if ($booked) {
            return 'booked';
        }

        $locked = \App\Models\ElementLock::where('event_element_id', $elementId)
            ->where('expires_at', '>', now())
            ->exists();

        if ($locked) {
            return 'locked';
        }

        return 'available';
    }

    /**
     * Get all available seat IDs within a viewport. Used by available() endpoint.
     * Uses raw DB query to bypass Eloquent SoftDeletes issue.
     */
    public function getAvailableInViewport(
        int $eventId,
        float $x,
        float $y,
        float $width,
        float $height
    ): array {
        // Get element IDs in viewport from DB (spatial query with index)
        $elementIds = DB::table('event_elements')
            ->where('event_id', $eventId)
            ->where('is_bookable', true)
            ->whereBetween('x', [$x, $x + $width])
            ->whereBetween('y', [$y, $y + $height])
            ->pluck('id')
            ->toArray();

        if (empty($elementIds)) {
            return [];
        }

        // Check status from cache
        $statuses = $this->getSeatStatuses($eventId, $elementIds);

        // Filter to only available
        return array_keys(array_filter($statuses, fn ($s) => $s === 'available'));
    }
}