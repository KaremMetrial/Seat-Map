<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Element Lock — temporary hold on an element during the booking process.
 *
 * TTL-based expiration ensures locks are automatically released.
 * The unique constraint on event_element_id ensures only one active lock
 * can exist per element at any time.
 */
class ElementLock extends Model
{
    protected $fillable = [
        'event_element_id',
        'event_id',
        'lock_key',
        'booking_reference',
        'expires_at',
        'locked_at',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'locked_at'  => 'datetime',
        'metadata'   => 'array',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    // ── Business logic ────────────────────────────────────────────────────────

    public function isValid(): bool
    {
        return now()->lt($this->expires_at);
    }

    public function extend(int $minutes = 10): void
    {
        $this->expires_at = now()->addMinutes($minutes);
        $this->save();
    }

    /**
     * Delete all expired locks. Call from a scheduled artisan command.
     *
     * @return int Number of rows deleted
     */
    public static function cleanup(): int
    {
        return static::where('expires_at', '<', now())->delete();
    }
}
