<?php

declare(strict_types=1);

namespace App\Models;

use App\Jobs\RebuildSeatCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Event extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'template_id',
        'title',
        'slug',
        'description',
        'start_at',
        'end_at',
        'booking_open_at',
        'booking_close_at',
        'status',
        'snapshotted_at',
        'snapshot_version',
        'total_capacity',
        'available_capacity',
        'sold_count',
        'base_price',
        'metadata',
    ];

    protected $casts = [
        'start_at'        => 'datetime',
        'end_at'          => 'datetime',
        'booking_open_at' => 'datetime',
        'booking_close_at'=> 'datetime',
        'snapshotted_at'  => 'datetime',
        'base_price'      => 'decimal:2',
        'metadata'        => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $event): void {
            if (empty($event->slug)) {
                $event->slug = Str::slug($event->title);
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function template(): BelongsTo
    {
        return $this->belongsTo(VenueTemplate::class, 'template_id');
    }

    public function eventElements(): HasMany
    {
        return $this->hasMany(EventElement::class, 'event_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'event_id');
    }

    // ── Business logic ────────────────────────────────────────────────────────

    public function isBookingOpen(): bool
    {
        if ($this->status !== 'published') {
            return false;
        }

        $now = Carbon::now();

        if ($this->booking_open_at && $now->lt($this->booking_open_at)) {
            return false;
        }

        if ($this->booking_close_at && $now->gt($this->booking_close_at)) {
            return false;
        }

        return true;
    }

    /**
     * Publish the event — creates an immutable snapshot of template elements.
     * Dispatches a queue job to warm the Redis seat cache.
     */
    public function publish(): void
    {
        if ($this->status === 'published') {
            return;
        }

        $this->createSnapshot();

        $this->status         = 'published';
        $this->snapshotted_at = Carbon::now();
        $this->save();

        // Warm Redis cache in background (non-blocking)
        // For 100K seats, this takes ~10-30 seconds in queue
        RebuildSeatCache::dispatch($this->id);
    }

    /**
     * Bulk-copy template elements into event_elements (the snapshot).
     */
    protected function createSnapshot(): void
    {
        DB::transaction(function () {
            $elements = $this->template->elements;
            $zoneMap  = $this->buildZoneMap();

            $snapshotData = $elements->map(function (TemplateElement $element) use ($zoneMap) {
                return $element->toEventElement($this->id, $zoneMap[$element->id] ?? null);
            });

            EventElement::insert($snapshotData->toArray());

            $this->updateCapacityCounts();
        });
    }

    protected function buildZoneMap(): array
    {
        // Eager load elements on zones to prevent N+1
        $this->template->loadMissing('zones.elements');
        
        $map = [];
        foreach ($this->template->zones as $zone) {
            foreach ($zone->elements as $element) {
                $map[$element->id] = $zone->id;
            }
        }

        return $map;
    }

    /**
     * Recalculate and persist capacity counters.
     *
     * booking_status is NOT a real column — the booked count is derived via
     * a correlated whereExists subquery against booking_items.
     */
    public function updateCapacityCounts(): void
    {
        $bookableCount = $this->eventElements()
            ->where('is_bookable', true)
            ->count();

        $bookedCount = $this->eventElements()
            ->where('is_bookable', true)
            ->whereExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('booking_items')
                    ->whereColumn('booking_items.event_element_id', 'event_elements.id')
                    ->where('booking_items.status', 'booked');
            })
            ->count();

        $this->total_capacity     = $bookableCount;
        $this->available_capacity = $bookableCount - $bookedCount;
        $this->sold_count         = $bookedCount;
        $this->save();
    }
}
