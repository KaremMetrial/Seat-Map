<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Event Element — SNAPSHOT COPY of TemplateElement.
 *
 * CRITICAL: This is a point-in-time copy that NEVER changes after creation.
 * Layout changes after publish cannot affect booked seats.
 *
 * Booking Status Flow:  available → locked → booked
 *
 * ── N+1 Prevention ───────────────────────────────────────────────────────────
 * booking_status is NOT a stored column. Never call $element->booking_status
 * in a plain loop — use one of the two batch patterns below first.
 *
 * Pattern A — scope (best for filtering / pagination):
 *   $elements = EventElement::withBookingStatus()->where('event_id', $id)->get();
 *   // Status resolved via CASE/EXISTS in the SELECT — 0 extra queries
 *
 * Pattern B — batch hydration (best for already-loaded Collections):
 *   EventElement::hydrateBookingStatus($elements);
 *   // Fires exactly 2 bulk queries for the whole collection
 * ─────────────────────────────────────────────────────────────────────────────
 */
class EventElement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'event_id',
        'template_element_id',
        'element_type',
        'x',
        'y',
        'width',
        'height',
        'rotation',
        'z_index',
        'parent_id',
        'data_json',
        'style_json',
        'is_bookable',
        'zone_id',
        'booked_price',
    ];

    protected $casts = [
        'x'           => 'decimal:2',
        'y'           => 'decimal:2',
        'width'       => 'decimal:2',
        'height'      => 'decimal:2',
        'rotation'    => 'decimal:2',
        'data_json'   => 'array',
        'style_json'  => 'array',
        'is_bookable' => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(TemplateZone::class, 'zone_id');
    }

    // ── Booking status — N+1-safe ─────────────────────────────────────────────

    /**
     * Query scope: resolves booking_status for every row via a CASE/EXISTS
     * subquery added to the SELECT. Zero extra queries regardless of row count.
     *
     * @param Builder<EventElement> $query
     * @return Builder<EventElement>
     */
    public function scopeWithBookingStatus(Builder $query): Builder
    {
        $now = Carbon::now()->toDateTimeString();

        return $query->selectRaw("
            event_elements.*,
            CASE
                WHEN EXISTS (
                    SELECT 1 FROM booking_items bi
                    WHERE bi.event_element_id = event_elements.id
                      AND bi.status = 'booked'
                ) THEN 'booked'
                WHEN EXISTS (
                    SELECT 1 FROM element_locks el
                    WHERE el.event_element_id = event_elements.id
                      AND el.expires_at > ?
                ) THEN 'locked'
                ELSE 'available'
            END AS booking_status
        ", [$now]);
    }

    /**
     * Batch hydration: resolves booking_status for an already-loaded Collection
     * using exactly TWO queries total, regardless of collection size.
     *
     * Mutates the collection in-place. Safe to call multiple times (idempotent).
     *
     * @param Collection<int, EventElement> $elements
     */
    public static function hydrateBookingStatus(Collection $elements): void
    {
        if ($elements->isEmpty()) {
            return;
        }

        $ids = $elements->pluck('id')->all();

        // Query 1 — all booked element IDs (flip to hash-map for O(1) lookup)
        $bookedIds = BookingItem::whereIn('event_element_id', $ids)
            ->where('status', 'booked')
            ->pluck('event_element_id')
            ->flip()
            ->all();

        // Query 2 — all actively locked element IDs
        $lockedIds = ElementLock::whereIn('event_element_id', $ids)
            ->where('expires_at', '>', Carbon::now())
            ->pluck('event_element_id')
            ->flip()
            ->all();

        foreach ($elements as $element) {
            if (isset($bookedIds[$element->id])) {
                $element->booking_status = 'booked';
            } elseif (isset($lockedIds[$element->id])) {
                $element->booking_status = 'locked';
            } else {
                $element->booking_status = 'available';
            }
        }
    }

    /**
     * Accessor — returns booking_status.
     *
     * Short-circuits to the pre-set attribute when either batch pattern has
     * been used (zero queries). Falls back to individual queries only for
     * single-model lookups — acceptable outside of collection loops.
     */
    public function getBookingStatusAttribute(): string
    {
        // Already resolved by scope or batch hydration — free return
        if (array_key_exists('booking_status', $this->attributes)) {
            return $this->attributes['booking_status'];
        }

        // Single-model fallback (not in a loop — acceptable)
        $booked = BookingItem::where('event_element_id', $this->id)
            ->where('status', 'booked')
            ->exists();

        if ($booked) {
            return 'booked';
        }

        $locked = ElementLock::where('event_element_id', $this->id)
            ->where('expires_at', '>', Carbon::now())
            ->exists();

        return $locked ? 'locked' : 'available';
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function getLabel(): ?string
    {
        return $this->data_json['label'] ?? null;
    }

    /**
     * Check if element is currently available for booking.
     * Call hydrateBookingStatus() first when checking a collection.
     */
    public function isAvailable(): bool
    {
        return $this->is_bookable && $this->booking_status === 'available';
    }
}
