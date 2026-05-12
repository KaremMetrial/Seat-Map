<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\EventElement;
use App\Models\PricingRule;
use App\Models\TemplateZone;

class PricingService
{
    private const SERVICE_FEE_RATE = 0.05; // 5%
    private const TAX_RATE         = 0.10; // 10%

    /**
     * Calculate the full pricing breakdown for a set of elements.
     *
     * @param  array<int> $elementIds
     * @return array{subtotal: float, service_fee: float, tax: float, total: float, items: array}
     */
    public function calculatePrice(array $elementIds, Event $event): array
    {
        $elements = EventElement::whereIn('id', $elementIds)->get();
        $subtotal = 0.0;
        $items    = [];

        // Preload pivot data for zone pricing to prevent N+1
        $pivotData = $this->preloadPivotData($elements);

        foreach ($elements as $element) {
            $unitPrice = $this->calculateElementPrice($element, $event, $pivotData);
            $capacity  = (int) ($element->data_json['capacity'] ?? 1);
            $lineTotal = round($unitPrice * $capacity, 2);

            $items[]   = [
                'element_id'   => $element->id,
                'element_type' => $element->element_type,
                'label'        => $element->getLabel(),
                'unit_price'   => $unitPrice,
                'total_price'  => $lineTotal,
            ];

            $subtotal += $lineTotal;
        }

        $subtotal   = round($subtotal, 2);
        $serviceFee = $this->fee($subtotal);
        $tax        = $this->tax($subtotal);

        return [
            'subtotal'    => $subtotal,
            'service_fee' => $serviceFee,
            'tax'         => $tax,
            'total'       => round($subtotal + $serviceFee + $tax, 2),
            'items'       => $items,
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Preload pivot data for zone-element relationships to prevent N+1 queries.
     *
     * @param  \Illuminate\Support\Collection $elements
     * @return array<int, array{zone_id: int, price_modifier: ?float, modifier_type: ?string}>
     */
    private function preloadPivotData($elements): array
    {
        $pivotData = [];
        
        // Get template element IDs and zone IDs from event elements
        $templateElementIds = $elements->pluck('template_element_id')->filter()->unique()->all();
        $zoneIds = $elements->pluck('zone_id')->filter()->unique()->all();
        
        if (empty($templateElementIds) || empty($zoneIds)) {
            return $pivotData;
        }
        
        // Eager load the pivot relationships
        $templateElements = \App\Models\TemplateElement::whereIn('id', $templateElementIds)
            ->with(['zones' => fn($q) => $q->whereIn('template_zones.id', $zoneIds)])
            ->get()
            ->keyBy('id');
        
        foreach ($elements as $element) {
            if (!$element->template_element_id || !$element->zone_id) {
                continue;
            }
            
            $templateElement = $templateElements->get($element->template_element_id);
            if (!$templateElement) {
                continue;
            }
            
            $zone = $templateElement->zones->firstWhere('id', $element->zone_id);
            if ($zone && $zone->pivot) {
                $pivotData[$element->id] = [
                    'zone_id' => $zone->id,
                    'price_modifier' => $zone->pivot->price_modifier,
                    'modifier_type' => $zone->pivot->modifier_type,
                ];
            }
        }
        
        return $pivotData;
    }

    private function calculateElementPrice(EventElement $element, Event $event, array $pivotData = []): float
    {
        $basePrice = (float) $event->base_price;

        // Zone-based price modifier (with pivot data for element-specific pricing)
        if ($element->zone_id) {
            $zone = TemplateZone::find($element->zone_id);
            if ($zone) {
                // Use preloaded pivot data if available, otherwise fall back to query
                $pivotModifier = null;
                $pivotModifierType = null;
                
                if (isset($pivotData[$element->id])) {
                    $pivotModifier = $pivotData[$element->id]['price_modifier'];
                    $pivotModifierType = $pivotData[$element->id]['modifier_type'];
                } else {
                    // Fallback for single element lookups
                    $pivot = \App\Models\TemplateElement::find($element->template_element_id)
                        ?->zones()
                        ->where('template_zones.id', $zone->id)
                        ->first()?->pivot;
                    $pivotModifier = $pivot?->price_modifier;
                    $pivotModifierType = $pivot?->modifier_type;
                }
                
                $basePrice = $zone->calculateFinalPrice(
                    $basePrice,
                    $pivotModifier,
                    $pivotModifierType
                );
            }
        }

        // Dynamic pricing rules (ordered by priority desc)
        $rules = PricingRule::where('is_active', true)
            ->where(fn ($q) => $q
                ->whereNull('template_id')
                ->orWhere('template_id', $event->template_id)
            )
            ->where(fn ($q) => $q
                ->whereNull('valid_to')
                ->orWhere('valid_to', '>', now())
            )
            ->orderByDesc('priority')
            ->get();

        foreach ($rules as $rule) {
            if ($this->ruleMatches($element, $rule->conditions_json ?? [])) {
                if ($rule->adjustment_type === 'percent') {
                    $basePrice += $basePrice * ($rule->price_adjustment / 100);
                } else {
                    $basePrice += $rule->price_adjustment;
                }
            }
        }

        return max(0.0, round($basePrice, 2));
    }

    private function ruleMatches(EventElement $element, array $conditions): bool
    {
        if (isset($conditions['date_from'], $conditions['date_to'])) {
            $now = now();
            if ($now->lt($conditions['date_from']) || $now->gt($conditions['date_to'])) {
                return false;
            }
        }

        if (isset($conditions['element_types'])) {
            if (! in_array($element->element_type, $conditions['element_types'], true)) {
                return false;
            }
        }

        return true;
    }

    private function fee(float $subtotal): float
    {
        return round($subtotal * self::SERVICE_FEE_RATE, 2);
    }

    private function tax(float $subtotal): float
    {
        return round($subtotal * self::TAX_RATE, 2);
    }
}
