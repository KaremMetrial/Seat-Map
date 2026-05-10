<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TemplateZone extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'template_id',
        'name',
        'code',
        'description',
        'color',
        'priority',
        'base_price',
        'service_fee',
        'capacity',
        'max_booking_per_order',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'base_price'  => 'decimal:2',
        'service_fee' => 'decimal:2',
        'settings'    => 'array',
        'is_active'   => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function template(): BelongsTo
    {
        return $this->belongsTo(VenueTemplate::class, 'template_id');
    }

    public function elements(): BelongsToMany
    {
        return $this->belongsToMany(
            TemplateElement::class,
            'element_zone_map',
            'template_zone_id',
            'template_element_id',
        )->withPivot('price_modifier', 'modifier_type');
    }

    // ── Business logic ────────────────────────────────────────────────────────

    /**
     * Apply zone price modifier to a base price.
     */
    public function calculatePrice(float $basePrice): float
    {
        return max(0.0, round($basePrice + (float) $this->base_price, 2));
    }
}
