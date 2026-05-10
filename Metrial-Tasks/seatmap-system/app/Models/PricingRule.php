<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends Model
{
    protected $fillable = [
        'name',
        'code',
        'rule_type',
        'conditions_json',
        'price_adjustment',
        'adjustment_type',
        'priority',
        'zone_id',
        'template_id',
        'valid_from',
        'valid_to',
        'is_active',
    ];

    protected $casts = [
        'conditions_json'  => 'array',
        'price_adjustment' => 'decimal:2',
        'valid_from'       => 'datetime',
        'valid_to'         => 'datetime',
        'is_active'        => 'boolean',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(TemplateZone::class, 'zone_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(VenueTemplate::class, 'template_id');
    }
}
