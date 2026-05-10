<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingItem extends Model
{
    protected $fillable = [
        'booking_id',
        'event_element_id',
        'event_id',
        'element_type',
        'label',
        'unit_price',
        'total_price',
        'quantity',
        'status',
    ];

    protected $casts = [
        'unit_price'  => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function eventElement(): BelongsTo
    {
        return $this->belongsTo(EventElement::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeBooked(Builder $query): Builder
    {
        return $query->where('status', 'booked');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }
}
