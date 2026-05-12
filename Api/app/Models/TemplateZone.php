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

    // ── Scopes ─────────────────────────────────────────────────────────────────

    /**
     * Scope to only active zones.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Business logic ────────────────────────────────────────────────────────

    /**
     * Calculate final price for an element in this zone.
     *
     * @param float $productBasePrice The base price of the product/event
     * @param float|null $pivotModifier Price modifier from element-zone pivot table
     * @param string|null $pivotModifierType Type of modifier ('fixed' or 'percent')
     * @return float
     */
    public function calculateFinalPrice(
        float $productBasePrice,
        ?float $pivotModifier = null,
        ?string $pivotModifierType = null
    ): float {
        // Start with product base price
        $price = $productBasePrice;

        // Add zone base price
        $price += (float) $this->base_price;

        // Apply element-specific modifier from pivot table if present
        if ($pivotModifier !== null && $pivotModifierType !== null) {
            $price = match ($pivotModifierType) {
                'fixed' => $price + $pivotModifier,
                'percent' => $price * (1 + ($pivotModifier / 100)),
                default => $price,
            };
        }

        // Add service fee
        $price += (float) $this->service_fee;

        return max(0.0, round($price, 2));
    }

    /**
     * Legacy method for backward compatibility.
     * Apply zone price modifier to a base price.
     * 
     * @deprecated Use calculateFinalPrice() instead
     */
    public function calculatePrice(float $basePrice): float
    {
        return max(0.0, round($basePrice + (float) $this->base_price, 2));
    }

    /**
     * Get actual element count from pivot table (vs stored capacity).
     */
    public function getActualElementCount(): int
    {
        return $this->elements()->count();
    }

    /**
     * Sync capacity column with actual element count.
     */
    public function syncCapacity(): void
    {
        $this->update(['capacity' => $this->getActualElementCount()]);
    }
}
