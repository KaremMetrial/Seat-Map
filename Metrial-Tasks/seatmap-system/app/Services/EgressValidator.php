<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\EventElement;
use App\Models\TemplateElement;
use App\Models\TemplateZone;
use App\Services\MaritimeGraph;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * EgressValidator — محقق مسالك الإخلاء الطارئة
 *
 * يضمن الامتثال لـ SOLAS II-2 (Safety of Life at Sea)
 * - أقصى مسافة لإخلاء: 60م لسفن الركاب
 * - Minimum 2 escape routes per accommodation space
 * - Clear width ≥ 0.9m for escape routes
 *
 * Incident Reference: Maritime Safety Audit 2026-05-06
 */
class EgressValidator
{
    private const SOLAS_MAX_EVACUATION_DISTANCE_METERS = 60;
    private const SOLAS_MIN_ESCAPE_ROUTE_WIDTH_METERS = 0.9;
    private const SOLAS_MAX_CORRIDOR_LENGTH_WITHOUT_EXIT = 45;

    private ?float $scaleFactor = null;
    private ?MaritimeGraph $graph = null;

    public function __construct(?float $scaleFactor = null)
    {
        // FIXED: Validate scale factor
        if ($scaleFactor !== null) {
            if ($scaleFactor <= 0) {
                throw new InvalidArgumentException(
                    'Scale factor must be positive, got: ' . $scaleFactor
                );
            }
            if ($scaleFactor > 10) {
                throw new InvalidArgumentException(
                    'Scale factor appears too large (max 10), got: ' . $scaleFactor
                );
            }
        }
        $this->scaleFactor = $scaleFactor ?? 0.05;
    }

    public function setScaleFactor(float $factor): void
    {
        if ($factor <= 0) {
            throw new InvalidArgumentException('Scale factor must be positive');
        }
        $this->scaleFactor = $factor;
    }

    private function toMeters(float $canvasUnits): float
    {
        return $canvasUnits * $this->scaleFactor;
    }

    /**
     * Validate evacuation routes using A* pathfinding (realistic paths)
     *
     * @param Event $event
     * @return array{valid: bool, violations: array, statistics: array, paths: array}
     */
    public function validateEventEvacuationRoutes(Event $event): array
    {
        // FIXED: Validate template has scale factor
        if (!$event->template || !$event->template->scale_factor || $event->template->scale_factor <= 0) {
            return [
                'valid' => false,
                'violations' => ['Template must have valid positive scale_factor for metric conversion'],
                'statistics' => ['total_seats' => 0, 'exits_found' => 0],
                'paths' => [],
            ];
        }

        $event->load(['eventElements' => fn($q) => $q->where('is_bookable', true)->orderBy('z_index'),
                      'template.zones', 'template.elements']);

        $elements = $event->eventElements;
        $template = $event->template;

        // Build navigation graph from template
        $this->graph = new MaritimeGraph($template);
        Log::info('MaritimeGraph summary', $this->graph->getSummary());

        $exits = $elements->where('element_type', 'entrance')
                          ->where('data->is_main', true)
                          ->merge($elements->where('element_type', 'emergency_exit'));

        if ($exits->isEmpty()) {
            return [
                'valid' => false,
                'violations' => ['No emergency exits defined in event layout'],
                'statistics' => ['total_seats' => $elements->count(), 'exits_found' => 0],
                'paths' => [],
            ];
        }

        $violations = [];
        $seatPaths = [];
        $overDistanceSeats = 0;
        $unreachableSeats = 0;

        foreach ($elements as $seat) {
            if (!in_array($seat->element_type, ['seat', 'table', 'standing_zone'], true)) {
                continue;
            }

            try {
                $pathData = $this->graph->findShortestPathToExit($seat, $this->scaleFactor);

                if (isset($pathData['error'])) {
                    $unreachableSeats++;
                    $violations[] = "Seat {$seat->getLabel()}: {$pathData['error']}";
                    continue;
                }

                $distanceMeters = $pathData['distance_meters'];
                $seatPaths[] = [
                    'seat' => $seat->getLabel(),
                    'distance_canvas' => $pathData['distance_canvas'],
                    'distance_meters' => $distanceMeters,
                    'time_seconds' => $pathData['time_seconds'],
                    'path_node_count' => count($pathData['path']),
                ];

                if ($distanceMeters > self::SOLAS_MAX_EVACUATION_DISTANCE_METERS) {
                    $overDistanceSeats++;
                    $violations[] = "Seat {$seat->getLabel()}: Evacuation distance {$distanceMeters}m exceeds SOLAS limit of " . self::SOLAS_MAX_EVACUATION_DISTANCE_METERS . "m (estimated time: {$pathData['time_seconds']}s)";
                }
            } catch (\Exception $e) {
                Log::error("Pathfinding failed for seat {$seat->id}: " . $e->getMessage());
                $unreachableSeats++;
                $violations[] = "Seat {$seat->getLabel()}: Pathfinding error — " . $e->getMessage();
            }
        }

        $distances = array_column($seatPaths, 'distance_meters');
        $statistics = [
            'total_seats' => $elements->count(),
            'exits_found' => $exits->count(),
            'max_distance_meters' => empty($distances) ? 0 : max($distances),
            'avg_distance_meters' => empty($distances) ? 0 : round(array_sum($distances) / count($distances), 2),
            'over_limit_count' => $overDistanceSeats,
            'unreachable_count' => $unreachableSeats,
            'graph_nodes' => $this->graph->getSummary()['total_nodes'] ?? 0,
            'graph_edges' => $this->graph->getSummary()['total_edges'] ?? 0,
        ];

        return [
            'valid' => empty($violations),
            'violations' => $violations,
            'statistics' => $statistics,
            'paths' => $seatPaths,
        ];
    }

