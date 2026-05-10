<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ElementLock;
use App\Models\Event;
use App\Models\EventElement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seat Lock Service — prevents double booking through atomic operations.
 *
 * Defence layers (in execution order):
 *
 *   1. Database transaction with explicit locking
 *   2. Availability check inside the transaction — TOCTOU-safe.
 *   3. Expired-lock DELETE inside a DB transaction — removes ghost rows before INSERT.
 *   4. DB UNIQUE on element_locks(event_element_id) — last-resort guard for races.
 */
class SeatLockService
{
    private const LOCK_TTL_MINUTES = 10;
    private const MAX_DEADLOCK_RETRIES = 3;

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Lock multiple elements atomically.
     *
     * @param  array<int> $elementIds  event_element IDs to lock
     * @param  string     $lockKey     Non-empty session/cart identifier.
     *                                 Auto-generates a UUID if empty string passed.
     * @param  int        $ttlMinutes  Lock duration (default 10 min)
     * @return array{success: bool, message: string, ...}
     */
    public function lockElements(
        array $elementIds,
        string $lockKey,
        int $ttlMinutes = self::LOCK_TTL_MINUTES,
    ): array {
        // Guard: empty lock_key would cause all callers to share the same mutex
        if (trim($lockKey) === '') {
            $lockKey = Str::uuid()->toString();
        }

        $now = Carbon::now();
        $expiresAt = $now->copy()->addMinutes($ttlMinutes);

        $retries = 0;

        while ($retries < self::MAX_DEADLOCK_RETRIES) {
            try {
                DB::beginTransaction();

                // Check availability WITHIN transaction using SELECT FOR UPDATE
                // This prevents TOCTOU race condition
                $unavailable = $this->checkUnavailableInTransaction($elementIds);

                if (! empty($unavailable)) {
                    DB::rollBack();

                    return [
                        'success' => false,
                        'message' => 'Some seats are no longer available',
                        'unavailable' => $unavailable,
                        'code' => 'seats_unavailable',
                    ];
                }

                // Bulk-load event_ids
                $eventIdMap = EventElement::whereIn('id', $elementIds)
                    ->pluck('event_id', 'id')
                    ->all();

                foreach ($elementIds as $elementId) {
                    // Delete any expired lock
                    ElementLock::where('event_element_id', $elementId)
                        ->where('expires_at', '<=', $now)
                        ->delete();

                    try {
                        ElementLock::create([
                            'event_element_id' => $elementId,
                            'event_id' => $eventIdMap[$elementId] ?? null,
                            'lock_key' => $lockKey,
                            'expires_at' => $expiresAt,
                            'locked_at' => $now,
                        ]);
                    } catch (\Exception $e) {
                        DB::rollBack();

                        // Check if this is a deadlock or a genuine conflict
                        if ($this->isDeadlock($e) && $retries < self::MAX_DEADLOCK_RETRIES - 1) {
                            $retries++;
                            continue 2; // Retry the entire transaction
                        }

                        return [
                            'success' => false,
                            'message' => 'Seat was just taken by another user',
                            'conflict_element' => $elementId,
                            'code' => 'seat_taken',
                        ];
                    }
                }

                DB::commit();

                return [
                    'success' => true,
                    'message' => 'Seats locked successfully',
                    'locked_elements' => $elementIds,
                    'lock_key' => $lockKey,
                    'expires_at' => $expiresAt->toIso8601String(),
                ];
            } catch (\Exception $e) {
                DB::rollBack();

                // Retry on deadlock
                if ($this->isDeadlock($e) && $retries < self::MAX_DEADLOCK_RETRIES - 1) {
                    $retries++;
                    usleep(100000 * $retries); // Exponential backoff: 100ms, 200ms, 400ms
                    continue;
                }

                return [
                    'success' => false,
                    'message' => 'Failed to lock seats: ' . $e->getMessage(),
                    'code' => 'lock_error',
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'Failed to lock seats after multiple retries',
            'code' => 'lock_retry_exhausted',
        ];
    }

    /**
     * Check unavailable elements WITHIN transaction using SELECT FOR UPDATE.
     * This locks the rows to prevent race conditions.
     *
     * @param array<int> $elementIds
     * @return array<int>
     */
    private function checkUnavailableInTransaction(array $elementIds): array
    {
        // Use SELECT FOR UPDATE to lock the rows and prevent concurrent modifications
        $booked = \App\Models\BookingItem::whereIn('event_element_id', $elementIds)
            ->where('status', 'booked')
            ->lockForUpdate()
            ->pluck('event_element_id')
            ->all();

        $locked = ElementLock::whereIn('event_element_id', $elementIds)
            ->where('expires_at', '>', Carbon::now())
            ->lockForUpdate()
            ->pluck('event_element_id')
            ->all();

        return array_values(array_unique(array_merge($booked, $locked)));
    }

    /**
     * Release all locks held by a given lock_key.
     */
    public function releaseLocks(string $lockKey): int
    {
        return ElementLock::where('lock_key', $lockKey)->delete();
    }

    /**
     * Extend the expiration of all locks for a given lock_key.
     */
    public function extendLock(string $lockKey, int $minutes = self::LOCK_TTL_MINUTES): bool
    {
        $updated = ElementLock::where('lock_key', $lockKey)
            ->update(['expires_at' => Carbon::now()->addMinutes($minutes)]);

        return $updated > 0;
    }

    /**
     * Get available (bookable, unlocked, unbooked) elements for an event.
     */
    public function getAvailableElements(Event $event): array
    {
        return $event->eventElements()
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
            ->get()
            ->map(fn (EventElement $element) => [
                'id' => $element->id,
                'type' => $element->element_type,
                'label' => $element->getLabel(),
                'x' => $element->x,
                'y' => $element->y,
                'status' => 'available',
            ])
            ->toArray();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Check if exception is a database deadlock.
     */
    private function isDeadlock(\Exception $e): bool
    {
        $message = $e->getMessage();
        $code = $e->getCode();

        return strpos($message, 'Deadlock') !== false ||
               $code === 1213 || // Deadlock found
               $code === 1205;   // Lock wait timeout
    }
}
