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

        foreach ($elements as $element) {
            $unitPrice = $this->calculateElementPrice($element, $event);
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

    private function calculateElementPrice(EventElement $element, Event $event): float
    {
        $basePrice = (float) $event->base_price;

        // Zone-based price modifier
        if ($element->zone_id) {
            $zone = TemplateZone::find($element->zone_id);
            if ($zone) {
                $basePrice = $zone->calculatePrice($basePrice);
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