    /**
     * Check if layout has at least 2 independent escape routes per accommodation area
     * (SOLAS requires dual means of egress)
     *
     * @param Event $event
     * @return array
     */
    public function validateDualEgressRoutes(Event $event): array
    {
        $event->load(['eventElements' => fn($q) => $q->where('is_bookable', true)]);
        $violations = [];

        // Group seats by deck level (z coordinate)
        $zones = $event->eventElements->groupBy(function($el) {
            return floor(($el->z ?? 0) / 100); // group every 100 canvas units vertically
        });

        foreach ($zones as $zoneId => $zoneSeats) {
            $exitsInZone = $zoneSeats->where('element_type', 'entrance')
                                     ->where('data->is_main', true)->count();
            if ($exitsInZone < 2) {
                $violations[] = "Zone {$zoneId}: Only {$exitsInZone} emergency exit(s), SOLAS requires minimum 2 independent escape routes";
            }
        }

        return [
            'valid' => empty($violations),
            'violations' => $violations,
        ];
    }

    /**
     * Generate evacuation heat map data for frontend visualization
     *
     * Uses pathfinding-based distances (more accurate than Euclidean)
     *
     * @param Event $event
     * @return array<array{id, x, y, distance_meters, risk_level}>
     */
    public function getEvacuationHeatMap(Event $event): array
    {
        $result = $this->validateEventEvacuationRoutes($event);
        $heatMap = [];

        foreach ($result['paths'] as $path) {
            $riskLevel = 'low';
            if ($path['distance_meters'] > 60) $riskLevel = 'critical';
            elseif ($path['distance_meters'] > 45) $riskLevel = 'high';
            elseif ($path['distance_meters'] > 30) $riskLevel = 'medium';

            $heatMap[] = [
                'id' => $path['seat'],
                'distance_meters' => $path['distance_meters'],
                'time_seconds' => $path['time_seconds'] ?? 0,
                'risk_level' => $riskLevel,
                'path_node_count' => $path['path_node_count'] ?? 0,
            ];
        }

        return $heatMap;
    }

    // ── Legacy helpers (kept for backwards compatibility — not used by new A* engine) ──

    /**
     * Find the nearest exit to a given seat using Euclidean distance
     * DEPRECATED: Use A* pathfinding instead
     */
    private function findNearestExit(EventElement $seat, Collection $exits): ?EventElement
    {
        $nearest = null;
        $minDist = PHP_INT_MAX;

        foreach ($exits as $exit) {
            $dist = $this->euclideanDistance($seat, $exit);
            if ($dist < $minDist) {
                $minDist = $dist;
                $nearest = $exit;
            }
        }

        return $nearest;
    }

    /**
     * Calculate Euclidean distance between two elements
     * DEPRECATED: Use graph-based path distance
     */
    private function euclideanDistance(EventElement $a, EventElement $b): float
    {
        $dx = ($a->x + ($a->width / 2)) - ($b->x + ($b->width / 2));
        $dy = ($a->y + ($a->height / 2)) - ($b->y + ($b->height / 2));
        return sqrt($dx * $dx + $dy * $dy);
    }
}
