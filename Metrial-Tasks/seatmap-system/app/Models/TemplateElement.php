<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Template Element — dynamic building block of venue layouts.
 *
 * Element types: seat | section | table | stage | shape | entrance | text
 */
class TemplateElement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'template_id',
        'element_type',
        'x',
        'y',
        'z',
        'width',
        'height',
        'vertical_clearance',
        'rotation',
        'z_index',
        'parent_id',
        'data_json',
        'style_json',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'x'                 => 'decimal:2',
        'y'                 => 'decimal:2',
        'z'                 => 'decimal:2',
        'width'             => 'decimal:2',
        'height'            => 'decimal:2',
        'vertical_clearance' => 'decimal:2',
        'rotation'          => 'decimal:2',
        'data_json'         => 'array',
        'style_json'        => 'array',
        'is_active'         => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function template(): BelongsTo
    {
        return $this->belongsTo(VenueTemplate::class, 'template_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('z_index');
    }

    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(
            TemplateZone::class,
            'element_zone_map',
            'template_element_id',
            'template_zone_id',
        )->withPivot('price_modifier', 'modifier_type');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getLabelAttribute(): ?string
    {
        return $this->data_json['label'] ?? null;
    }

    public function getSeatRowAttribute(): ?string
    {
        return $this->data_json['row'] ?? null;
    }

    public function getSeatNumberAttribute(): ?string
    {
        return $this->data_json['seat_number'] ?? null;
    }

    // ── Snapshot ──────────────────────────────────────────────────────────────

    /**
     * Convert to an array suitable for EventElement::insert() (bulk snapshot).
     *
     * EventElement::insert() bypasses Eloquent entirely — no model events,
     * no cast pipeline, no auto-timestamps. This method compensates:
     *   1. JSON-encodes array fields (insert() skips the cast pipeline).
     *   2. Sets created_at / updated_at explicitly (insert() skips timestamps).
     *
     * @param int      $eventId
     * @param int|null $zoneId
     * @return array<string, mixed>
     */
    public function toEventElement(int $eventId, ?int $zoneId = null): array
    {
        $now = now()->toDateTimeString();

        return [
            'event_id'            => $eventId,
            'template_element_id' => $this->id,
            'element_type'        => $this->element_type,
            'x'                   => $this->x,
            'y'                   => $this->y,
            'width'               => $this->width,
            'height'              => $this->height,
            'rotation'            => $this->rotation,
            'z_index'             => $this->z_index,
            'parent_id'           => $this->parent_id,
            // json_encode() required — insert() skips Eloquent cast pipeline
            'data_json'           => $this->data_json !== null
                                        ? json_encode($this->data_json)
                                        : null,
            'style_json'          => $this->style_json !== null
                                        ? json_encode($this->style_json)
                                        : null,
            'is_bookable'         => $this->is_bookable ?? $this->isBookable(),
            'zone_id'             => $zoneId,
            // Explicit timestamps — insert() bypasses Eloquent auto-timestamps
            'created_at'          => $now,
            'updated_at'          => $now,
        ];
    }

    /**
     * Whether this element type is bookable by default.
     */
    public function isBookable(): bool
    {
        return in_array($this->element_type, ['seat', 'table', 'standing_zone'], true);
    }
}
