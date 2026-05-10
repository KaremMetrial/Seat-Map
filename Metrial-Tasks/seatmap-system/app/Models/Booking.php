<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Booking extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'booking_reference',
        'internal_reference',
        'event_id',
        'user_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'subtotal',
        'service_fee',
        'tax_amount',
        'total_amount',
        'currency',
        'status',
        'locked_at',
        'confirmed_at',
        'completed_at',
        'cancelled_at',
        'expires_at',
        'payment_intent_id',
        'payment_provider',
        'metadata',
    ];

    protected $casts = [
        'subtotal'     => 'decimal:2',
        'service_fee'  => 'decimal:2',
        'tax_amount'   => 'decimal:2',
        'total_amount' => 'decimal:2',
        'locked_at'    => 'datetime',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expires_at'   => 'datetime',
        'metadata'     => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $booking): void {
            if (empty($booking->booking_reference)) {
                $booking->booking_reference = 'BK-' . strtoupper(Str::random(8));
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        // config('auth.providers.users.model') is the correct key
        return $this->belongsTo(config('auth.providers.users.model', \App\Models\User::class), 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(BookingItem::class, 'booking_id');
    }

    // ── Business logic ────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at !== null && now()->gt($this->expires_at);
    }

    public function confirm(): void
    {
        $this->status        = 'confirmed';
        $this->confirmed_at  = now();
        $this->expires_at    = null;
        $this->save();

        $this->event->updateCapacityCounts();
    }

    public function cancel(): void
    {
        $this->status       = 'cancelled';
        $this->cancelled_at = now();
        $this->save();

        ElementLock::where('booking_reference', $this->booking_reference)->delete();

        $this->event->updateCapacityCounts();
    }
}
